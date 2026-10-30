<?php
/**
 * Depósito Nascimento — funções compartilhadas (loja + painel do administrador)
 * Não acesse diretamente: é incluído pelo catalogo.php.
 */
defined('DIR_DADOS') or exit;

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
 * Armazenamento em JSON
 * ================================================================== */
function lerJson(string $arquivo): array
{
    $caminho = DIR_DADOS . '/' . $arquivo;
    if (!is_file($caminho)) {
        return [];
    }
    $dados = json_decode((string) file_get_contents($caminho), true);
    return is_array($dados) ? $dados : [];
}

function gravarJson(string $arquivo, array $dados): bool
{
    if (!is_dir(DIR_DADOS)) {
        @mkdir(DIR_DADOS, 0750, true);
        @file_put_contents(DIR_DADOS . '/.htaccess', "Require all denied\n");
        @file_put_contents(DIR_DADOS . '/index.html', '');
    }
    return file_put_contents(
        DIR_DADOS . '/' . $arquivo,
        json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    ) !== false;
}

/* ==================================================================
 * Proteção contra tentativas de login em excesso
 * ================================================================== */
function acessoBloqueado(): bool
{
    return ($_SESSION['tentativas']['ate'] ?? 0) > time();
}

function registrarFalha(): void
{
    $t = $_SESSION['tentativas'] ?? ['n' => 0, 'ate' => 0];
    $t['n']++;
    if ($t['n'] >= 5) {
        $t = ['n' => 0, 'ate' => time() + 300];
    }
    $_SESSION['tentativas'] = $t;
}

/* ==================================================================
 * Produtos (produtos.json — criado na primeira execução)
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

function carregarProdutos(): array
{
    if (!is_file(DIR_DADOS . '/produtos.json')) {
        $lista = produtosPadrao();
        gravarJson('produtos.json', $lista);
    } else {
        $lista = lerJson('produtos.json');
    }
    foreach ($lista as &$p) {
        $p['id']      = (int) $p['id'];
        $p['estoque'] = (int) $p['estoque'];
        $p['minimo']  = (int) ($p['minimo'] ?? 10);
        $p['preco']   = (float) $p['preco'];
        $p['ativo']   = (bool) ($p['ativo'] ?? true);
        $p['imagem']  = (string) ($p['imagem'] ?? '');
    }
    unset($p);
    return array_values($lista);
}

function salvarProdutos(array $lista): bool
{
    return gravarJson('produtos.json', array_values($lista));
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
 * Formas de pagamento (pagamentos.json)
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
    if (!is_file(DIR_DADOS . '/pagamentos.json')) {
        $lista = pagamentosPadrao();
        gravarJson('pagamentos.json', $lista);
    } else {
        $lista = lerJson('pagamentos.json');
    }
    foreach ($lista as &$p) {
        $p['id']       = (int) $p['id'];
        $p['desconto'] = (float) ($p['desconto'] ?? 0);
        $p['parcelas'] = max(1, (int) ($p['parcelas'] ?? 1));
        $p['ativo']    = (bool) ($p['ativo'] ?? true);
    }
    unset($p);
    return array_values($lista);
}

/* ==================================================================
 * Movimentações de estoque (movimentos.json)
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
    $historico = lerJson('movimentos.json');
    foreach (array_reverse($novos) as $m) {
        array_unshift($historico, $m + ['data' => date('d/m/Y H:i')]);
    }
    gravarJson('movimentos.json', array_slice($historico, 0, 300));
}

/* ==================================================================
 * Estoque e pedidos
 *   status: 0 recebido · 1 em separação · 2 saiu para entrega · 3 entregue · 4 cancelado
 * ================================================================== */
function reservadoMapa(): array
{
    static $mapa = null;
    if ($mapa === null) {
        $mapa = [];
        foreach (lerJson('usuarios.json') as $u) {
            foreach ($u['pedidos'] ?? [] as $pedido) {
                if ($pedido['status'] < 3) {
                    foreach ($pedido['itens'] as $i) {
                        $mapa[$i['id']] = ($mapa[$i['id']] ?? 0) + $i['qtd'];
                    }
                }
            }
        }
    }
    return $mapa;
}

function disponivel(array $produto): int
{
    return max(0, $produto['estoque'] - (reservadoMapa()[$produto['id']] ?? 0));
}

function itemPedido(array $mapa, int $id, int $qtd): ?array
{
    return isset($mapa[$id]) ? ['id' => $id, 'nome' => $mapa[$id]['nome'], 'qtd' => $qtd, 'preco' => $mapa[$id]['preco']] : null;
}

function totalItens(array $itens): float
{
    $t = 0;
    foreach ($itens as $i) {
        $t += $i['qtd'] * $i['preco'];
    }
    return $t;
}

/** Total final do pedido (já com desconto da forma de pagamento, quando houver). */
function totalPedido(array $pedido): float
{
    return isset($pedido['total']) ? (float) $pedido['total'] : totalItens($pedido['itens']);
}

function proximoNumeroPedido(array $usuarios): int
{
    $max = 1000;
    foreach ($usuarios as $u) {
        foreach ($u['pedidos'] ?? [] as $p) {
            $max = max($max, (int) $p['numero']);
        }
    }
    return $max + 1;
}

function pedidosDemo(array $mapa, array $usuarios): array
{
    $n = proximoNumeroPedido($usuarios);
    $modelos = [
        [$n + 2, '-2 days',  2, [[1, 10], [2, 4]]],
        [$n + 1, '-18 days', 3, [[3, 2], [4, 1]]],
        [$n,     '-45 days', 3, [[6, 1], [5, 1]]],
    ];
    $pedidos = [];
    foreach ($modelos as [$num, $quando, $status, $pares]) {
        $itens = array_values(array_filter(array_map(fn($p) => itemPedido($mapa, $p[0], $p[1]), $pares)));
        if ($itens) {
            $pedidos[] = ['numero' => $num, 'data' => date('d/m/Y', strtotime($quando)), 'status' => $status, 'itens' => $itens];
        }
    }
    return $pedidos;
}

/** Todos os pedidos de todos os clientes (para o painel do administrador), mais novos primeiro. */
function listarPedidos(array $usuarios): array
{
    $todos = [];
    foreach ($usuarios as $email => $u) {
        foreach ($u['pedidos'] ?? [] as $p) {
            $todos[] = $p + ['cliente_nome' => $u['nome'], 'cliente_email' => $email];
        }
    }
    usort($todos, fn($a, $b) => $b['numero'] <=> $a['numero']);
    return $todos;
}