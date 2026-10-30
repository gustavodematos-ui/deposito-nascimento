<?php
/**
 * Depósito Nascimento — ações do painel do administrador (POST).
 * Incluído pelo catalogo.php; todas as ações começam com "adm_".
 */
defined('DIR_DADOS') or exit;

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

        $lista  = carregarProdutos();
        $indice = null;
        foreach ($lista as $k => $p) {
            if ($p['id'] === $idEdit) {
                $indice = $k;
            }
        }
        if (!$erro && $idEdit && $indice === null) {
            $erro = 'Produto não encontrado.';
        }

        $novaImagem = null;
        if (!$erro) {
            $novaImagem = salvarImagem($_FILES['imagem'] ?? null, $erro);
        }
        if ($erro) {
            aviso('erro', $erro, null, $old);
            irPainel('produtos', $destino);
        }

        $preco = round((float) $precoIn, 2);

        if ($idEdit) {
            $antigo = $lista[$indice];
            if ($novaImagem) {
                removerImagem($antigo['imagem']);
            }
            $lista[$indice] = array_merge($antigo, [
                'nome' => $nome, 'categoria' => $cat, 'descricao' => $desc, 'preco' => $preco,
                'estoque' => $estoque, 'minimo' => $minimo, 'ativo' => $ativo,
                'imagem' => $novaImagem ?: $antigo['imagem'],
            ]);
            if ($estoque !== $antigo['estoque']) {
                registrarMovimentos([movimento($lista[$indice], 'Ajuste', $estoque - $antigo['estoque'], $estoque, 'Edição do produto')]);
            }
            $msg = 'Produto "' . $nome . '" atualizado.';
        } else {
            $novoId = 1;
            foreach ($lista as $p) {
                $novoId = max($novoId, $p['id'] + 1);
            }
            $novo = ['id' => $novoId, 'categoria' => $cat, 'nome' => $nome, 'descricao' => $desc, 'estoque' => $estoque,
                     'minimo' => $minimo, 'preco' => $preco, 'imagem' => $novaImagem ?: '', 'ativo' => $ativo];
            $lista[] = $novo;
            if ($estoque > 0) {
                registrarMovimentos([movimento($novo, 'Entrada inicial', $estoque, $estoque, 'Cadastro do produto')]);
            }
            $msg = 'Produto "' . $nome . '" adicionado ao catálogo.';
        }

        if (salvarProdutos($lista)) {
            aviso('ok', $msg);
            irPainel('produtos');
        }
        aviso('erro', 'Não foi possível salvar. Verifique as permissões da pasta "dados".', null, $old);
        irPainel('produtos', $destino);

    case 'adm_produto_excluir':
        $idDel = (int) ($_POST['id'] ?? 0);
        $lista = carregarProdutos();
        $nomeDel = null;
        foreach ($lista as $k => $p) {
            if ($p['id'] === $idDel) {
                $nomeDel = $p['nome'];
                removerImagem($p['imagem']);
                unset($lista[$k]);
            }
        }
        if ($nomeDel !== null && salvarProdutos($lista)) {
            aviso('ok', 'Produto "' . $nomeDel . '" excluído. Pedidos antigos continuam com o histórico dele.');
        } else {
            aviso('erro', 'Produto não encontrado.');
        }
        irPainel('produtos');

    /* ------------------- ESTOQUE ------------------- */
    case 'adm_estoque_ajustar':
        $idAj   = (int) ($_POST['id'] ?? 0);
        $delta  = filter_var($_POST['delta'] ?? '', FILTER_VALIDATE_INT);
        $motivo = mb_substr(trim((string) ($_POST['motivo'] ?? '')), 0, 120) ?: 'Ajuste manual';
        $lista  = carregarProdutos();
        $feito  = false;

        if ($delta === false || $delta === 0 || abs($delta) > 1000000) {
            aviso('erro', 'Informe uma quantidade inteira diferente de zero (use negativo para saída).');
            irPainel('estoque');
        }
        foreach ($lista as $k => $p) {
            if ($p['id'] === $idAj) {
                $saldo = $p['estoque'] + $delta;
                if ($saldo < 0) {
                    aviso('erro', 'A saída é maior que o estoque atual (' . $p['estoque'] . ').');
                    irPainel('estoque');
                }
                $lista[$k]['estoque'] = $saldo;
                registrarMovimentos([movimento($p, 'Ajuste', $delta, $saldo, $motivo)]);
                $feito = true;
            }
        }
        if ($feito && salvarProdutos($lista)) {
            aviso('ok', 'Estoque ajustado.');
        } else {
            aviso('erro', 'Produto não encontrado.');
        }
        irPainel('estoque');

    case 'adm_estoque_minimo':
        $idMin  = (int) ($_POST['id'] ?? 0);
        $minimo = filter_var($_POST['minimo'] ?? '', FILTER_VALIDATE_INT);
        $lista  = carregarProdutos();
        if ($minimo === false || $minimo < 0 || $minimo > 1000000) {
            aviso('erro', 'Estoque mínimo inválido.');
            irPainel('estoque');
        }
        foreach ($lista as $k => $p) {
            if ($p['id'] === $idMin) {
                $lista[$k]['minimo'] = $minimo;
            }
        }
        salvarProdutos($lista);
        aviso('ok', 'Estoque mínimo atualizado.');
        irPainel('estoque');

    /* ------------------- PEDIDOS ------------------- */
    case 'adm_pedido_status':
        $email  = strtolower(trim((string) ($_POST['email'] ?? '')));
        $numero = (int) ($_POST['numero'] ?? 0);
        $op     = $_POST['op'] ?? '';
        $aba    = in_array($_POST['aba'] ?? '', ['andamento', 'vendas', 'cancelados'], true) ? $_POST['aba'] : 'andamento';
        $usuarios = lerJson('usuarios.json');
        $mensagem = ['erro', 'Pedido não encontrado.'];

        foreach ($usuarios[$email]['pedidos'] ?? [] as $i => $ped) {
            if ($ped['numero'] !== $numero) {
                continue;
            }
            if ($ped['status'] >= 3) {
                $mensagem = ['erro', 'Este pedido já foi encerrado e não pode ser alterado.'];
                break;
            }

            if ($op === 'cancelar') {
                $usuarios[$email]['pedidos'][$i]['status'] = 4;
                $mensagem = ['ok', 'Pedido #' . $numero . ' cancelado. As unidades reservadas foram liberadas.'];
            } else {
                $novo = $ped['status'] + 1;
                if ($novo === 3) {
                    // Entrega concluída: baixa definitiva no estoque
                    $lista = carregarProdutos();
                    $movs  = [];
                    foreach ($ped['itens'] as $item) {
                        foreach ($lista as $k => $p) {
                            if ($p['id'] === $item['id']) {
                                $lista[$k]['estoque'] = max(0, $p['estoque'] - $item['qtd']);
                                $movs[] = movimento($p, 'Venda', -$item['qtd'], $lista[$k]['estoque'], 'Pedido #' . $numero);
                            }
                        }
                    }
                    salvarProdutos($lista);
                    registrarMovimentos($movs);
                }
                $usuarios[$email]['pedidos'][$i]['status'] = $novo;
                $mensagem = ['ok', 'Pedido #' . $numero . ' atualizado para "' . $etapas[$novo] . '".'];
            }
            gravarJson('usuarios.json', $usuarios);
            break;
        }
        aviso($mensagem[0], $mensagem[1]);
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
            $lista = carregarPagamentos();
            $item  = ['nome' => $nome, 'descricao' => $desc, 'desconto' => round((float) $descIn, 2), 'parcelas' => $parcelas, 'ativo' => $ativo];
            $achou = false;
            foreach ($lista as $k => $p) {
                if ($idPg && $p['id'] === $idPg) {
                    $lista[$k] = ['id' => $idPg] + $item;
                    $achou = true;
                }
            }
            if (!$achou) {
                $novoId = 1;
                foreach ($lista as $p) {
                    $novoId = max($novoId, $p['id'] + 1);
                }
                $lista[] = ['id' => $novoId] + $item;
            }
            gravarJson('pagamentos.json', $lista);
            aviso('ok', $achou ? 'Forma de pagamento atualizada.' : 'Forma de pagamento adicionada.');
        }
        irPainel('pagamentos');

    case 'adm_pagamento_excluir':
        $idPg  = (int) ($_POST['id'] ?? 0);
        $lista = array_values(array_filter(carregarPagamentos(), fn($p) => $p['id'] !== $idPg));
        gravarJson('pagamentos.json', $lista);
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
            $repos = lerJson('reposicoes.json');
            $novoId = 1;
            foreach ($repos as $r) {
                $novoId = max($novoId, $r['id'] + 1);
            }
            array_unshift($repos, [
                'id' => $novoId, 'data' => date('d/m/Y'), 'produto_id' => $idProd, 'produto' => $mapa[$idProd]['nome'],
                'qtd' => $qtd, 'fornecedor' => $fornecedor, 'custo' => round((float) $custoIn, 2),
                'previsao' => $previsao !== '' ? date('d/m/Y', strtotime($previsao)) : '', 'status' => 'Solicitada',
            ]);
            gravarJson('reposicoes.json', $repos);
            aviso('ok', 'Reposição #' . $novoId . ' registrada.');
        }
        irPainel('reposicao');

    case 'adm_reposicao_receber':
    case 'adm_reposicao_cancelar':
        $idRep = (int) ($_POST['id'] ?? 0);
        $repos = lerJson('reposicoes.json');
        $resultado = ['erro', 'Reposição não encontrada ou já encerrada.'];

        foreach ($repos as $k => $r) {
            if ($r['id'] !== $idRep || $r['status'] !== 'Solicitada') {
                continue;
            }
            if ($acao === 'adm_reposicao_receber') {
                $lista = carregarProdutos();
                foreach ($lista as $j => $p) {
                    if ($p['id'] === $r['produto_id']) {
                        $lista[$j]['estoque'] += $r['qtd'];
                        registrarMovimentos([movimento($p, 'Reposição', $r['qtd'], $lista[$j]['estoque'], 'Reposição #' . $idRep . ' — ' . $r['fornecedor'])]);
                    }
                }
                salvarProdutos($lista);
                $repos[$k]['status'] = 'Recebida';
                $repos[$k]['recebida_em'] = date('d/m/Y');
                $resultado = ['ok', 'Mercadoria recebida: +' . $r['qtd'] . ' un. de "' . $r['produto'] . '" no estoque.'];
            } else {
                $repos[$k]['status'] = 'Cancelada';
                $resultado = ['ok', 'Reposição #' . $idRep . ' cancelada.'];
            }
            gravarJson('reposicoes.json', $repos);
            break;
        }
        aviso($resultado[0], $resultado[1]);
        irPainel('reposicao');
}

aviso('erro', 'Ação desconhecida.');
irPainel('produtos');