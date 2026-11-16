<?php
/**
 * Depósito Nascimento — Loja + Painel do administrador
 *
 * Arquivos:
 *   index.php       → ponto de entrada (esta página): rotas, loja do cliente, layout
 *   config.php      → configurações (banco de dados, administrador)
 *   db.php          → conexão PDO, criação do banco/tabelas e dados iniciais
 *   funcoes.php     → funções e camada de dados (MySQL)
 *   admin_acoes.php → ações POST do painel (produtos, estoque, pedidos, pagamentos, reposição)
 *   admin_views.php → telas do painel
 *   banco/esquema_mysql.sql → esquema do banco
 *   stleys.css + catalogo.css → estilos
 *
 * Loja (clientes):   ?secao=catalogo | carrinho | pedidos | compras   (Contato abre o WhatsApp)
 * Painel (admin):    ?painel=produtos | pedidos | estoque | pagamentos | reposicao
 * O administrador não acessa a loja: ao entrar, só vê o painel.
 * Requer PHP 7.4+ com a extensão pdo_mysql (já vem ativa no XAMPP).
 */

/* ==================================================================
 * 0) CONFIGURAÇÕES
 * ================================================================== */
define('DEPOSITO', true);          // libera a inclusão dos demais arquivos
require __DIR__ . '/config.php';   // banco de dados, administrador, modo demo

// Contato: o menu "Contato" apenas abre o WhatsApp (DDI + DDD + número, só dígitos).
$empresa = [
    'whatsapp' => '5500000000000',
];

/* Erros inesperados (ex.: MySQL desligado no XAMPP) viram uma página amigável. */
set_exception_handler(function (Throwable $e) {
    error_log('[Depósito Nascimento] ' . $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $dica = '';
    if ($e instanceof PDOException) {
        $cod = (int) ($e->errorInfo[1] ?? $e->getCode());
        if ($cod === 2002 || $cod === 2006) {
            $dica = 'O MySQL não respondeu. Abra o Painel de Controle do XAMPP e clique em <strong>Start</strong> ao lado de MySQL.';
        } elseif ($cod === 1045) {
            $dica = 'Usuário ou senha do banco incorretos. Ajuste DB_USUARIO e DB_SENHA no config.php.';
        } else {
            $dica = 'Falha ao acessar o banco de dados. Confira as configurações do config.php.';
        }
    }
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Erro | Depósito Nascimento</title></head>'
       . '<body style="font-family:Arial,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#1E293B">'
       . '<h1 style="color:#F97316">Ops, algo deu errado</h1>'
       . '<p>' . ($dica ?: 'Não foi possível concluir a operação. Tente novamente em instantes.') . '</p>'
       . (defined('DEBUG') && DEBUG ? '<pre style="background:#F1F5F9;padding:12px;white-space:pre-wrap">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>' : '')
       . '</body></html>';
    exit;
});

/* ==================================================================
 * 1) SESSÃO SEGURA, CSRF E FUNÇÕES
 * ================================================================== */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/funcoes.php';   // carrega também o db.php

/* ==================================================================
 * 2) DADOS BÁSICOS
 * ================================================================== */
$catalogoTodos = carregarProdutos();                                   // todos (painel)
$mapa = [];
foreach ($catalogoTodos as $p) {
    $mapa[$p['id']] = $p;
}
$produtos = array_values(array_filter($catalogoTodos, fn($p) => $p['ativo'])); // só os visíveis na loja

$etapas   = ETAPAS;
$urlWhats = 'https://wa.me/' . preg_replace('/\D/', '', $empresa['whatsapp']);

$secoes = [
    'catalogo' => ['Catálogo',       'fa-store'],
    'carrinho' => ['Carrinho',       'fa-cart-shopping'],
    'pedidos'  => ['Meus pedidos',   'fa-box-open'],
    'compras'  => ['Minhas compras', 'fa-receipt'],
];

$secoesAdmin = [
    'produtos'   => ['Produtos',           'fa-boxes-stacked'],
    'pedidos'    => ['Pedidos',            'fa-clipboard-list'],
    'estoque'    => ['Estoque',            'fa-warehouse'],
    'pagamentos' => ['Forma de pagamento', 'fa-credit-card'],
    'reposicao'  => ['Reposição',          'fa-truck-ramp-box'],
];

/* ==================================================================
 * 3) QUEM ESTÁ LOGADO?
 * ================================================================== */
$_SESSION['carrinho'] = $_SESSION['carrinho'] ?? [];

$emailSessao = $_SESSION['usuario_email'] ?? null;
$usuario     = $emailSessao ? buscarUsuario((string) $emailSessao) : null;
if ($emailSessao && !$usuario) {
    unset($_SESSION['usuario_email']);
}
$ehAdmin = !empty($_SESSION['admin']);

/* ==================================================================
 * 4) AÇÕES (POST → redireciona → GET)
 * ================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao   = $_POST['acao'] ?? '';
    $id     = (int) ($_POST['produto_id'] ?? 0);
    $voltar = $_POST['voltar'] ?? 'catalogo';
    if (!isset($secoes[$voltar])) {
        $voltar = 'catalogo';
    }

    if (!hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        aviso('erro', 'Sua sessão expirou. Tente novamente.');
        strpos($acao, 'adm_') === 0 ? irPainel('produtos') : irPara($voltar);
    }

    // Ações do painel do administrador
    if (strpos($acao, 'adm_') === 0) {
        require __DIR__ . '/admin_acoes.php';
    }

    // O administrador só usa o painel: qualquer ação da loja/conta de cliente é bloqueada (exceto sair)
    if ($ehAdmin && $acao !== 'sair') {
        aviso('erro', 'O administrador acessa apenas o painel.');
        irPainel('produtos');
    }

    switch ($acao) {

        /* ------------------- ACESSO ------------------- */
        case 'cadastrar':
            $nome  = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $senha = (string) ($_POST['senha'] ?? '');
            $conf  = (string) ($_POST['senha2'] ?? '');
            $old   = ['nome' => $nome, 'email' => $email];

            if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
                aviso('erro', 'Informe seu nome (2 a 100 caracteres).', 'cadastro', $old);
            } elseif (!emailValido($email)) {
                aviso('erro', 'Informe um e-mail em formato válido (ex.: nome@dominio.com.br).', 'cadastro', $old);
            } elseif (strlen($senha) < 8) {
                aviso('erro', 'A senha precisa ter pelo menos 8 caracteres.', 'cadastro', $old);
            } elseif (senhaTemParteDoEmail($senha, $email)) {
                aviso('erro', 'A senha não pode conter parte do seu e-mail.', 'cadastro', $old);
            } elseif ($senha !== $conf) {
                aviso('erro', 'As senhas não coincidem.', 'cadastro', $old);
            } elseif (buscarUsuario($email)) {
                aviso('erro', 'Já existe uma conta com este e-mail. Use a aba "Entrar".', 'login', ['email' => $email]);
            } else {
                try {
                    transacao(function () use ($nome, $email, $senha, $mapa) {
                        $uid = criarUsuario($nome, $email, password_hash($senha, PASSWORD_DEFAULT));
                        if (MODO_DEMO) {
                            criarPedidosDemo($uid, $mapa);
                        }
                    });
                    session_regenerate_id(true);
                    unset($_SESSION['admin']);
                    $_SESSION['usuario_email'] = $email;
                    aviso('ok', 'Conta criada! Bem-vindo(a), ' . explode(' ', $nome)[0] . '.');
                } catch (PDOException $e) {
                    if ($e->getCode() !== '23000') {   // 23000 = e-mail duplicado (cadastro simultâneo)
                        throw $e;
                    }
                    aviso('erro', 'Já existe uma conta com este e-mail. Use a aba "Entrar".', 'login', ['email' => $email]);
                }
            }
            irPara($voltar);

        case 'entrar':
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $senha = (string) ($_POST['senha'] ?? '');

            if (acessoBloqueado()) {
                aviso('erro', 'Muitas tentativas. Aguarde alguns minutos e tente de novo.', 'login', ['email' => $email]);
                irPara($voltar);
            }

            if (!emailValido($email)) {
                aviso('erro', 'Informe um e-mail em formato válido (ex.: nome@dominio.com.br).', 'login', ['email' => $email]);
                irPara($voltar);
            }
            if (senhaTemParteDoEmail($senha, $email)) {
                aviso('erro', 'A senha não pode conter parte do e-mail.', 'login', ['email' => $email]);
                irPara($voltar);
            }

            $u  = buscarUsuario($email);
            $ok = $u ? password_verify($senha, $u['hash']) : (password_hash($senha, PASSWORD_DEFAULT) && false);

            if ($ok) {
                session_regenerate_id(true);
                unset($_SESSION['admin']);
                limparFalhas();
                $_SESSION['usuario_email'] = $email;
                aviso('ok', 'Olá, ' . explode(' ', $u['nome'])[0] . '! Você entrou na sua conta.');
            } else {
                registrarFalha();
                aviso('erro', 'E-mail ou senha incorretos.', 'login', ['email' => $email]);
            }
            irPara($voltar);

        case 'entrar_admin':
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $senha = (string) ($_POST['senha'] ?? '');

            if (acessoBloqueado()) {
                aviso('erro', 'Muitas tentativas. Aguarde alguns minutos e tente de novo.', 'admin');
                irPara($voltar);
            }

            $emailOk = hash_equals(strtolower(ADMIN_EMAIL), $email);
            $senhaOk = hash_equals(ADMIN_SENHA, $senha);

            if ($emailOk && $senhaOk) {
                session_regenerate_id(true);
                unset($_SESSION['usuario_email']);
                limparFalhas();
                $_SESSION['admin'] = true;
                aviso('ok', 'Acesso de administrador liberado.');
                irPainel('produtos');          // área do administrador começa em "Produtos"
            }
            registrarFalha();
            aviso('erro', 'Credenciais de administrador inválidas.', 'admin');
            irPara($voltar);

        case 'sair':
            $_SESSION = [];
            session_regenerate_id(true);
            aviso('ok', 'Você saiu da sua conta.');
            irPara('catalogo');

        /* ------------------- LOJA ------------------- */
        case 'adicionar':
            $cat   = (string) ($_POST['cat'] ?? '');
            $extra = $cat !== '' ? ['cat' => $cat] : [];

            if (!isset($mapa[$id]) || !$mapa[$id]['ativo']) {
                aviso('erro', 'Produto não encontrado.');
            } elseif (disponivel($mapa[$id]) <= 0) {
                aviso('erro', 'Produto sem estoque no momento.');
            } elseif (($_SESSION['carrinho'][$id] ?? 0) >= disponivel($mapa[$id])) {
                aviso('erro', 'Você já adicionou todo o estoque disponível de "' . $mapa[$id]['nome'] . '".');
            } else {
                $_SESSION['carrinho'][$id] = ($_SESSION['carrinho'][$id] ?? 0) + 1;
                aviso('ok', '"' . $mapa[$id]['nome'] . '" foi adicionado ao carrinho.');
            }
            irPara('catalogo', $extra);

        case 'alterar':
            $delta = (int) ($_POST['delta'] ?? 0) >= 0 ? 1 : -1;
            if (isset($mapa[$id], $_SESSION['carrinho'][$id])) {
                $nova = $_SESSION['carrinho'][$id] + $delta;
                if ($nova <= 0) {
                    unset($_SESSION['carrinho'][$id]);
                } elseif ($nova <= disponivel($mapa[$id])) {
                    $_SESSION['carrinho'][$id] = $nova;
                } else {
                    aviso('erro', 'Quantidade máxima em estoque atingida.');
                }
            }
            irPara('carrinho');

        case 'remover':
            unset($_SESSION['carrinho'][$id]);
            aviso('ok', 'Item removido do carrinho.');
            irPara('carrinho');

        case 'finalizar':
            if (!$usuario) {
                aviso('erro', 'Entre na sua conta (ou crie uma) para finalizar o pedido.', 'login');
                irPara('carrinho');
            }
            if (empty($_SESSION['carrinho'])) {
                aviso('erro', 'Seu carrinho está vazio.');
                irPara('carrinho');
            }

            $pagId = (int) ($_POST['pagamento_id'] ?? 0);
            $pagamento = null;
            foreach (carregarPagamentos() as $pg) {
                if ($pg['ativo'] && $pg['id'] === $pagId) {
                    $pagamento = $pg;
                }
            }
            if (!$pagamento) {
                aviso('erro', 'Escolha uma forma de pagamento.');
                irPara('carrinho');
            }

            $res = criarPedido($usuario['id'], $pagamento, $_SESSION['carrinho']);
            if ($res['ok']) {
                $_SESSION['carrinho'] = [];
                aviso('ok', 'Pedido #' . $res['numero'] . ' realizado com sucesso! Acompanhe o andamento abaixo.'
                    . ($res['removidos'] ? ' Itens sem estoque suficiente ficaram de fora do pedido.' : ''));
                irPara('pedidos');
            }
            aviso('erro', $res['erro']);
            irPara('carrinho');

        case 'recomprar':
            $numero = (int) ($_POST['numero'] ?? 0);
            $adicionados = 0;
            foreach ($usuario ? carregarPedidos($usuario['id']) : [] as $pedido) {
                if ($pedido['numero'] !== $numero || $pedido['status'] !== 3) {
                    continue;
                }
                foreach ($pedido['itens'] as $item) {
                    if (!isset($mapa[$item['id']]) || !$mapa[$item['id']]['ativo']) {
                        continue;
                    }
                    $atual = $_SESSION['carrinho'][$item['id']] ?? 0;
                    $nova  = min($atual + $item['qtd'], disponivel($mapa[$item['id']]));
                    if ($nova > $atual) {
                        $_SESSION['carrinho'][$item['id']] = $nova;
                        $adicionados++;
                    }
                }
            }
            aviso($adicionados ? 'ok' : 'erro',
                  $adicionados ? 'Itens da compra #' . $numero . ' adicionados ao carrinho.'
                               : 'Nenhum item desta compra está disponível agora.');
            irPara('carrinho');
    }

    irPara($voltar);
}

/* ==================================================================
 * 5) PREPARAÇÃO DA PÁGINA
 * ================================================================== */
// Administrador só enxerga o painel (nunca a loja do cliente)
$areaAdmin = $ehAdmin;
$painel    = $_GET['painel'] ?? 'produtos';
if (!isset($secoesAdmin[$painel])) {
    $painel = 'produtos';
}

$secao = $_GET['secao'] ?? 'catalogo';
if (!isset($secoes[$secao])) {
    $secao = 'catalogo';
}

$tituloPagina = $areaAdmin ? 'Painel — ' . $secoesAdmin[$painel][0] : $secoes[$secao][0];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$old        = $flash['old'] ?? [];
$abrirModal = $flash['modal'] ?? '';

$qtdCarrinho = array_sum($_SESSION['carrinho']);

// Pedidos completos só são lidos nas telas que os exibem; o selo do menu usa um COUNT leve
$todosPedidos   = ($usuario && !$areaAdmin && in_array($secao, ['pedidos', 'compras'], true))
    ? carregarPedidos($usuario['id']) : [];
$pedidosCliente = array_values(array_filter($todosPedidos, fn($p) => $p['status'] !== 3));  // em andamento + cancelados
$compras        = array_values(array_filter($todosPedidos, fn($p) => $p['status'] === 3));
$qtdPedidos     = $usuario ? contarPedidosAbertos($usuario['id']) : 0;

$categorias = array_values(array_unique(array_column($produtos, 'categoria')));
$catAtual   = $_GET['cat'] ?? '';
$lista = ($catAtual !== '' && in_array($catAtual, $categorias, true))
    ? array_filter($produtos, fn($p) => $p['categoria'] === $catAtual)
    : $produtos;

// Carrinho detalhado (ignora produtos que o administrador ocultou/excluiu)
$itensCarrinho = [];
$subtotal = 0;
$qtdValida = 0;
foreach ($_SESSION['carrinho'] as $pid => $qtd) {
    if (!isset($mapa[$pid]) || !$mapa[$pid]['ativo']) {
        continue;
    }
    $linha = $mapa[$pid] + ['qtd' => $qtd, 'subtotal' => $qtd * $mapa[$pid]['preco']];
    $itensCarrinho[] = $linha;
    $subtotal += $linha['subtotal'];
    $qtdValida += $qtd;
}

$pagamentosAtivos = array_values(array_filter(carregarPagamentos(), fn($p) => $p['ativo']));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($tituloPagina) ?> | Depósito Nascimento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="stleys.css">
    <link rel="stylesheet" href="catalogo.css">
</head>
<body data-abrir-modal="<?= h($abrirModal) ?>">

<!-- ===================== CABEÇALHO ===================== -->
<header class="header">
    <div class="container header-container">
        <div class="logo"><i class="fa-solid fa-trowel-bricks"></i> Depósito <span>Nascimento</span></div>

        <div class="header-actions">
            <?php if ($usuario): ?>
                <span class="usuario-saudacao">Olá, <strong><?= h(explode(' ', $usuario['nome'])[0]) ?></strong></span>
                <form method="post">
                    <?= csrf() ?><input type="hidden" name="acao" value="sair">
                    <button type="submit" class="account-btn"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
                </form>
            <?php elseif ($ehAdmin): ?>
                <span class="tag-admin"><i class="fa-solid fa-shield-halved"></i> Admin</span>
                <form method="post">
                    <?= csrf() ?><input type="hidden" name="acao" value="sair">
                    <button type="submit" class="account-btn"><i class="fa-solid fa-right-from-bracket"></i> Sair</button>
                </form>
            <?php else: ?>
                <button type="button" class="account-btn" data-abrir-modal="login">
                    <i class="fa-solid fa-circle-user"></i> Entrar
                </button>
            <?php endif; ?>

            <?php if (!$areaAdmin): ?>
                <a href="?secao=carrinho" class="cart-btn header-link">
                    <i class="fa-solid fa-cart-shopping"></i> Carrinho
                    <span class="cart-badge"><?= (int) $qtdCarrinho ?></span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="layout">

    <!-- ===================== MENU LATERAL ===================== -->
    <aside class="menu-lateral">
        <div class="menu-titulo"><?= $areaAdmin ? 'Painel administrativo' : 'Menu' ?></div>
        <nav aria-label="Navegação principal">
            <?php if ($areaAdmin): ?>
                <?php foreach ($secoesAdmin as $chave => [$rotulo, $icone]): ?>
                    <a href="?painel=<?= $chave ?>" class="<?= $painel === $chave ? 'ativo' : '' ?>"
                       <?= $painel === $chave ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= $icone ?>"></i> <?= h($rotulo) ?>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($secoes as $chave => [$rotulo, $icone]):
                    $badge = $chave === 'carrinho' ? $qtdCarrinho : ($chave === 'pedidos' ? $qtdPedidos : 0);
                ?>
                    <a href="?secao=<?= $chave ?>" class="<?= $secao === $chave ? 'ativo' : '' ?>"
                       <?= $secao === $chave ? 'aria-current="page"' : '' ?>>
                        <i class="fa-solid <?= $icone ?>"></i>
                        <?= h($rotulo) ?>
                        <?php if ($badge > 0): ?><span class="menu-badge"><?= (int) $badge ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <a href="<?= h($urlWhats) ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-headset"></i> Contato
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- ===================== CONTEÚDO ===================== -->
    <main class="conteudo">

<?php /* ============================== PAINEL DO ADMINISTRADOR ============================== */ ?>
<?php if ($areaAdmin): ?>

    <?php require __DIR__ . '/admin_views.php'; ?>

<?php /* ------------------------------ CATÁLOGO ------------------------------ */ ?>
<?php elseif ($secao === 'catalogo'): ?>

        <div class="section-title">
            <h2>Nosso Catálogo</h2>
            <p>Tudo para sua obra, com os melhores preços e pronta entrega.</p>
        </div>

        <div class="filtros">
            <a href="?secao=catalogo" class="cat-btn <?= $catAtual === '' ? 'active' : '' ?>">Todos</a>
            <?php foreach ($categorias as $cat): ?>
                <a href="?secao=catalogo&cat=<?= urlencode($cat) ?>" class="cat-btn <?= $catAtual === $cat ? 'active' : '' ?>">
                    <?= h($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!$lista): ?>
            <div class="vazio">
                <i class="fa-solid fa-store"></i>
                <h3>Nenhum produto disponível</h3>
                <p>Volte em breve — estamos atualizando o catálogo.</p>
            </div>
        <?php endif; ?>

        <div class="product-grid">
            <?php foreach ($lista as $p):
                $disp     = disponivel($p);
                $esgotado = $disp <= 0;
                $baixo    = !$esgotado && $disp <= 10;
                $estado   = $esgotado ? 'esgotado' : ($baixo ? 'baixo' : '');
            ?>
                <article class="product-card <?= $esgotado ? 'esgotado' : '' ?>">
                    <div class="product-image-wrap">
                        <img class="product-image" src="<?= h(srcImagem($p)) ?>" alt="<?= h($p['nome']) ?>" loading="lazy"
                             onerror="this.onerror=null;this.src='<?= h(imagemReserva($p['nome'])) ?>';">
                        <?php if ($esgotado): ?>
                            <span class="badge-estoque esgotado">Esgotado</span>
                        <?php elseif ($baixo): ?>
                            <span class="badge-estoque baixo">Últimas unidades</span>
                        <?php endif; ?>
                    </div>

                    <div class="product-info">
                        <div>
                            <div class="product-category"><?= h($p['categoria']) ?></div>
                            <h3 class="product-name"><?= h($p['nome']) ?></h3>
                            <p class="product-desc"><?= h($p['descricao']) ?></p>
                            <div class="product-stock <?= $estado ?>">
                                <i class="fa-solid <?= $esgotado ? 'fa-circle-xmark' : 'fa-boxes-stacked' ?>"></i>
                                <?= $esgotado ? 'Sem estoque' : $disp . ' em estoque' ?>
                            </div>
                        </div>

                        <div class="product-footer">
                            <span class="product-price"><?= moeda($p['preco']) ?></span>
                            <form method="post">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adicionar">
                                <input type="hidden" name="produto_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="cat" value="<?= h($catAtual) ?>">
                                <button type="submit" class="btn-add" <?= $esgotado ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-cart-plus"></i> <?= $esgotado ? 'Indisponível' : 'Adicionar' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

<?php /* ------------------------------ CARRINHO ------------------------------ */ ?>
<?php elseif ($secao === 'carrinho'): ?>

        <div class="section-title">
            <h2>Meu Carrinho</h2>
            <p>Confira os itens e ajuste as quantidades antes de finalizar.</p>
        </div>

        <?php if (!$itensCarrinho): ?>
            <div class="vazio">
                <i class="fa-solid fa-cart-shopping"></i>
                <h3>Seu carrinho está vazio</h3>
                <p>Explore o catálogo e adicione os materiais da sua obra.</p>
                <a href="?secao=catalogo" class="btn-link"><i class="fa-solid fa-store"></i> Ver catálogo</a>
            </div>
        <?php else: ?>
            <div class="carrinho-layout">
                <div class="lista-carrinho">
                    <?php foreach ($itensCarrinho as $i): ?>
                        <div class="item-carrinho-linha">
                            <img src="<?= h(srcImagem($i)) ?>" alt="<?= h($i['nome']) ?>"
                                 onerror="this.onerror=null;this.src='<?= h(imagemReserva($i['nome'])) ?>';">
                            <div>
                                <div class="item-nome"><?= h($i['nome']) ?></div>
                                <div class="item-unit"><?= moeda($i['preco']) ?> cada</div>
                            </div>

                            <div class="qtd-controle">
                                <form method="post">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="alterar">
                                    <input type="hidden" name="voltar" value="carrinho">
                                    <input type="hidden" name="produto_id" value="<?= (int) $i['id'] ?>">
                                    <input type="hidden" name="delta" value="-1">
                                    <button type="submit" aria-label="Diminuir">−</button>
                                </form>
                                <span><?= (int) $i['qtd'] ?></span>
                                <form method="post">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="alterar">
                                    <input type="hidden" name="voltar" value="carrinho">
                                    <input type="hidden" name="produto_id" value="<?= (int) $i['id'] ?>">
                                    <input type="hidden" name="delta" value="1">
                                    <button type="submit" aria-label="Aumentar" <?= $i['qtd'] >= disponivel($i) ? 'disabled' : '' ?>>+</button>
                                </form>
                            </div>

                            <div class="item-direita">
                                <div class="item-subtotal"><?= moeda($i['subtotal']) ?></div>
                                <form method="post" data-confirmar="Remover este item do carrinho?">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="remover">
                                    <input type="hidden" name="voltar" value="carrinho">
                                    <input type="hidden" name="produto_id" value="<?= (int) $i['id'] ?>">
                                    <button type="submit" class="btn-remover"><i class="fa-regular fa-trash-can"></i> Remover</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="resumo">
                    <h3>Resumo do pedido</h3>
                    <div class="resumo-linha"><span>Itens (<?= (int) $qtdValida ?>)</span><span><?= moeda($subtotal) ?></span></div>
                    <div class="resumo-linha"><span>Entrega</span><span>A combinar</span></div>
                    <div class="resumo-total"><span>Total</span><strong id="valorTotal"><?= moeda($subtotal) ?></strong></div>

                    <form method="post">
                        <?= csrf() ?>
                        <input type="hidden" name="acao" value="finalizar">
                        <input type="hidden" name="voltar" value="carrinho">

                        <?php if ($pagamentosAtivos): ?>
                            <fieldset class="pagto-opcoes">
                                <legend>Forma de pagamento</legend>
                                <?php foreach ($pagamentosAtivos as $n => $pg): ?>
                                    <label class="opcao-pagto">
                                        <input type="radio" name="pagamento_id" value="<?= (int) $pg['id'] ?>"
                                               <?= $n === 0 ? 'checked' : '' ?> required>
                                        <span>
                                            <strong><?= h($pg['nome']) ?></strong>
                                            <small>
                                                <?= h($pg['descricao']) ?>
                                                <?= $pg['parcelas'] > 1 ? ' · até ' . (int) $pg['parcelas'] . 'x' : '' ?>
                                            </small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <button type="submit" class="btn-checkout"><i class="fa-solid fa-check"></i> Finalizar pedido</button>
                        <?php else: ?>
                            <p class="dica-login">Nenhuma forma de pagamento disponível no momento. Fale com a gente pelo WhatsApp (menu Contato).</p>
                            <button type="submit" class="btn-checkout" disabled><i class="fa-solid fa-check"></i> Finalizar pedido</button>
                        <?php endif; ?>
                    </form>

                    <?php if (!$usuario): ?>
                        <p class="dica-login">Você precisará entrar na sua conta para concluir.</p>
                    <?php endif; ?>
                    <a href="?secao=catalogo" class="btn-link btn-outline btn-continuar">Continuar comprando</a>
                </aside>
            </div>
        <?php endif; ?>

<?php /* ------------------------------ MEUS PEDIDOS ------------------------------ */ ?>
<?php elseif ($secao === 'pedidos'): ?>

        <div class="section-title">
            <h2>Meus Pedidos</h2>
            <p>Acompanhe o andamento dos pedidos que ainda não foram entregues.</p>
        </div>

        <?php if (!$usuario): ?>
            <div class="vazio">
                <i class="fa-solid fa-lock"></i>
                <h3>Entre para ver seus pedidos</h3>
                <p>Faça login ou crie uma conta para acompanhar suas compras.</p>
                <button type="button" class="btn-link" data-abrir-modal="login"><i class="fa-solid fa-circle-user"></i> Entrar</button>
            </div>
        <?php elseif (!$pedidosCliente): ?>
            <div class="vazio">
                <i class="fa-solid fa-box-open"></i>
                <h3>Nenhum pedido em andamento</h3>
                <p>Quando você finalizar uma compra, ela aparecerá aqui.</p>
                <a href="?secao=catalogo" class="btn-link"><i class="fa-solid fa-store"></i> Ir para o catálogo</a>
            </div>
        <?php else: ?>
            <div class="lista-pedidos">
                <?php foreach ($pedidosCliente as $pedido): $cancelado = $pedido['status'] === 4; ?>
                    <article class="pedido-card">
                        <div class="pedido-topo">
                            <div>
                                <div class="pedido-numero">Pedido #<?= (int) $pedido['numero'] ?></div>
                                <div class="pedido-data">Realizado em <?= h($pedido['data']) ?></div>
                            </div>
                            <?php if ($cancelado): ?>
                                <span class="status-tag cancelado"><i class="fa-solid fa-ban"></i> Cancelado</span>
                            <?php else: ?>
                                <span class="status-tag"><i class="fa-solid fa-truck-fast"></i> <?= h($etapas[$pedido['status']]) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="pedido-corpo">
                            <?php if (!$cancelado): ?>
                                <ol class="etapas">
                                    <?php foreach ($etapas as $n => $nomeEtapa): ?>
                                        <li class="etapa <?= $n <= $pedido['status'] ? 'feita' : '' ?> <?= $n === $pedido['status'] ? 'atual' : '' ?>">
                                            <div class="etapa-bolinha"><?= $n <= $pedido['status'] ? '<i class="fa-solid fa-check"></i>' : $n + 1 ?></div>
                                            <?= h($nomeEtapa) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>

                            <ul class="pedido-itens">
                                <?php foreach ($pedido['itens'] as $item): ?>
                                    <li>
                                        <span><?= (int) $item['qtd'] ?>× <?= h($item['nome']) ?></span>
                                        <span><?= moeda($item['qtd'] * $item['preco']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if (!empty($pedido['pagamento'])): ?>
                                <div class="pedido-pagto"><i class="fa-solid fa-credit-card"></i> <?= h($pedido['pagamento']['nome']) ?></div>
                            <?php endif; ?>

                            <div class="pedido-rodape">
                                <span class="pedido-total">Total: <strong><?= moeda(totalPedido($pedido)) ?></strong></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php /* ------------------------------ MINHAS COMPRAS ------------------------------ */ ?>
<?php elseif ($secao === 'compras'): ?>

        <div class="section-title">
            <h2>Minhas Compras</h2>
            <p>Histórico dos pedidos já entregues.</p>
        </div>

        <?php if (!$usuario): ?>
            <div class="vazio">
                <i class="fa-solid fa-lock"></i>
                <h3>Entre para ver suas compras</h3>
                <p>Faça login ou crie uma conta para consultar seu histórico.</p>
                <button type="button" class="btn-link" data-abrir-modal="login"><i class="fa-solid fa-circle-user"></i> Entrar</button>
            </div>
        <?php elseif (!$compras): ?>
            <div class="vazio">
                <i class="fa-solid fa-receipt"></i>
                <h3>Você ainda não tem compras concluídas</h3>
                <p>Os pedidos entregues ficam registrados aqui.</p>
                <a href="?secao=catalogo" class="btn-link"><i class="fa-solid fa-store"></i> Ver catálogo</a>
            </div>
        <?php else:
            $totalGasto = array_sum(array_map('totalPedido', $compras));
        ?>
            <div class="cartoes-resumo">
                <div class="cartao-resumo"><span>Compras concluídas</span><strong><?= count($compras) ?></strong></div>
                <div class="cartao-resumo"><span>Total investido</span><strong><?= moeda($totalGasto) ?></strong></div>
            </div>

            <div class="lista-pedidos">
                <?php foreach ($compras as $compra): ?>
                    <article class="pedido-card">
                        <div class="pedido-topo">
                            <div>
                                <div class="pedido-numero">Compra #<?= (int) $compra['numero'] ?></div>
                                <div class="pedido-data"><?= h($compra['data']) ?></div>
                            </div>
                            <span class="status-tag entregue"><i class="fa-solid fa-circle-check"></i> Entregue</span>
                        </div>

                        <div class="pedido-corpo">
                            <ul class="pedido-itens">
                                <?php foreach ($compra['itens'] as $item): ?>
                                    <li>
                                        <span><?= (int) $item['qtd'] ?>× <?= h($item['nome']) ?></span>
                                        <span><?= moeda($item['qtd'] * $item['preco']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                            <?php if (!empty($compra['pagamento'])): ?>
                                <div class="pedido-pagto"><i class="fa-solid fa-credit-card"></i> <?= h($compra['pagamento']['nome']) ?></div>
                            <?php endif; ?>

                            <div class="pedido-rodape">
                                <span class="pedido-total">Total: <strong><?= moeda(totalPedido($compra)) ?></strong></span>
                                <form method="post">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="recomprar">
                                    <input type="hidden" name="voltar" value="compras">
                                    <input type="hidden" name="numero" value="<?= (int) $compra['numero'] ?>">
                                    <button type="submit" class="btn-link btn-outline"><i class="fa-solid fa-rotate-right"></i> Comprar novamente</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

<?php endif; ?>

    </main>
</div>

<?php /* ===================== MODAL DE ACESSO (só para visitantes) ===================== */ ?>
<?php if (!$usuario && !$ehAdmin): ?>
<div class="auth-modal" id="authModal" role="dialog" aria-modal="true" aria-labelledby="authTitulo">
    <div class="auth-box">
        <div class="auth-topo">
            <h2 id="authTitulo"><i class="fa-solid fa-circle-user"></i> Acesse sua conta</h2>
            <button type="button" class="close-btn" data-fechar-modal aria-label="Fechar">×</button>
        </div>

        <div class="auth-tabs" role="tablist">
            <button type="button" role="tab" data-aba="login">Entrar</button>
            <button type="button" role="tab" data-aba="cadastro">Criar conta</button>
            <button type="button" role="tab" data-aba="admin">Administrador</button>
        </div>

        <div class="auth-painel" data-painel="login">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="entrar">
                <input type="hidden" name="voltar" value="<?= h($secao) ?>">
                <div class="campo">
                    <label for="l-email">E-mail</label>
                    <input type="email" id="l-email" name="email" autocomplete="email" required
                           value="<?= h($abrirModal === 'login' ? ($old['email'] ?? '') : '') ?>">
                </div>
                <div class="campo">
                    <label for="l-senha">Senha</label>
                    <input type="password" id="l-senha" name="senha" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-enviar"><i class="fa-solid fa-right-to-bracket"></i> Entrar</button>
            </form>
            <div class="auth-troca">Ainda não tem conta? <button type="button" data-aba="cadastro">Criar conta</button></div>
        </div>

        <div class="auth-painel" data-painel="cadastro">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="cadastrar">
                <input type="hidden" name="voltar" value="<?= h($secao) ?>">
                <div class="campo">
                    <label for="r-nome">Nome completo</label>
                    <input type="text" id="r-nome" name="nome" maxlength="100" autocomplete="name" required
                           value="<?= h($abrirModal === 'cadastro' ? ($old['nome'] ?? '') : '') ?>">
                </div>
                <div class="campo">
                    <label for="r-email">E-mail</label>
                    <input type="email" id="r-email" name="email" maxlength="150" autocomplete="email" required
                           value="<?= h($abrirModal === 'cadastro' ? ($old['email'] ?? '') : '') ?>">
                </div>
                <div class="campo-linha">
                    <div class="campo">
                        <label for="r-senha">Senha</label>
                        <input type="password" id="r-senha" name="senha" minlength="8" autocomplete="new-password" required>
                        <small>Mínimo de 8 caracteres, sem partes do seu e-mail.</small>
                    </div>
                    <div class="campo">
                        <label for="r-senha2">Confirmar senha</label>
                        <input type="password" id="r-senha2" name="senha2" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn-enviar"><i class="fa-solid fa-user-plus"></i> Criar minha conta</button>
            </form>
            <div class="auth-troca">Já tem conta? <button type="button" data-aba="login">Entrar</button></div>
        </div>

        <div class="auth-painel" data-painel="admin">
            <p>Acesso restrito à equipe do Depósito Nascimento.</p>
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="entrar_admin">
                <input type="hidden" name="voltar" value="<?= h($secao) ?>">
                <div class="campo">
                    <label for="a-email">E-mail do administrador</label>
                    <input type="email" id="a-email" name="email" autocomplete="username" required>
                </div>
                <div class="campo">
                    <label for="a-senha">Senha</label>
                    <input type="password" id="a-senha" name="senha" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-enviar escuro"><i class="fa-solid fa-shield-halved"></i> Entrar como administrador</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="toast <?= h($flash['tipo']) ?>" role="status" id="toast">
        <i class="fa-solid <?= $flash['tipo'] === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <span><?= h($flash['texto']) ?></span>
        <button type="button" aria-label="Fechar" onclick="this.parentElement.remove()">×</button>
    </div>
<?php endif; ?>

<script>
(function () {
    // Confirmação em formulários com data-confirmar
    document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirmar)) e.preventDefault();
        });
    });

    // Remove o aviso depois da animação CSS
    var toast = document.getElementById('toast');
    if (toast) setTimeout(function () { toast.remove(); }, 5000);

    // Prévia da imagem no cadastro/edição de produto
    var inputImg = document.getElementById('p-imagem');
    if (inputImg) {
        inputImg.addEventListener('change', function () {
            var arq = inputImg.files && inputImg.files[0];
            if (arq) document.getElementById('previaImagem').src = URL.createObjectURL(arq);
        });
    }

    // Modal de acesso
    var modal = document.getElementById('authModal');
    if (!modal) return;

    function mostrar(aba) {
        modal.querySelectorAll('.auth-tabs [data-aba]').forEach(function (b) {
            b.classList.toggle('ativo', b.dataset.aba === aba);
        });
        modal.querySelectorAll('[data-painel]').forEach(function (p) {
            p.classList.toggle('ativo', p.dataset.painel === aba);
        });
    }

    function abrir(aba) {
        mostrar(aba || 'login');
        modal.classList.add('open');
        var campo = modal.querySelector('[data-painel].ativo input:not([type=hidden])');
        if (campo) setTimeout(function () { campo.focus(); }, 120);
    }

    function fechar() { modal.classList.remove('open'); }

    document.querySelectorAll('[data-abrir-modal]').forEach(function (el) {
        if (el === document.body) return;
        el.addEventListener('click', function () { abrir(el.dataset.abrirModal); });
    });

    modal.querySelectorAll('[data-aba]').forEach(function (b) {
        b.addEventListener('click', function () { mostrar(b.dataset.aba); });
    });

    modal.querySelectorAll('[data-fechar-modal]').forEach(function (b) { b.addEventListener('click', fechar); });
    modal.addEventListener('click', function (e) { if (e.target === modal) fechar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') fechar(); });

    if (document.body.dataset.abrirModal) abrir(document.body.dataset.abrirModal);
})();
</script>

</body>
</html>