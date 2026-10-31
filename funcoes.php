<?php
/**
 * Depósito Nascimento — funções compartilhadas (loja + painel do administrador)
 * Camada de dados em MySQL (PDO). Não acesse diretamente: é incluído pelo index.php.
 */
defined('DEPOSITO') or exit;

require_once __DIR__ . '/db.php';

/** Etapas do pedido (índice = status). Status 4 = cancelado. */
const ETAPAS = ['Pedido recebido', 'Em separação', 'Saiu para entrega', 'Entregue'];

/* ==================================================================
 * Utilitários de saída e navegação
 * ================================================================== */
function h($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function imagemReserva(string $nome): string
{
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200">'
         . '<rect width="300" height="200" fill="#F8FAFC"/>'
         . '<g fill="none" stroke="#F97316" stroke-width="6" stroke-linejoin="round">'
         . '<path d="M100 85 150 60l50 25v55l-50 25-50-25z"/><path d="M100 85l50 25 50-25M150 110v55"/></g>'
         . '<text x="150" y="190" font-family="Arial" font-size="13" fill="#64748B" text-anchor="middle">'
         . htmlspecialchars(mb_strimwidth($nome, 0, 32, '…'), ENT_XML1, 'UTF-8') . '</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

function srcImagem(array $p): string
{
    $arquivo = __DIR__ . '/' . ($p['imagem'] ?? '');
    return (!empty($p['imagem']) && is_file($arquivo)) ? $p['imagem'] : imagemReserva($p['nome']);
}

/** Aviso (toast). $modal reabre o modal de acesso; $old repovoa formulários. */
function aviso(string $tipo, string $texto, ?string $modal = null, array $old = []): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto, 'modal' => $modal, 'old' => $old];
}

function irPara(string $secao, array $extra = []): void
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['secao' => $secao] + $extra));
    exit;
}

function irPainel(string $painel, array $extra = []): void
{
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(['painel' => $painel] + $extra));
    exit;
}

/* ==================================================================
 * Clientes e proteção contra tentativas de login em excesso
 * ================================================================== */
const LOGIN_MAX_FALHAS = 5;     // falhas permitidas...
const LOGIN_JANELA_SEG = 300;   // ...dentro desta janela (5 min), por IP

function buscarUsuario(string $email): ?array
{
    $st = db()->prepare('SELECT id, nome, email, hash FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    return $u ? ['id' => (int) $u['id']] + $u : null;
}

function criarUsuario(string $nome, string $email, string $hash): int
{
    db()->prepare('INSERT INTO usuarios (nome, email, hash, criado_em) VALUES (?,?,?,?)')
        ->execute([$nome, $email, $hash, date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

/**
 * E-mail em formato estrito: parte local + "@" + domínio com ponto e extensão de 2+ letras.
 * (o FILTER_VALIDATE_EMAIL sozinho aceita coisas como "a@b", sem extensão)
 */
function emailValido(string $email): bool
{
    if ($email === '' || strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [$local, $dominio] = explode('@', $email, 2) + ['', ''];
    if ($local === '' || strlen($local) > 64 || strpos($email, '..') !== false
        || $local[0] === '.' || substr($local, -1) === '.') {
        return false;
    }
    return (bool) preg_match('/^[a-z0-9._%+\-]+@([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $email);
}

/** Minúsculas, sem acentos e só letras/números — base de comparação entre senha e e-mail. */
function normalizarComparacao(string $txt): string
{
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = strtr($txt, [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c','ñ'=>'n',
    ]);
    return (string) preg_replace('/[^a-z0-9]/', '', $txt);
}

/**
 * true se a senha contém parte do e-mail: o usuário (antes do "@") ou o nome do
 * domínio (ex.: "gmail"), inteiros ou em trechos de 4+ caracteres seguidos.
 * Ignora maiúsculas, acentos e pontuação ("Joao_Silva1" contém "joao.silva").
 */
function senhaTemParteDoEmail(string $senha, string $email): bool
{
    $senhaN = normalizarComparacao($senha);
    [$local, $dominio] = explode('@', strtolower($email), 2) + ['', ''];
    $partes = [$local, explode('.', $dominio)[0]];

    foreach ($partes as $parte) {
        $parte = normalizarComparacao($parte);
        $tam   = strlen($parte);
        if ($tam < 3) {
            continue;   // trechos muito curtos gerariam falsos positivos
        }
        if ($tam <= 4) {
            if (strpos($senhaN, $parte) !== false) {
                return true;
            }
            continue;
        }
        for ($i = 0; $i + 4 <= $tam; $i++) {
            if (strpos($senhaN, substr($parte, $i, 4)) !== false) {
                return true;
            }
        }
    }
    return false;
}

function ipCliente(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function acessoBloqueado(): bool
{
    $st = db()->prepare('SELECT COUNT(*) FROM tentativas_login WHERE ip = ? AND criado_em > ?');
    $st->execute([ipCliente(), date('Y-m-d H:i:s', time() - LOGIN_JANELA_SEG)]);
    return (int) $st->fetchColumn() >= LOGIN_MAX_FALHAS;
}

function registrarFalha(): void
{
    db()->prepare('INSERT INTO tentativas_login (ip, criado_em) VALUES (?, ?)')
        ->execute([ipCliente(), date('Y-m-d H:i:s')]);
    if (random_int(1, 20) === 1) {   // faxina ocasional de registros com mais de 1 dia
        db()->prepare('DELETE FROM tentativas_login WHERE criado_em < ?')
            ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function limparFalhas(): void
{
    db()->prepare('DELETE FROM tentativas_login WHERE ip = ?')->execute([ipCliente()]);
}

/* ==================================================================
 * Produtos
 * ================================================================== */
function produtosPadrao(): array
{
    return [
        ['id' => 1, 'categoria' => 'Cimento e Argamassa', 'nome' => 'Cimento CP II 50kg',        'descricao' => 'Saco de 50 kg, ideal para fundações, lajes e reboco.',                       'estoque' => 120, 'minimo' => 30, 'preco' => 38.90,  'imagem' => 'img/cimento.jpg',      'ativo' => true],
        ['id' => 2, 'categoria' => 'Cimento e Argamassa', 'nome' => 'Argamassa AC-II 20kg',      'descricao' => 'Para assentamento de pisos e revestimentos cerâmicos internos e externos.', 'estoque' => 64,  'minimo' => 20, 'preco' => 24.50,  'imagem' => 'img/argamassa.jpg',    'ativo' => true],
        ['id' => 3, 'categoria' => 'Tintas',              'nome' => 'Tinta Acrílica Branca 18L', 'descricao' => 'Acabamento fosco, alto rendimento e ótima cobertura.',                      'estoque' => 18,  'minimo' => 8,  'preco' => 289.90, 'imagem' => 'img/tinta.jpg',        'ativo' => true],
        ['id' => 4, 'categoria' => 'Ferramentas',         'nome' => 'Martelo de Unha 27mm',      'descricao' => 'Cabo ergonômico antiderrapante e cabeça de aço forjado.',                    'estoque' => 35,  'minimo' => 10, 'preco' => 42.00,  'imagem' => 'img/martelo.jpg',      'ativo' => true],
        ['id' => 5, 'categoria' => 'Ferramentas',         'nome' => 'Furadeira de Impacto 650W', 'descricao' => 'Mandril de 13 mm, velocidade variável e reversão.',                          'estoque' => 7,   'minimo' => 5,  'preco' => 259.00, 'imagem' => 'img/furadeira.jpg',    'ativo' => true],
        ['id' => 6, 'categoria' => 'Elétrica',            'nome' => 'Fio Flexível 2,5mm 100m',   'descricao' => 'Rolo com 100 metros, isolamento 750V, certificado pelo Inmetro.',            'estoque' => 42,  'minimo' => 12, 'preco' => 219.90, 'imagem' => 'img/fio.jpg',          'ativo' => true],
        ['id' => 7, 'categoria' => 'Hidráulica',          'nome' => 'Caixa d\'água 1000L',       'descricao' => 'Polietileno com tampa rosqueável, proteção contra raios UV.',                'estoque' => 5,   'minimo' => 4,  'preco' => 479.00, 'imagem' => 'img/caixa-dagua.jpg',  'ativo' => true],
        ['id' => 8, 'categoria' => 'Hidráulica',          'nome' => 'Tubo PVC Soldável 25mm 6m', 'descricao' => 'Barra de 6 metros para instalações de água fria.',                           'estoque' => 0,   'minimo' => 20, 'preco' => 31.80,  'imagem' => 'img/tubo.jpg',         'ativo' => true],
    ];
}

function normalizarProduto(array $p): array
{
    return [
        'id'        => (int) $p['id'],
        'categoria' => (string) $p['categoria'],
        'nome'      => (string) $p['nome'],
        'descricao' => (string) $p['descricao'],
        'estoque'   => (int) $p['estoque'],
        'minimo'    => (int) $p['minimo'],
        'preco'     => (float) $p['preco'],
        'imagem'    => (string) $p['imagem'],
        'ativo'     => (bool) $p['ativo'],
    ];
}

function carregarProdutos(): array
{
    $linhas = db()->query('SELECT id, categoria, nome, descricao, estoque, minimo, preco, imagem, ativo FROM produtos ORDER BY id')->fetchAll();
    return array_map('normalizarProduto', $linhas);
}

function inserirProduto(array $p): int
{
    $agora = date('Y-m-d H:i:s');
    db()->prepare('INSERT INTO produtos (categoria, nome, descricao, estoque, minimo, preco, imagem, ativo, criado_em, atualizado_em)
                   VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$p['categoria'], $p['nome'], $p['descricao'], $p['estoque'], $p['minimo'], $p['preco'],
                   $p['imagem'], $p['ativo'] ? 1 : 0, $agora, $agora]);
    return (int) db()->lastInsertId();
}

function atualizarProduto(array $p): void
{
    db()->prepare('UPDATE produtos SET categoria = ?, nome = ?, descricao = ?, estoque = ?, minimo = ?, preco = ?,
                   imagem = ?, ativo = ?, atualizado_em = ? WHERE id = ?')
        ->execute([$p['categoria'], $p['nome'], $p['descricao'], $p['estoque'], $p['minimo'], $p['preco'],
                   $p['imagem'], $p['ativo'] ? 1 : 0, date('Y-m-d H:i:s'), $p['id']]);
}

/** Exclui o produto. Pedidos, movimentações e reposições antigas mantêm o histórico (FK SET NULL). */
function excluirProduto(int $id): ?array
{
    return transacao(function (PDO $pdo) use ($id) {
        $st = $pdo->prepare('SELECT id, nome, imagem FROM produtos WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        $p = $st->fetch();
        if (!$p) {
            return null;
        }
        $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);
        return $p;
    });
}

/** Salva a imagem enviada em img/produtos/. Retorna o caminho ou null (com $erro preenchido se houve problema). */
function salvarImagem($arquivo, ?string &$erro): ?string
{
    if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erro = 'Falha no envio da imagem. Tente novamente.';
        return null;
    }
    if ($arquivo['size'] > 2 * 1024 * 1024) {
        $erro = 'A imagem deve ter no máximo 2 MB.';
        return null;
    }
    $info = @getimagesize($arquivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]])) {
        $erro = 'Envie uma imagem JPG, PNG ou WEBP.';
        return null;
    }
    $dir = __DIR__ . '/img/produtos';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        $erro = 'Não foi possível criar a pasta img/produtos.';
        return null;
    }
    // Nunca executa scripts dentro da pasta de uploads (Apache/XAMPP)
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n    Require all denied\n</FilesMatch>\n");
    }
    $nome = bin2hex(random_bytes(8)) . '.' . $tipos[$info[2]];
    if (!move_uploaded_file($arquivo['tmp_name'], $dir . '/' . $nome)) {
        $erro = 'Não foi possível salvar a imagem no servidor.';
        return null;
    }
    return 'img/produtos/' . $nome;
}

/** Só apaga arquivos enviados pelo painel (img/produtos/*), nunca as imagens originais do site. */
function removerImagem(string $caminho): void
{
    if (preg_match('#^img/produtos/[a-f0-9]{16}\.(jpg|png|webp)$#', $caminho) && is_file(__DIR__ . '/' . $caminho)) {
        @unlink(__DIR__ . '/' . $caminho);
    }
}

/* ==================================================================
 * Formas de pagamento
 * ================================================================== */
function pagamentosPadrao(): array
{
    return [
        ['id' => 1, 'nome' => 'PIX',                 'descricao' => 'Aprovação imediata, com desconto à vista.', 'desconto' => 5.0, 'parcelas' => 1, 'ativo' => true],
        ['id' => 2, 'nome' => 'Cartão de crédito',   'descricao' => 'Parcelamento em até 6x.',                   'desconto' => 0.0, 'parcelas' => 6, 'ativo' => true],
        ['id' => 3, 'nome' => 'Cartão de débito',    'descricao' => 'Débito na entrega ou na retirada.',         'desconto' => 0.0, 'parcelas' => 1, 'ativo' => true],
        ['id' => 4, 'nome' => 'Boleto bancário',     'descricao' => 'Compensação em até 2 dias úteis.',          'desconto' => 0.0, 'parcelas' => 1, 'ativo' => true],
        ['id' => 5, 'nome' => 'Dinheiro na entrega', 'descricao' => 'Pague ao receber os materiais.',            'desconto' => 0.0, 'parcelas' => 1, 'ativo' => false],
    ];
}

function carregarPagamentos(): array
{
    $linhas = db()->query('SELECT id, nome, descricao, desconto, parcelas, ativo FROM formas_pagamento ORDER BY id')->fetchAll();
    return array_map(fn($p) => [
        'id'        => (int) $p['id'],
        'nome'      => (string) $p['nome'],
        'descricao' => (string) $p['descricao'],
        'desconto'  => (float) $p['desconto'],
        'parcelas'  => max(1, (int) $p['parcelas']),
        'ativo'     => (bool) $p['ativo'],
    ], $linhas);
}

/** Atualiza (se $id existir) ou cria a forma de pagamento. Retorna true se atualizou. */
function salvarPagamento(int $id, array $item): bool
{
    $pdo = db();
    $existe = false;
    if ($id > 0) {
        $st = $pdo->prepare('SELECT 1 FROM formas_pagamento WHERE id = ?');
        $st->execute([$id]);
        $existe = (bool) $st->fetchColumn();
    }
    if ($existe) {
        $pdo->prepare('UPDATE formas_pagamento SET nome = ?, descricao = ?, desconto = ?, parcelas = ?, ativo = ? WHERE id = ?')
            ->execute([$item['nome'], $item['descricao'], $item['desconto'], $item['parcelas'], $item['ativo'] ? 1 : 0, $id]);
    } else {
        $pdo->prepare('INSERT INTO formas_pagamento (nome, descricao, desconto, parcelas, ativo) VALUES (?,?,?,?,?)')
            ->execute([$item['nome'], $item['descricao'], $item['desconto'], $item['parcelas'], $item['ativo'] ? 1 : 0]);
    }
    return $existe;
}

/** Pedidos antigos mantêm o nome da forma de pagamento (pagamento_nome). */
function excluirPagamento(int $id): void
{
    db()->prepare('DELETE FROM formas_pagamento WHERE id = ?')->execute([$id]);
}

/* ==================================================================
 * Movimentações de estoque
 * ================================================================== */
function movimento(array $produto, string $tipo, int $qtd, int $saldo, string $motivo): array
{
    return [
        'produto_id' => $produto['id'],
        'produto'    => $produto['nome'],
        'tipo'       => $tipo,     // Entrada inicial | Ajuste | Venda | Reposição
        'qtd'        => $qtd,      // positivo = entrou, negativo = saiu
        'saldo'      => $saldo,
        'motivo'     => $motivo,
    ];
}

function registrarMovimentos(array $novos): void
{
    if (!$novos) {
        return;
    }
    $st = db()->prepare('INSERT INTO movimentos (produto_id, produto_nome, tipo, qtd, saldo, motivo, criado_em) VALUES (?,?,?,?,?,?,?)');
    $agora = date('Y-m-d H:i:s');
    foreach ($novos as $m) {
        $st->execute([(int) $m['produto_id'], mb_substr($m['produto'], 0, 100), $m['tipo'], (int) $m['qtd'],
                      (int) $m['saldo'], mb_substr($m['motivo'], 0, 150), $agora]);
    }
}

function listarMovimentos(int $limite = 20): array
{
    $limite = max(1, min(500, $limite));
    return db()->query("SELECT produto_nome AS produto, tipo, qtd, saldo, motivo,
                               DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS data
                        FROM movimentos ORDER BY id DESC LIMIT $limite")->fetchAll();
}

/* ==================================================================
 * Estoque reservado e disponível
 *   (unidades de pedidos com status 0, 1 e 2 — veja a view vw_reservado)
 * ================================================================== */
function reservadoMapa(): array
{
    static $mapa = null;
    if ($mapa === null) {
        $mapa = [];
        foreach (db()->query('SELECT produto_id, reservado FROM vw_reservado')->fetchAll() as $r) {
            $mapa[(int) $r['produto_id']] = (int) $r['reservado'];
        }
    }
    return $mapa;
}

function disponivel(array $produto): int
{
    return max(0, $produto['estoque'] - (reservadoMapa()[$produto['id']] ?? 0));
}

/* ==================================================================
 * Pedidos
 *   status: 0 recebido · 1 em separação · 2 saiu para entrega · 3 entregue · 4 cancelado
 *   Número do pedido = pedidos.id
 * ================================================================== */
function totalPedido(array $pedido): float
{
    return (float) $pedido['total'];
}

function contarPedidosAbertos(int $usuarioId): int
{
    $st = db()->prepare('SELECT COUNT(*) FROM pedidos WHERE usuario_id = ? AND status < 3');
    $st->execute([$usuarioId]);
    return (int) $st->fetchColumn();
}

/**
 * Pedidos (com itens) em uma única consulta, mais novos primeiro.
 * $usuarioId = null → de todos os clientes (painel do administrador).
 */
function carregarPedidos(?int $usuarioId = null): array
{
    $sql = "SELECT p.id, p.status, p.total, p.desconto_pct, p.pagamento_id, p.pagamento_nome,
                   DATE_FORMAT(p.criado_em, '%d/%m/%Y') AS data,
                   u.nome AS cliente_nome, u.email AS cliente_email,
                   i.produto_id AS item_produto, i.nome AS item_nome, i.qtd AS item_qtd, i.preco AS item_preco
            FROM pedidos p
            INNER JOIN usuarios u ON u.id = p.usuario_id
            INNER JOIN pedido_itens i ON i.pedido_id = p.id"
         . ($usuarioId !== null ? ' WHERE p.usuario_id = ?' : '')
         . ' ORDER BY p.id DESC, i.id';
    $st = db()->prepare($sql);
    $st->execute($usuarioId !== null ? [$usuarioId] : []);

    $pedidos = [];
    foreach ($st as $r) {
        $n = (int) $r['id'];
        if (!isset($pedidos[$n])) {
            $pedidos[$n] = [
                'numero'        => $n,
                'data'          => $r['data'],
                'status'        => (int) $r['status'],
                'total'         => (float) $r['total'],
                'pagamento'     => $r['pagamento_nome'] !== null
                    ? ['id' => (int) $r['pagamento_id'], 'nome' => $r['pagamento_nome'], 'desconto' => (float) $r['desconto_pct']]
                    : null,
                'cliente_nome'  => $r['cliente_nome'],
                'cliente_email' => $r['cliente_email'],
                'itens'         => [],
            ];
        }
        $pedidos[$n]['itens'][] = [
            'id'    => (int) $r['item_produto'],
            'nome'  => $r['item_nome'],
            'qtd'   => (int) $r['item_qtd'],
            'preco' => (float) $r['item_preco'],
        ];
    }
    return array_values($pedidos);
}

/** Todos os pedidos de todos os clientes (painel do administrador), mais novos primeiro. */
function listarPedidos(): array
{
    return carregarPedidos(null);
}

/**
 * Cria o pedido com segurança contra vendas simultâneas: trava as linhas dos
 * produtos (FOR UPDATE), confere o disponível (estoque − reservas) e grava tudo
 * em uma transação.
 *
 * @param array $carrinho  [produto_id => qtd]
 * @return array ['ok' => bool, 'numero' => int, 'removidos' => int, 'erro' => string]
 */
function criarPedido(int $usuarioId, array $pagamento, array $carrinho): array
{
    $ids = array_values(array_unique(array_map('intval', array_keys($carrinho))));
    sort($ids);   // ordem fixa evita deadlock entre pedidos simultâneos
    if (!$ids) {
        return ['ok' => false, 'erro' => 'Seu carrinho está vazio.'];
    }

    return transacao(function (PDO $pdo) use ($usuarioId, $pagamento, $carrinho, $ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));

        $st = $pdo->prepare("SELECT id, nome, preco, estoque, ativo FROM produtos WHERE id IN ($in) ORDER BY id FOR UPDATE");
        $st->execute($ids);
        $prods = [];
        foreach ($st as $p) {
            $prods[(int) $p['id']] = $p;
        }

        // Lido depois do bloqueio: enxerga todos os pedidos já confirmados desses produtos
        $st = $pdo->prepare("SELECT produto_id, reservado FROM vw_reservado WHERE produto_id IN ($in)");
        $st->execute($ids);
        $reservas = [];
        foreach ($st as $r) {
            $reservas[(int) $r['produto_id']] = (int) $r['reservado'];
        }

        $itens = [];
        $removidos = 0;
        $subtotal = 0.0;
        foreach ($carrinho as $pid => $qtd) {
            $pid = (int) $pid;
            $qtd = (int) $qtd;
            $p   = $prods[$pid] ?? null;
            $disp = $p ? max(0, (int) $p['estoque'] - ($reservas[$pid] ?? 0)) : 0;
            if (!$p || !$p['ativo'] || $qtd < 1 || $qtd > $disp) {
                $removidos++;
                continue;
            }
            $itens[] = ['id' => $pid, 'nome' => $p['nome'], 'qtd' => $qtd, 'preco' => (float) $p['preco']];
            $subtotal += $qtd * (float) $p['preco'];
        }
        if (!$itens) {
            return ['ok' => false, 'erro' => 'Os itens do carrinho não estão mais disponíveis.'];
        }

        $agora = date('Y-m-d H:i:s');
        $total = round($subtotal * (1 - $pagamento['desconto'] / 100), 2);
        $pdo->prepare('INSERT INTO pedidos (usuario_id, status, subtotal, desconto_pct, total, pagamento_id, pagamento_nome, criado_em, atualizado_em)
                       VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$usuarioId, 0, round($subtotal, 2), $pagamento['desconto'], $total, $pagamento['id'], $pagamento['nome'], $agora, $agora]);
        $numero = (int) $pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, nome, qtd, preco) VALUES (?,?,?,?,?)');
        foreach ($itens as $i) {
            $ins->execute([$numero, $i['id'], $i['nome'], $i['qtd'], $i['preco']]);
        }
        return ['ok' => true, 'numero' => $numero, 'removidos' => $removidos];
    });
}

/**
 * Pedidos de exemplo para contas novas (MODO_DEMO). Os pedidos entregues não mexem
 * no estoque; o único em andamento reserva poucas unidades.
 */
function criarPedidosDemo(int $usuarioId, array $mapa): void
{
    $modelos = [   // do mais antigo para o mais novo (a numeração acompanha a data)
        ['-45 days', 3, [[6, 1], [5, 1]]],
        ['-18 days', 3, [[3, 2], [4, 1]]],
        ['-2 days',  2, [[1, 2], [2, 1]]],
    ];
    $pdo = db();
    $insP = $pdo->prepare('INSERT INTO pedidos (usuario_id, status, subtotal, desconto_pct, total, criado_em, atualizado_em) VALUES (?,?,?,?,?,?,?)');
    $insI = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, nome, qtd, preco) VALUES (?,?,?,?,?)');

    foreach ($modelos as [$quando, $status, $pares]) {
        $itens = [];
        foreach ($pares as [$pid, $qtd]) {
            if (isset($mapa[$pid])) {
                $itens[] = [$pid, $mapa[$pid]['nome'], $qtd, $mapa[$pid]['preco']];
            }
        }
        if (!$itens) {
            continue;
        }
        $bruto = 0.0;
        foreach ($itens as $i) {
            $bruto += $i[2] * $i[3];
        }
        $data = date('Y-m-d H:i:s', strtotime($quando));
        $insP->execute([$usuarioId, $status, round($bruto, 2), 0, round($bruto, 2), $data, $data]);
        $numero = (int) $pdo->lastInsertId();
        foreach ($itens as $i) {
            $insI->execute([$numero, $i[0], $i[1], $i[2], $i[3]]);
        }
    }
}

/**
 * Cancela ou avança o pedido (admin). Ao chegar em "Entregue" dá baixa definitiva
 * no estoque e registra a movimentação. Retorna ['ok'|'erro', mensagem].
 */
function alterarStatusPedido(int $numero, string $op): array
{
    return transacao(function (PDO $pdo) use ($numero, $op) {
        $st = $pdo->prepare('SELECT id, status FROM pedidos WHERE id = ? FOR UPDATE');
        $st->execute([$numero]);
        $ped = $st->fetch();
        if (!$ped) {
            return ['erro', 'Pedido não encontrado.'];
        }
        $status = (int) $ped['status'];
        if ($status >= 3) {
            return ['erro', 'Este pedido já foi encerrado e não pode ser alterado.'];
        }
        $agora = date('Y-m-d H:i:s');
        $upd = $pdo->prepare('UPDATE pedidos SET status = ?, atualizado_em = ? WHERE id = ?');

        if ($op === 'cancelar') {
            $upd->execute([4, $agora, $numero]);
            return ['ok', 'Pedido #' . $numero . ' cancelado. As unidades reservadas foram liberadas.'];
        }

        $novo = $status + 1;
        if ($novo === 3) {
            $st = $pdo->prepare('SELECT produto_id, qtd FROM pedido_itens WHERE pedido_id = ? AND produto_id IS NOT NULL ORDER BY produto_id');
            $st->execute([$numero]);
            $itens = $st->fetchAll();

            $selP = $pdo->prepare('SELECT id, nome, estoque FROM produtos WHERE id = ? FOR UPDATE');
            $updP = $pdo->prepare('UPDATE produtos SET estoque = ?, atualizado_em = ? WHERE id = ?');
            $movs = [];
            foreach ($itens as $item) {
                $selP->execute([(int) $item['produto_id']]);
                $p = $selP->fetch();
                if (!$p) {
                    continue;
                }
                $baixa = min((int) $item['qtd'], (int) $p['estoque']);
                $saldo = (int) $p['estoque'] - $baixa;
                $updP->execute([$saldo, $agora, (int) $p['id']]);
                $movs[] = movimento(['id' => (int) $p['id'], 'nome' => $p['nome']], 'Venda', -$baixa, $saldo, 'Pedido #' . $numero);
            }
            registrarMovimentos($movs);
        }
        $upd->execute([$novo, $agora, $numero]);
        return ['ok', 'Pedido #' . $numero . ' atualizado para "' . ETAPAS[$novo] . '".'];
    });
}

/* ==================================================================
 * Reposição de mercadoria
 * ================================================================== */
function listarReposicoes(): array
{
    $linhas = db()->query("SELECT id, produto_id, produto_nome, qtd, fornecedor, custo, status,
                                  DATE_FORMAT(criado_em, '%d/%m/%Y') AS data,
                                  DATE_FORMAT(previsao, '%d/%m/%Y')  AS previsao
                           FROM reposicoes ORDER BY id DESC")->fetchAll();
    return array_map(fn($r) => [
        'id'         => (int) $r['id'],
        'data'       => $r['data'],
        'produto_id' => (int) $r['produto_id'],
        'produto'    => $r['produto_nome'],
        'qtd'        => (int) $r['qtd'],
        'fornecedor' => $r['fornecedor'],
        'custo'      => (float) $r['custo'],
        'previsao'   => (string) $r['previsao'],
        'status'     => $r['status'],
    ], $linhas);
}

/** $previsao no formato aaaa-mm-dd ou ''. Retorna o número da reposição. */
function criarReposicao(array $produto, int $qtd, string $fornecedor, float $custo, string $previsao): int
{
    db()->prepare('INSERT INTO reposicoes (produto_id, produto_nome, qtd, fornecedor, custo, previsao, status, criado_em)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$produto['id'], $produto['nome'], $qtd, $fornecedor, $custo, $previsao !== '' ? $previsao : null,
                   'Solicitada', date('Y-m-d H:i:s')]);
    return (int) db()->lastInsertId();
}

/** Recebe ou cancela uma reposição pendente. Retorna ['ok'|'erro', mensagem]. */
function encerrarReposicao(int $id, bool $receber): array
{
    return transacao(function (PDO $pdo) use ($id, $receber) {
        $st = $pdo->prepare("SELECT * FROM reposicoes WHERE id = ? AND status = 'Solicitada' FOR UPDATE");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            return ['erro', 'Reposição não encontrada ou já encerrada.'];
        }
        $agora = date('Y-m-d H:i:s');

        if (!$receber) {
            $pdo->prepare("UPDATE reposicoes SET status = 'Cancelada' WHERE id = ?")->execute([$id]);
            return ['ok', 'Reposição #' . $id . ' cancelada.'];
        }

        $st = $pdo->prepare('SELECT id, nome, estoque FROM produtos WHERE id = ? FOR UPDATE');
        $st->execute([(int) $r['produto_id']]);
        $p = $st->fetch();
        if (!$p) {
            return ['erro', 'O produto desta reposição foi excluído. Cancele a reposição.'];
        }
        $saldo = (int) $p['estoque'] + (int) $r['qtd'];
        $pdo->prepare('UPDATE produtos SET estoque = ?, atualizado_em = ? WHERE id = ?')->execute([$saldo, $agora, (int) $p['id']]);
        registrarMovimentos([movimento(['id' => (int) $p['id'], 'nome' => $p['nome']], 'Reposição', (int) $r['qtd'], $saldo,
                                       'Reposição #' . $id . ' — ' . $r['fornecedor'])]);
        $pdo->prepare("UPDATE reposicoes SET status = 'Recebida', recebida_em = ? WHERE id = ?")->execute([$agora, $id]);
        return ['ok', 'Mercadoria recebida: +' . $r['qtd'] . ' un. de "' . $r['produto_nome'] . '" no estoque.'];
    });
}

/* ==================================================================
 * Mensagens do formulário de contato
 * ================================================================== */
function salvarMensagem(string $nome, string $email, string $telefone, string $assunto, string $texto): void
{
    db()->prepare('INSERT INTO mensagens (nome, email, telefone, assunto, mensagem, lida, criado_em) VALUES (?,?,?,?,?,0,?)')
        ->execute([$nome, $email, $telefone, $assunto, $texto, date('Y-m-d H:i:s')]);
}

function listarMensagens(int $limite = 300): array
{
    $limite = max(1, min(1000, $limite));
    return db()->query("SELECT id, nome, email, telefone, assunto, mensagem, lida,
                               DATE_FORMAT(criado_em, '%d/%m/%Y %H:%i') AS data
                        FROM mensagens ORDER BY id DESC LIMIT $limite")->fetchAll();
}

function marcarMensagensLidas(): void
{
    db()->exec('UPDATE mensagens SET lida = 1 WHERE lida = 0');
}