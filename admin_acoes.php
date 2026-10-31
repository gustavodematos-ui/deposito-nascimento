<?php
/**
 * Depósito Nascimento — ações do painel do administrador (POST).
 * Incluído pelo index.php; todas as ações começam com "adm_".
 */
defined('DEPOSITO') or exit;

if (!$ehAdmin) {
    aviso('erro', 'Acesso restrito ao administrador.');
    irPara('catalogo');
}

switch ($acao) {

    /* ------------------- PRODUTOS ------------------- */
    case 'adm_produto_salvar':
        $idEdit  = (int) ($_POST['id'] ?? 0);
        $nome    = trim((string) ($_POST['nome'] ?? ''));
        $cat     = trim((string) ($_POST['categoria'] ?? ''));
        $desc    = trim((string) ($_POST['descricao'] ?? ''));
        $precoIn = str_replace(',', '.', trim((string) ($_POST['preco'] ?? '')));
        $estoque = filter_var($_POST['estoque'] ?? '', FILTER_VALIDATE_INT);
        $minimo  = filter_var($_POST['minimo'] ?? '', FILTER_VALIDATE_INT);
        $ativo   = !empty($_POST['ativo']);
        $old     = ['nome' => $nome, 'categoria' => $cat, 'descricao' => $desc, 'preco' => $precoIn,
                    'estoque' => $estoque === false ? '' : $estoque, 'minimo' => $minimo === false ? '' : $minimo, 'ativo' => $ativo];
        $destino = $idEdit ? ['editar' => $idEdit] : ['novo' => 1];

        $erro = null;
        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100)        $erro = 'Informe o nome do produto (2 a 100 caracteres).';
        elseif (mb_strlen($cat) < 2 || mb_strlen($cat) > 60)       $erro = 'Informe a categoria (2 a 60 caracteres).';
        elseif (mb_strlen($desc) > 300)                            $erro = 'A descrição pode ter no máximo 300 caracteres.';
        elseif (!is_numeric($precoIn) || $precoIn < 0 || $precoIn > 9999999) $erro = 'Informe um preço válido.';
        elseif ($estoque === false || $estoque < 0 || $estoque > 1000000)    $erro = 'O estoque deve ser um número inteiro, zero ou maior.';
        elseif ($minimo === false || $minimo < 0 || $minimo > 1000000)       $erro = 'O estoque mínimo deve ser um número inteiro, zero ou maior.';
        elseif ($idEdit && !isset($mapa[$idEdit]))                           $erro = 'Produto não encontrado.';

        $novaImagem = null;
        if (!$erro) {
            $novaImagem = salvarImagem($_FILES['imagem'] ?? null, $erro);
        }
        if ($erro) {
            aviso('erro', $erro, null, $old);
            irPainel('produtos', $destino);
        }

        $preco = round((float) $precoIn, 2);

        try {
            $resultado = transacao(function (PDO $pdo) use ($idEdit, $nome, $cat, $desc, $preco, $estoque, $minimo, $ativo, $novaImagem, $mapa) {
                if ($idEdit) {
                    // Estoque atual lido com bloqueio: não sobrescreve vendas/recebimentos simultâneos sem registrar
                    $st = $pdo->prepare('SELECT estoque FROM produtos WHERE id = ? FOR UPDATE');
                    $st->execute([$idEdit]);
                    $estoqueAtual = $st->fetchColumn();
                    if ($estoqueAtual === false) {
                        return ['erro' => 'Produto não encontrado.'];
                    }
                    $antigo  = $mapa[$idEdit];
                    $produto = [
                        'id' => $idEdit, 'nome' => $nome, 'categoria' => $cat, 'descricao' => $desc, 'preco' => $preco,
                        'estoque' => $estoque, 'minimo' => $minimo, 'ativo' => $ativo,
                        'imagem' => $novaImagem ?: $antigo['imagem'],
                    ];
                    atualizarProduto($produto);
                    if ($estoque !== (int) $estoqueAtual) {
                        registrarMovimentos([movimento($produto, 'Ajuste', $estoque - (int) $estoqueAtual, $estoque, 'Edição do produto')]);
                    }
                    return ['msg' => 'Produto "' . $nome . '" atualizado.', 'imagemAntiga' => $novaImagem ? $antigo['imagem'] : ''];
                }

                $novo = ['categoria' => $cat, 'nome' => $nome, 'descricao' => $desc, 'estoque' => $estoque,
                         'minimo' => $minimo, 'preco' => $preco, 'imagem' => $novaImagem ?: '', 'ativo' => $ativo];
                $novo['id'] = inserirProduto($novo);
                if ($estoque > 0) {
                    registrarMovimentos([movimento($novo, 'Entrada inicial', $estoque, $estoque, 'Cadastro do produto')]);
                }
                return ['msg' => 'Produto "' . $nome . '" adicionado ao catálogo.', 'imagemAntiga' => ''];
            });
        } catch (Throwable $e) {
            if ($novaImagem) {
                removerImagem($novaImagem);   // não deixa arquivo órfão se o banco falhar
            }
            throw $e;
        }

        if (isset($resultado['erro'])) {
            if ($novaImagem) {
                removerImagem($novaImagem);
            }
            aviso('erro', $resultado['erro'], null, $old);
            irPainel('produtos', $destino);
        }
        if ($resultado['imagemAntiga'] !== '') {
            removerImagem($resultado['imagemAntiga']);
        }
        aviso('ok', $resultado['msg']);
        irPainel('produtos');

    case 'adm_produto_excluir':
        $idDel = (int) ($_POST['id'] ?? 0);
        $prod  = excluirProduto($idDel);
        if ($prod) {
            removerImagem((string) $prod['imagem']);
            aviso('ok', 'Produto "' . $prod['nome'] . '" excluído. Pedidos antigos continuam com o histórico dele.');
        } else {
            aviso('erro', 'Produto não encontrado.');
        }
        irPainel('produtos');

    /* ------------------- ESTOQUE ------------------- */
    case 'adm_estoque_ajustar':
        $idAj   = (int) ($_POST['id'] ?? 0);
        $delta  = filter_var($_POST['delta'] ?? '', FILTER_VALIDATE_INT);
        $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 120) ?: 'Ajuste manual';

        if ($delta === false || $delta === 0 || abs($delta) > 1000000) {
            aviso('erro', 'Informe uma quantidade inteira diferente de zero (use negativo para saída).');
            irPainel('estoque');
        }

        $res = transacao(function (PDO $pdo) use ($idAj, $delta, $motivo) {
            $st = $pdo->prepare('SELECT id, nome, estoque FROM produtos WHERE id = ? FOR UPDATE');
            $st->execute([$idAj]);
            $p = $st->fetch();
            if (!$p) {
                return ['erro', 'Produto não encontrado.'];
            }
            $saldo = (int) $p['estoque'] + $delta;
            if ($saldo < 0) {
                return ['erro', 'A saída é maior que o estoque atual (' . (int) $p['estoque'] . ').'];
            }
            $pdo->prepare('UPDATE produtos SET estoque = ?, atualizado_em = ? WHERE id = ?')
                ->execute([$saldo, date('Y-m-d H:i:s'), $idAj]);
            registrarMovimentos([movimento(['id' => (int) $p['id'], 'nome' => $p['nome']], 'Ajuste', $delta, $saldo, $motivo)]);
            return ['ok', 'Estoque ajustado.'];
        });
        aviso($res[0], $res[1]);
        irPainel('estoque');

    case 'adm_estoque_minimo':
        $idMin  = (int) ($_POST['id'] ?? 0);
        $minimo = filter_var($_POST['minimo'] ?? '', FILTER_VALIDATE_INT);
        if ($minimo === false || $minimo < 0 || $minimo > 1000000) {
            aviso('erro', 'Estoque mínimo inválido.');
            irPainel('estoque');
        }
        if (isset($mapa[$idMin])) {
            db()->prepare('UPDATE produtos SET minimo = ?, atualizado_em = ? WHERE id = ?')
                ->execute([$minimo, date('Y-m-d H:i:s'), $idMin]);
            aviso('ok', 'Estoque mínimo atualizado.');
        } else {
            aviso('erro', 'Produto não encontrado.');
        }
        irPainel('estoque');

    /* ------------------- PEDIDOS ------------------- */
    case 'adm_pedido_status':
        $numero = (int) ($_POST['numero'] ?? 0);
        $op     = (string) ($_POST['op'] ?? '');
        $aba    = in_array($_POST['aba'] ?? '', ['andamento', 'vendas', 'cancelados'], true) ? $_POST['aba'] : 'andamento';
        $res    = alterarStatusPedido($numero, $op);
        aviso($res[0], $res[1]);
        irPainel('pedidos', ['aba' => $aba]);

    /* ------------------- FORMAS DE PAGAMENTO ------------------- */
    case 'adm_pagamento_salvar':
        $idPg     = (int) ($_POST['id'] ?? 0);
        $nome     = trim((string) ($_POST['nome'] ?? ''));
        $desc     = trim((string) ($_POST['descricao'] ?? ''));
        $descIn   = str_replace(',', '.', trim((string) ($_POST['desconto'] ?? '0')));
        $parcelas = filter_var($_POST['parcelas'] ?? 1, FILTER_VALIDATE_INT);
        $ativo    = !empty($_POST['ativo']);

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 40) {
            aviso('erro', 'Informe o nome da forma de pagamento (2 a 40 caracteres).');
        } elseif (mb_strlen($desc) > 120) {
            aviso('erro', 'A descrição pode ter no máximo 120 caracteres.');
        } elseif (!is_numeric($descIn) || $descIn < 0 || $descIn > 50) {
            aviso('erro', 'O desconto deve estar entre 0% e 50%.');
        } elseif ($parcelas === false || $parcelas < 1 || $parcelas > 12) {
            aviso('erro', 'O número de parcelas deve estar entre 1 e 12.');
        } else {
            $achou = salvarPagamento($idPg, [
                'nome' => $nome, 'descricao' => $desc, 'desconto' => round((float) $descIn, 2),
                'parcelas' => $parcelas, 'ativo' => $ativo,
            ]);
            aviso('ok', $achou ? 'Forma de pagamento atualizada.' : 'Forma de pagamento adicionada.');
        }
        irPainel('pagamentos');

    case 'adm_pagamento_excluir':
        excluirPagamento((int) ($_POST['id'] ?? 0));
        aviso('ok', 'Forma de pagamento removida.');
        irPainel('pagamentos');

    /* ------------------- REPOSIÇÃO ------------------- */
    case 'adm_reposicao_criar':
        $idProd     = (int) ($_POST['produto_id'] ?? 0);
        $qtd        = filter_var($_POST['qtd'] ?? '', FILTER_VALIDATE_INT);
        $fornecedor = trim((string) ($_POST['fornecedor'] ?? ''));
        $custoIn    = str_replace(',', '.', trim((string) ($_POST['custo'] ?? '0')));
        $previsao   = trim((string) ($_POST['previsao'] ?? ''));

        if (!isset($mapa[$idProd])) {
            aviso('erro', 'Escolha o produto a repor.');
        } elseif ($qtd === false || $qtd < 1 || $qtd > 1000000) {
            aviso('erro', 'Informe uma quantidade inteira maior que zero.');
        } elseif (mb_strlen($fornecedor) < 2 || mb_strlen($fornecedor) > 100) {
            aviso('erro', 'Informe o fornecedor.');
        } elseif (!is_numeric($custoIn) || $custoIn < 0 || $custoIn > 9999999) {
            aviso('erro', 'Informe um custo unitário válido.');
        } elseif ($previsao !== '' && !(DateTime::createFromFormat('Y-m-d', $previsao) && DateTime::createFromFormat('Y-m-d', $previsao)->format('Y-m-d') === $previsao)) {
            aviso('erro', 'Data de previsão inválida.');
        } else {
            $novoId = criarReposicao($mapa[$idProd], $qtd, $fornecedor, round((float) $custoIn, 2), $previsao);
            aviso('ok', 'Reposição #' . $novoId . ' registrada.');
        }
        irPainel('reposicao');

    case 'adm_reposicao_receber':
    case 'adm_reposicao_cancelar':
        $res = encerrarReposicao((int) ($_POST['id'] ?? 0), $acao === 'adm_reposicao_receber');
        aviso($res[0], $res[1]);
        irPainel('reposicao');
}

aviso('erro', 'Ação desconhecida.');
irPainel('produtos');
