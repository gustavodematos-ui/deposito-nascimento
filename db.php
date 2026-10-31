<?php
/**
 * Depósito Nascimento — conexão com o MySQL/MariaDB (PDO).
 *
 * Na primeira execução:
 *   1) cria o banco (se não existir) e as tabelas de banco/esquema_mysql.sql;
 *   2) importa os JSON antigos da pasta "dados" (se existirem) ou cadastra
 *      os produtos e formas de pagamento padrão.
 *
 * Não acesse diretamente: é incluído pelo funcoes.php.
 */
defined('DEPOSITO') or exit;

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
    $dsn = function (bool $comBanco): string {
        return sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', DB_HOST, DB_PORTA,
                       $comBanco ? 'dbname=' . DB_NOME . ';' : '');
    };

    try {
        $pdo = new PDO($dsn(true), DB_USUARIO, DB_SENHA, $opcoes);
    } catch (PDOException $e) {
        // 1049 = banco desconhecido → cria o banco e reconecta
        if ((int) $e->getCode() !== 1049 && (int) ($e->errorInfo[1] ?? 0) !== 1049) {
            throw $e;
        }
        $servidor = new PDO($dsn(false), DB_USUARIO, DB_SENHA, $opcoes);
        $servidor->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', DB_NOME)
                      . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO($dsn(true), DB_USUARIO, DB_SENHA, $opcoes);
    }

    prepararBanco($pdo);
    return $pdo;
}

/** Executa $fn dentro de uma transação (commit ao final, rollback se houver exceção). */
function transacao(callable $fn)
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }
    $pdo->beginTransaction();
    try {
        $resultado = $fn($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $st->execute([$tabela]);
    return (bool) $st->fetchColumn();
}

/** Executa um arquivo .sql (linhas de comentário "--" e instruções separadas por ";"). */
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
    db()->prepare('INSERT INTO config (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$chave, $valor]);
}

/** Cria o esquema e os dados iniciais, se ainda não existirem. */
function prepararBanco(PDO $pdo): void
{
    // "config" é a última tabela do esquema: se existe, o esquema foi aplicado por completo.
    if (!tabelaExiste($pdo, 'config')) {
        $arquivo = __DIR__ . '/banco/esquema_mysql.sql';
        if (!is_file($arquivo)) {
            throw new RuntimeException('Arquivo de esquema não encontrado: ' . $arquivo);
        }
        executarScript($pdo, (string) file_get_contents($arquivo));
    }
    if (configLer('semeado') === null) {
        semearBanco();
    }
}

/* ==================================================================
 * Dados iniciais (executa uma única vez)
 * ================================================================== */
function lerJsonLegado(string $arquivo): ?array
{
    $caminho = __DIR__ . '/dados/' . $arquivo;
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

/**
 * Popula o banco: usa os JSON antigos da pasta "dados" (migração) quando
 * existirem; caso contrário, cadastra produtos e pagamentos padrão.
 */
function semearBanco(): void
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
                (string) ($p['imagem'] ?? ''), (!array_key_exists('ativo', $p) || !empty($p['ativo'])) ? 1 : 0, $agora, $agora,
            ]);
        }

        // Formas de pagamento
        $pagamentos = lerJsonLegado('pagamentos.json') ?? pagamentosPadrao();
        $ins = $pdo->prepare('INSERT INTO formas_pagamento (id, nome, descricao, desconto, parcelas, ativo) VALUES (?,?,?,?,?,?)');
        foreach ($pagamentos as $p) {
            $ins->execute([
                (int) $p['id'], (string) $p['nome'], (string) ($p['descricao'] ?? ''),
                min(50, max(0, (float) ($p['desconto'] ?? 0))), min(12, max(1, (int) ($p['parcelas'] ?? 1))),
                (!array_key_exists('ativo', $p) || !empty($p['ativo'])) ? 1 : 0,
            ]);
        }

        $idsProdutos = array_flip(array_map('intval', array_column($produtos, 'id')));
        $idsPagto    = array_flip(array_map('intval', array_column($pagamentos, 'id')));

        // Clientes e pedidos (usuarios.json: e-mail => {nome, hash, criado, pedidos[]})
        $usuarios = lerJsonLegado('usuarios.json') ?? [];
        $insU = $pdo->prepare('INSERT INTO usuarios (nome, email, hash, criado_em) VALUES (?,?,?,?)');
        $insP = $pdo->prepare('INSERT INTO pedidos (id, usuario_id, status, subtotal, desconto_pct, total, pagamento_id, pagamento_nome, criado_em, atualizado_em)
                               VALUES (?,?,?,?,?,?,?,?,?,?)');
        $insI = $pdo->prepare('INSERT INTO pedido_itens (pedido_id, produto_id, nome, qtd, preco) VALUES (?,?,?,?,?)');

        foreach ($usuarios as $email => $u) {
            $criado = strtotime((string) ($u['criado'] ?? '')) ?: time();
            $insU->execute([(string) $u['nome'], strtolower((string) $email), (string) $u['hash'], date('Y-m-d H:i:s', $criado)]);
            $uid = (int) $pdo->lastInsertId();

            foreach ($u['pedidos'] ?? [] as $ped) {
                $bruto = 0;
                foreach ($ped['itens'] as $i) {
                    $bruto += $i['qtd'] * $i['preco'];
                }
                $pag    = $ped['pagamento'] ?? null;
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
                $prev, in_array($r['status'], ['Solicitada', 'Recebida', 'Cancelada'], true) ? $r['status'] : 'Solicitada',
                dataBrParaSql($r['data'] ?? null),
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
