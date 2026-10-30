<?php
/**
 * Depósito Nascimento — conexão com o banco de dados (PDO).
 *
 * Suporta SQLite (padrão, sem instalação) e MySQL/MariaDB. Na primeira execução:
 *   1) cria as tabelas a partir de banco/esquema_<driver>.sql;
 *   2) importa os arquivos JSON antigos da pasta "dados" (se existirem) ou
 *      cadastra os produtos e formas de pagamento padrão.
 *
 * Não acesse diretamente: é incluído pelo funcoes.php.
 */
defined('DIR_DADOS') or exit;

/** Cria a pasta "dados" protegida contra acesso direto pelo navegador (Apache). */
function garantirPastaDados(): void
{
    if (!is_dir(DIR_DADOS)) {
        @mkdir(DIR_DADOS, 0750, true);
    }
    if (is_dir(DIR_DADOS)) {
        if (!is_file(DIR_DADOS . '/.htaccess')) {
            @file_put_contents(DIR_DADOS . '/.htaccess', "Require all denied\n");
        }
        if (!is_file(DIR_DADOS . '/index.html')) {
            @file_put_contents(DIR_DADOS . '/index.html', '');
        }
    }
}

/** Conexão única com o banco (criada sob demanda). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opcoes = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (DB_DRIVER === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORTA, DB_NOME);
        $pdo = new PDO($dsn, DB_USUARIO, DB_SENHA, $opcoes);
    } else {
        garantirPastaDados();
        $pdo = new PDO('sqlite:' . DB_ARQUIVO, null, null, $opcoes);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }

    prepararBanco($pdo);
    return $pdo;
}

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    if (DB_DRIVER === 'mysql') {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    } else {
        $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    }
    $st->execute([$tabela]);
    return (bool) $st->fetchColumn();
}

/** Executa um arquivo .sql (comentários "--" e instruções separadas por ";"). */
function executarScript(PDO $pdo, string $sql): void
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (explode(';', (string) $sql) as $comando) {
        if (trim($comando) !== '') {
            $pdo->exec($comando);
        }
    }
}

function configLer(string $chave): ?string
{
    $st = db()->prepare('SELECT valor FROM config WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string) $v;
}

function configGravar(string $chave, string $valor): void
{
    $st = db()->prepare('DELETE FROM config WHERE chave = ?');
    $st->execute([$chave]);
    $st = db()->prepare('INSERT INTO config (chave, valor) VALUES (?, ?)');
    $st->execute([$chave, $valor]);
}

/** Cria o esquema e os dados iniciais, se ainda não existirem. */
function prepararBanco(PDO $pdo): void
{
    if (!tabelaExiste($pdo, 'config')) {
        $arquivo = __DIR__ . '/banco/esquema_' . DB_DRIVER . '.sql';
        if (!is_file($arquivo)) {
            throw new RuntimeException('Arquivo de esquema não encontrado: ' . $arquivo);
        }
        executarScript($pdo, (string) file_get_contents($arquivo));
    }
    if (configLer('semeado') === null) {
        importarLegado();
    }
}

/* ==================================================================
 * Importação dos JSON antigos (executa uma única vez)
 * ================================================================== */
function lerJsonLegado(string $arquivo): ?array
{
    $caminho = DIR_DADOS . '/' . $arquivo;
    if (!is_file($caminho)) {
        return null;
    }
    $dados = json_decode((string) file_get_contents($caminho), true);
    return is_array($dados) ? $dados : null;
}

/** "dd/mm/aaaa" (com ou sem hora) → "aaaa-mm-dd hh:mm:ss". */
function dataBrParaSql(?string $data, ?string $padrao = null): string
{
    $padrao = $padrao ?? date('Y-m-d H:i:s');
    $data = trim((string) $data);
    foreach (['d/m/Y H:i' => 'Y-m-d H:i:00', 'd/m/Y' => 'Y-m-d 00:00:00'] as $formato => $saida) {
        $dt = DateTime::createFromFormat('!' . $formato, $data);
        if ($dt && $dt->format($formato) === $data) {
            return $dt->format($saida);
        }
    }
    return $padrao;
}

function importarLegado(): void
{
    transacao(function (PDO $pdo) {
        $agora = date('Y-m-d H:i:s');

        // Produtos
        $produtos = lerJsonLegado('produtos.json') ?? produtosPadrao();
        $ins = $pdo->prepare('INSERT INTO produtos (id, categoria, nome, descricao, estoque, minimo, preco, imagem, ativo, criado_em, atualizado_em)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($produtos as $p) {
            $ins->execute([
                (int) $p['id'], (string) $p['categoria'], (string) $p['nome'], (string) ($p['descricao'] ?? ''),
                max(0, (int) $p['estoque']), max(0, (int) ($p['minimo'] ?? 10)), round((float) $p['preco'], 2),
                (string) ($p['imagem'] ?? ''), !empty($p['ativo']) || !array_key_exists('ativo', $p) ? 1 : 0, $agora, $agora,
            ]);
        }

        // Formas de pagamento
        $pagamentos = lerJsonLegado('pagamentos.json') ?? pagamentosPadrao();
        $ins = $pdo->prepare('INSERT INTO formas_pagamento (id, nome, descricao, desconto, parcelas, ativo) VALUES (?,?,?,?,?,?)');
        foreach ($pagamentos as $p) {
            $ins->execute([
                (int) $p['id'], (string) $p['nome'], (string) ($p['descricao'] ?? ''),
                min(50, max(0, (float) ($p['desconto'] ?? 0))), min(12, max(1, (int) ($p['parcelas'] ?? 1))),
                !empty($p['ativo']) || !array_key_exists('ativo', $p) ? 1 : 0,
            ]);
        }

        // Clientes e pedidos (usuarios.json: e-mail => {nome, hash, criado, pedidos[]})
        $usuarios = lerJsonLegado('usuarios.json') ?? [];
        $insU = $pdo->prepare('INSERT INTO usuarios (nome, email, hash, criado_em) VALUES (?,?,?,?)');
        $insP = $pdo->prepare('INSERT INTO pedidos (id, usuario_id, status, subtotal, desconto_pct, total, pagamento_id, pagamento_nome, criado_em, atualizado_em)
                               VALUES (?,?,?,?,?,?,?,?,?,?)');
        $insI = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, nome, qtd, preco) VALUES (?,?,?,?,?)');
        $idsProdutos = array_flip(array_map('intval', array_column($produtos, 'id')));
        $idsPagto    = array_flip(array_map('intval', array_column($pagamentos, 'id')));

        foreach ($usuarios as $email => $u) {
            $criado = strtotime((string) ($u['criado'] ?? '')) ?: time();
            $insU->execute([(string) $u['nome'], strtolower((string) $email), (string) $u['hash'], date('Y-m-d H:i:s', $criado)]);
            $uid = (int) $pdo->lastInsertId();

            foreach ($u['pedidos'] ?? [] as $ped) {
                $bruto = 0;
                foreach ($ped['itens'] as $i) {
                    $bruto += $i['qtd'] * $i['preco'];
                }
                $pag  = $ped['pagamento'] ?? null;
                $quando = dataBrParaSql($ped['data'] ?? null);
                $insP->execute([
                    (int) $ped['numero'], $uid, (int) $ped['status'], round($bruto, 2),
                    $pag ? (float) $pag['desconto'] : 0,
                    round(isset($ped['total']) ? (float) $ped['total'] : $bruto, 2),
                    ($pag && isset($idsPagto[(int) $pag['id']])) ? (int) $pag['id'] : null,
                    $pag ? (string) $pag['nome'] : null,
                    $quando, $quando,
                ]);
                foreach ($ped['itens'] as $i) {
                    $insI->execute([
                        (int) $ped['numero'], isset($idsProdutos[(int) $i['id']]) ? (int) $i['id'] : null,
                        (string) $i['nome'], (int) $i['qtd'], round((float) $i['preco'], 2),
                    ]);
                }
            }
        }

        // Movimentações (o JSON guardava as mais novas primeiro → insere da mais antiga para a mais nova)
        $insM = $pdo->prepare('INSERT INTO movimentos (produto_id, produto_nome, tipo, qtd, saldo, motivo, criado_em) VALUES (?,?,?,?,?,?,?)');
        foreach (array_reverse(lerJsonLegado('movimentos.json') ?? []) as $m) {
            $insM->execute([
                isset($idsProdutos[(int) $m['produto_id']]) ? (int) $m['produto_id'] : null,
                (string) $m['produto'], (string) $m['tipo'], (int) $m['qtd'], (int) $m['saldo'],
                (string) ($m['motivo'] ?? ''), dataBrParaSql($m['data'] ?? null),
            ]);
        }

        // Reposições
        $insR = $pdo->prepare('INSERT INTO reposicoes (id, produto_id, produto_nome, qtd, fornecedor, custo, previsao, status, criado_em, recebida_em)
                               VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach (array_reverse(lerJsonLegado('reposicoes.json') ?? []) as $r) {
            $prev = !empty($r['previsao']) ? substr(dataBrParaSql($r['previsao']), 0, 10) : null;
            $insR->execute([
                (int) $r['id'], isset($idsProdutos[(int) $r['produto_id']]) ? (int) $r['produto_id'] : null,
                (string) $r['produto'], (int) $r['qtd'], (string) $r['fornecedor'], round((float) ($r['custo'] ?? 0), 2),
                $prev, (string) $r['status'], dataBrParaSql($r['data'] ?? null),
                !empty($r['recebida_em']) ? dataBrParaSql($r['recebida_em']) : null,
            ]);
        }

        // Mensagens do formulário de contato
        $insG = $pdo->prepare('INSERT INTO mensagens (nome, email, telefone, assunto, mensagem, lida, criado_em) VALUES (?,?,?,?,?,1,?)');
        foreach (array_reverse(lerJsonLegado('mensagens.json') ?? []) as $m) {
            $insG->execute([
                (string) $m['nome'], (string) $m['email'], (string) ($m['telefone'] ?? ''), (string) $m['assunto'],
                (string) $m['mensagem'], dataBrParaSql($m['data'] ?? null),
            ]);
        }

        configGravar('semeado', $agora);
    });
}