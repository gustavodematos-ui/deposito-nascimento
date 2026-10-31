<?php
/**
 * Depósito Nascimento — telas do painel do administrador.
 * Incluído pelo index.php dentro de <main>. Usa $painel para escolher a tela.
 */
defined('DEPOSITO') or exit;

$reservas = reservadoMapa();
?>

<?php /* ============================== PRODUTOS ============================== */ ?>
<?php if ($painel === 'produtos'): ?>

    <?php if (isset($_GET['novo']) || isset($_GET['editar'])):
        $editId = (int) ($_GET['editar'] ?? 0);
        $prod   = $editId ? ($mapa[$editId] ?? null) : null;
        $f = array_merge(
            ['nome' => '', 'categoria' => '', 'descricao' => '', 'preco' => '', 'estoque' => 0, 'minimo' => 10, 'ativo' => true, 'imagem' => ''],
            $prod ?: [],
            $old
        );
        $categoriasExistentes = array_values(array_unique(array_column($catalogoTodos, 'categoria')));
    ?>
        <div class="section-title">
            <h2><?= $editId ? 'Editar produto' : 'Novo produto' ?></h2>
            <p><?= $editId ? 'Altere os dados e salve.' : 'Preencha os dados para incluir um item no catálogo.' ?></p>
        </div>

        <?php if ($editId && !$prod): ?>
            <div class="vazio">
                <i class="fa-solid fa-circle-question"></i>
                <h3>Produto não encontrado</h3>
                <a href="?painel=produtos" class="btn-link"><i class="fa-solid fa-arrow-left"></i> Voltar aos produtos</a>
            </div>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="form-card">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="adm_produto_salvar">
                <input type="hidden" name="id" value="<?= (int) $editId ?>">

                <div class="form-produto">
                    <div class="imagem-upload">
                        <img id="previaImagem" src="<?= h(srcImagem(['nome' => $f['nome'] ?: 'Produto', 'imagem' => $f['imagem']])) ?>" alt="Prévia da imagem">
                        <label class="btn-link btn-outline" for="p-imagem"><i class="fa-solid fa-image"></i> Escolher imagem</label>
                        <input type="file" id="p-imagem" name="imagem" accept="image/jpeg,image/png,image/webp" hidden>
                        <small>JPG, PNG ou WEBP, até 2 MB.<?= $editId ? ' Deixe vazio para manter a atual.' : '' ?></small>
                    </div>

                    <div>
                        <div class="campo">
                            <label for="p-nome">Nome do produto</label>
                            <input type="text" id="p-nome" name="nome" maxlength="100" required value="<?= h($f['nome']) ?>">
                        </div>
                        <div class="campo">
                            <label for="p-cat">Categoria</label>
                            <input type="text" id="p-cat" name="categoria" maxlength="60" list="lista-categorias" required value="<?= h($f['categoria']) ?>">
                            <datalist id="lista-categorias">
                                <?php foreach ($categoriasExistentes as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
                            </datalist>
                            <small>Escolha uma existente ou digite uma nova.</small>
                        </div>
                        <div class="campo">
                            <label for="p-desc">Descrição curta</label>
                            <textarea id="p-desc" name="descricao" maxlength="300" style="min-height:90px"><?= h($f['descricao']) ?></textarea>
                        </div>
                        <div class="campo-linha tres">
                            <div class="campo">
                                <label for="p-preco">Preço (R$)</label>
                                <input type="text" inputmode="decimal" id="p-preco" name="preco" required placeholder="0,00"
                                       value="<?= h($f['preco'] === '' ? '' : str_replace('.', ',', (string) $f['preco'])) ?>">
                            </div>
                            <div class="campo">
                                <label for="p-estoque">Estoque</label>
                                <input type="number" id="p-estoque" name="estoque" min="0" step="1" required value="<?= h($f['estoque']) ?>">
                            </div>
                            <div class="campo">
                                <label for="p-min">Estoque mínimo</label>
                                <input type="number" id="p-min" name="minimo" min="0" step="1" required value="<?= h($f['minimo']) ?>">
                            </div>
                        </div>
                        <label class="check-linha">
                            <input type="checkbox" name="ativo" value="1" <?= $f['ativo'] ? 'checked' : '' ?>>
                            Visível na loja (desmarque para ocultar sem excluir)
                        </label>
                    </div>
                </div>

                <div class="form-acoes">
                    <a href="?painel=produtos" class="btn-link btn-outline">Cancelar</a>
                    <button type="submit" class="btn-link"><i class="fa-solid fa-floppy-disk"></i> Salvar produto</button>
                </div>
            </form>
        <?php endif; ?>

    <?php else: ?>
        <div class="section-title">
            <h2>Produtos</h2>
            <p>Gerencie o catálogo: adicione, edite ou exclua itens.</p>
        </div>

        <div class="barra-acoes">
            <span><?= count($catalogoTodos) ?> produto(s) cadastrado(s)</span>
            <a href="?painel=produtos&novo=1" class="btn-link"><i class="fa-solid fa-plus"></i> Novo produto</a>
        </div>

        <?php if (!$catalogoTodos): ?>
            <div class="vazio">
                <i class="fa-solid fa-boxes-stacked"></i>
                <h3>Nenhum produto cadastrado</h3>
                <p>Adicione o primeiro item do catálogo.</p>
                <a href="?painel=produtos&novo=1" class="btn-link"><i class="fa-solid fa-plus"></i> Novo produto</a>
            </div>
        <?php else: ?>
            <div class="tabela-wrap">
                <table class="tabela">
                    <thead>
                        <tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Na loja</th><th class="col-acoes">Ações</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalogoTodos as $p): ?>
                            <tr>
                                <td>
                                    <div class="celula-produto">
                                        <img src="<?= h(srcImagem($p)) ?>" alt="" onerror="this.onerror=null;this.src='<?= h(imagemReserva($p['nome'])) ?>';">
                                        <div>
                                            <strong><?= h($p['nome']) ?></strong>
                                            <small><?= h(mb_strimwidth($p['descricao'], 0, 60, '…')) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= h($p['categoria']) ?></td>
                                <td><?= moeda($p['preco']) ?></td>
                                <td><?= (int) $p['estoque'] ?></td>
                                <td>
                                    <?php if ($p['ativo']): ?><span class="status-tag entregue">Visível</span>
                                    <?php else: ?><span class="status-tag inativo">Oculto</span><?php endif; ?>
                                </td>
                                <td class="col-acoes">
                                    <a href="?painel=produtos&editar=<?= (int) $p['id'] ?>" class="btn-mini"><i class="fa-solid fa-pen"></i> Editar</a>
                                    <form method="post" data-confirmar="Excluir &quot;<?= h($p['nome']) ?>&quot;? Esta ação não pode ser desfeita.">
                                        <?= csrf() ?>
                                        <input type="hidden" name="acao" value="adm_produto_excluir">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <button type="submit" class="btn-mini perigo"><i class="fa-regular fa-trash-can"></i> Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php /* ============================== PEDIDOS ============================== */ ?>
<?php elseif ($painel === 'pedidos'):
    $aba = in_array($_GET['aba'] ?? '', ['vendas', 'cancelados'], true) ? $_GET['aba'] : 'andamento';
    $todosPed = listarPedidos();

    $andamento  = array_values(array_filter($todosPed, fn($p) => $p['status'] < 3));
    $vendas     = array_values(array_filter($todosPed, fn($p) => $p['status'] === 3));
    $cancelados = array_values(array_filter($todosPed, fn($p) => $p['status'] === 4));

    $receita = array_sum(array_map('totalPedido', $vendas));
    $ticket  = $vendas ? $receita / count($vendas) : 0;

    $listaAba = $aba === 'vendas' ? $vendas : ($aba === 'cancelados' ? $cancelados : $andamento);
?>
    <div class="section-title">
        <h2>Pedidos</h2>
        <p>Acompanhe os pedidos, veja os detalhes e consulte as vendas já concluídas.</p>
    </div>

    <div class="cartoes-resumo">
        <div class="cartao-resumo"><span>Em andamento</span><strong><?= count($andamento) ?></strong></div>
        <div class="cartao-resumo"><span>Vendas realizadas</span><strong><?= count($vendas) ?></strong></div>
        <div class="cartao-resumo"><span>Receita das vendas</span><strong><?= moeda($receita) ?></strong></div>
        <div class="cartao-resumo"><span>Ticket médio</span><strong><?= moeda($ticket) ?></strong></div>
    </div>

    <div class="abas">
        <a href="?painel=pedidos&aba=andamento" class="<?= $aba === 'andamento' ? 'ativa' : '' ?>">Em andamento (<?= count($andamento) ?>)</a>
        <a href="?painel=pedidos&aba=vendas" class="<?= $aba === 'vendas' ? 'ativa' : '' ?>">Vendas realizadas (<?= count($vendas) ?>)</a>
        <a href="?painel=pedidos&aba=cancelados" class="<?= $aba === 'cancelados' ? 'ativa' : '' ?>">Cancelados (<?= count($cancelados) ?>)</a>
    </div>

    <?php if (!$listaAba): ?>
        <div class="vazio">
            <i class="fa-solid fa-clipboard-list"></i>
            <h3>Nada por aqui</h3>
            <p>Não há pedidos nesta lista no momento.</p>
        </div>
    <?php else: ?>
        <div class="lista-pedidos">
            <?php foreach ($listaAba as $ped): ?>
                <article class="pedido-card">
                    <div class="pedido-topo">
                        <div>
                            <div class="pedido-numero">Pedido #<?= (int) $ped['numero'] ?></div>
                            <div class="pedido-data"><?= h($ped['data']) ?> · <?= h($ped['cliente_nome']) ?> (<?= h($ped['cliente_email']) ?>)</div>
                        </div>
                        <?php if ($ped['status'] === 3): ?>
                            <span class="status-tag entregue"><i class="fa-solid fa-circle-check"></i> Entregue</span>
                        <?php elseif ($ped['status'] === 4): ?>
                            <span class="status-tag cancelado"><i class="fa-solid fa-ban"></i> Cancelado</span>
                        <?php else: ?>
                            <span class="status-tag"><i class="fa-solid fa-truck-fast"></i> <?= h($etapas[$ped['status']]) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="pedido-corpo">
                        <ul class="pedido-itens">
                            <?php foreach ($ped['itens'] as $item): ?>
                                <li>
                                    <span><?= (int) $item['qtd'] ?>× <?= h($item['nome']) ?> <small>(<?= moeda($item['preco']) ?> cada)</small></span>
                                    <span><?= moeda($item['qtd'] * $item['preco']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (!empty($ped['pagamento'])): ?>
                            <div class="pedido-pagto">
                                <i class="fa-solid fa-credit-card"></i> <?= h($ped['pagamento']['nome']) ?>
                                <?php if ($ped['pagamento']['desconto'] > 0): ?>
                                    — desconto de <?= h(rtrim(rtrim(number_format($ped['pagamento']['desconto'], 2, ',', ''), '0'), ',')) ?>%
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="pedido-rodape">
                            <span class="pedido-total">Total: <strong><?= moeda(totalPedido($ped)) ?></strong></span>

                            <?php if ($ped['status'] < 3): ?>
                                <div class="grupo-botoes">
                                    <form method="post" data-confirmar="Cancelar o pedido #<?= (int) $ped['numero'] ?>?">
                                        <?= csrf() ?>
                                        <input type="hidden" name="acao" value="adm_pedido_status">
                                        <input type="hidden" name="op" value="cancelar">
                                        <input type="hidden" name="aba" value="<?= h($aba) ?>">
                                        <input type="hidden" name="email" value="<?= h($ped['cliente_email']) ?>">
                                        <input type="hidden" name="numero" value="<?= (int) $ped['numero'] ?>">
                                        <button type="submit" class="btn-link btn-outline"><i class="fa-solid fa-ban"></i> Cancelar</button>
                                    </form>
                                    <form method="post">
                                        <?= csrf() ?>
                                        <input type="hidden" name="acao" value="adm_pedido_status">
                                        <input type="hidden" name="op" value="avancar">
                                        <input type="hidden" name="aba" value="<?= h($aba) ?>">
                                        <input type="hidden" name="email" value="<?= h($ped['cliente_email']) ?>">
                                        <input type="hidden" name="numero" value="<?= (int) $ped['numero'] ?>">
                                        <button type="submit" class="btn-link">
                                            <i class="fa-solid fa-forward"></i> Avançar para "<?= h($etapas[$ped['status'] + 1]) ?>"
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php /* ============================== ESTOQUE ============================== */ ?>
<?php elseif ($painel === 'estoque'):
    $unidades = 0; $valorEstoque = 0; $baixos = 0; $esgotados = 0;
    foreach ($catalogoTodos as $p) {
        $disp = max(0, $p['estoque'] - ($reservas[$p['id']] ?? 0));
        $unidades += $p['estoque'];
        $valorEstoque += $p['estoque'] * $p['preco'];
        if ($p['estoque'] <= 0) { $esgotados++; } elseif ($disp <= $p['minimo']) { $baixos++; }
    }
    $historico = listarMovimentos(20);
?>
    <div class="section-title">
        <h2>Estoque</h2>
        <p>Controle as quantidades, defina o estoque mínimo e acompanhe as movimentações.</p>
    </div>

    <div class="cartoes-resumo">
        <div class="cartao-resumo"><span>Unidades em estoque</span><strong><?= number_format($unidades, 0, ',', '.') ?></strong></div>
        <div class="cartao-resumo"><span>Valor em estoque</span><strong><?= moeda($valorEstoque) ?></strong></div>
        <div class="cartao-resumo"><span>Estoque baixo</span><strong><?= $baixos ?></strong></div>
        <div class="cartao-resumo"><span>Esgotados</span><strong><?= $esgotados ?></strong></div>
    </div>

    <div class="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr><th>Produto</th><th>Físico</th><th>Reservado</th><th>Disponível</th><th>Mínimo</th><th>Situação</th><th>Ajustar estoque</th></tr>
            </thead>
            <tbody>
                <?php foreach ($catalogoTodos as $p):
                    $res  = $reservas[$p['id']] ?? 0;
                    $disp = max(0, $p['estoque'] - $res);
                    [$rotuloSit, $classeSit] = $p['estoque'] <= 0 ? ['Esgotado', 'esgotado'] : ($disp <= $p['minimo'] ? ['Baixo', 'baixo'] : ['Normal', 'entregue']);
                ?>
                    <tr>
                        <td><strong><?= h($p['nome']) ?></strong><small class="sub"><?= h($p['categoria']) ?></small></td>
                        <td><?= (int) $p['estoque'] ?></td>
                        <td><?= (int) $res ?></td>
                        <td><strong><?= (int) $disp ?></strong></td>
                        <td>
                            <form method="post" class="form-inline">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adm_estoque_minimo">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="number" name="minimo" min="0" step="1" value="<?= (int) $p['minimo'] ?>" class="input-mini" aria-label="Estoque mínimo">
                                <button type="submit" class="btn-mini" title="Salvar mínimo"><i class="fa-solid fa-check"></i></button>
                            </form>
                        </td>
                        <td><span class="status-tag <?= $classeSit ?>"><?= $rotuloSit ?></span></td>
                        <td>
                            <form method="post" class="form-inline">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adm_estoque_ajustar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="number" name="delta" step="1" placeholder="± qtd" class="input-mini" required aria-label="Quantidade (negativa para saída)">
                                <input type="text" name="motivo" maxlength="120" placeholder="Motivo" class="input-motivo" aria-label="Motivo">
                                <button type="submit" class="btn-mini">Aplicar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="nota">Reservado = unidades de pedidos ainda não entregues. A baixa definitiva acontece quando o pedido é marcado como entregue. Use valores negativos para registrar saídas (perda, quebra, etc.).</p>

    <h3 class="subtitulo">Últimas movimentações</h3>
    <?php if (!$historico): ?>
        <p class="nota">Nenhuma movimentação registrada ainda.</p>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Saldo</th><th>Motivo</th></tr></thead>
                <tbody>
                    <?php foreach ($historico as $m): ?>
                        <tr>
                            <td><?= h($m['data']) ?></td>
                            <td><?= h($m['produto']) ?></td>
                            <td><?= h($m['tipo']) ?></td>
                            <td class="<?= $m['qtd'] >= 0 ? 'mov-entrada' : 'mov-saida' ?>"><?= $m['qtd'] > 0 ? '+' : '' ?><?= (int) $m['qtd'] ?></td>
                            <td><?= (int) $m['saldo'] ?></td>
                            <td><?= h($m['motivo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php /* ============================== FORMA DE PAGAMENTO ============================== */ ?>
<?php elseif ($painel === 'pagamentos'):
    $formas = carregarPagamentos();
?>
    <div class="section-title">
        <h2>Forma de pagamento</h2>
        <p>Defina quais opções aparecem para o cliente ao finalizar o pedido.</p>
    </div>

    <form method="post" class="form-card">
        <?= csrf() ?>
        <input type="hidden" name="acao" value="adm_pagamento_salvar">
        <input type="hidden" name="id" value="0">
        <h3 class="subtitulo" style="margin-top:0">Adicionar forma de pagamento</h3>
        <div class="campo-linha tres">
            <div class="campo"><label for="n-nome">Nome</label><input type="text" id="n-nome" name="nome" maxlength="40" required placeholder="Ex.: Transferência"></div>
            <div class="campo"><label for="n-desc">Desconto (%)</label><input type="text" inputmode="decimal" id="n-desc" name="desconto" value="0"></div>
            <div class="campo"><label for="n-parc">Parcelas (máx.)</label><input type="number" id="n-parc" name="parcelas" min="1" max="12" value="1"></div>
        </div>
        <div class="campo"><label for="n-d">Descrição</label><input type="text" id="n-d" name="descricao" maxlength="120" placeholder="Texto exibido ao cliente"></div>
        <div class="form-acoes">
            <label class="check-linha"><input type="checkbox" name="ativo" value="1" checked> Ativa</label>
            <button type="submit" class="btn-link"><i class="fa-solid fa-plus"></i> Adicionar</button>
        </div>
    </form>

    <div class="lista-pedidos" style="margin-top:20px">
        <?php foreach ($formas as $pg): ?>
            <div class="pagto-card <?= $pg['ativo'] ? '' : 'desativada' ?>">
                <form method="post">
                    <?= csrf() ?>
                    <input type="hidden" name="acao" value="adm_pagamento_salvar">
                    <input type="hidden" name="id" value="<?= (int) $pg['id'] ?>">
                    <div class="pagto-topo">
                        <strong><i class="fa-solid fa-credit-card"></i> <?= h($pg['nome']) ?></strong>
                        <span class="status-tag <?= $pg['ativo'] ? 'entregue' : 'inativo' ?>"><?= $pg['ativo'] ? 'Ativa' : 'Desativada' ?></span>
                    </div>
                    <div class="campo-linha tres">
                        <div class="campo"><label>Nome</label><input type="text" name="nome" maxlength="40" required value="<?= h($pg['nome']) ?>"></div>
                        <div class="campo"><label>Desconto (%)</label><input type="text" inputmode="decimal" name="desconto" value="<?= h(str_replace('.', ',', (string) $pg['desconto'])) ?>"></div>
                        <div class="campo"><label>Parcelas (máx.)</label><input type="number" name="parcelas" min="1" max="12" value="<?= (int) $pg['parcelas'] ?>"></div>
                    </div>
                    <div class="campo"><label>Descrição</label><input type="text" name="descricao" maxlength="120" value="<?= h($pg['descricao']) ?>"></div>
                    <div class="form-acoes">
                        <label class="check-linha"><input type="checkbox" name="ativo" value="1" <?= $pg['ativo'] ? 'checked' : '' ?>> Ativa</label>
                        <button type="submit" class="btn-link"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
                    </div>
                </form>
                <form method="post" class="pagto-excluir" data-confirmar="Remover &quot;<?= h($pg['nome']) ?>&quot;?">
                    <?= csrf() ?>
                    <input type="hidden" name="acao" value="adm_pagamento_excluir">
                    <input type="hidden" name="id" value="<?= (int) $pg['id'] ?>">
                    <button type="submit" class="btn-mini perigo"><i class="fa-regular fa-trash-can"></i> Remover</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<?php /* ============================== REPOSIÇÃO ============================== */ ?>
<?php elseif ($painel === 'reposicao'):
    $repos = listarReposicoes();
    $pendentes = [];
    foreach ($repos as $r) {
        if ($r['status'] === 'Solicitada') {
            $pendentes[$r['produto_id']] = ($pendentes[$r['produto_id']] ?? 0) + $r['qtd'];
        }
    }

    $sugestoes = [];
    foreach ($catalogoTodos as $p) {
        $disp = max(0, $p['estoque'] - ($reservas[$p['id']] ?? 0));
        if ($disp <= $p['minimo'] && !isset($pendentes[$p['id']])) {
            $sugestoes[] = $p + ['disp' => $disp, 'sugerido' => max(10, $p['minimo'] * 2 - $disp)];
        }
    }

    $preProd = (int) ($_GET['produto'] ?? 0);
    $preQtd  = max(1, (int) ($_GET['qtd'] ?? 0));
?>
    <div class="section-title">
        <h2>Reposição</h2>
        <p>Solicite mercadoria aos fornecedores e dê entrada no estoque quando chegar.</p>
    </div>

    <h3 class="subtitulo" style="margin-top:0"><i class="fa-solid fa-triangle-exclamation" style="color:var(--primary)"></i> Sugestões de reposição</h3>
    <?php if (!$sugestoes): ?>
        <p class="nota">Tudo certo: nenhum produto abaixo do estoque mínimo sem reposição em andamento.</p>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead><tr><th>Produto</th><th>Disponível</th><th>Mínimo</th><th>Sugestão</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($sugestoes as $s): ?>
                        <tr>
                            <td><strong><?= h($s['nome']) ?></strong><small class="sub"><?= h($s['categoria']) ?></small></td>
                            <td><span class="status-tag <?= $s['disp'] <= 0 ? 'esgotado' : 'baixo' ?>"><?= (int) $s['disp'] ?></span></td>
                            <td><?= (int) $s['minimo'] ?></td>
                            <td>+<?= (int) $s['sugerido'] ?> un.</td>
                            <td class="col-acoes"><a class="btn-mini" href="?painel=reposicao&produto=<?= (int) $s['id'] ?>&qtd=<?= (int) $s['sugerido'] ?>#nova-reposicao"><i class="fa-solid fa-cart-flatbed"></i> Solicitar</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <form method="post" class="form-card" id="nova-reposicao" style="margin-top:24px">
        <?= csrf() ?>
        <input type="hidden" name="acao" value="adm_reposicao_criar">
        <h3 class="subtitulo" style="margin-top:0">Nova solicitação</h3>
        <div class="campo-linha">
            <div class="campo">
                <label for="r-prod">Produto</label>
                <select id="r-prod" name="produto_id" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($catalogoTodos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $preProd === $p['id'] ? 'selected' : '' ?>><?= h($p['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="r-qtd">Quantidade</label>
                <input type="number" id="r-qtd" name="qtd" min="1" step="1" required value="<?= $preProd ? $preQtd : '' ?>">
            </div>
        </div>
        <div class="campo-linha tres">
            <div class="campo"><label for="r-forn">Fornecedor</label><input type="text" id="r-forn" name="fornecedor" maxlength="100" required></div>
            <div class="campo"><label for="r-custo">Custo unitário (R$)</label><input type="text" inputmode="decimal" id="r-custo" name="custo" placeholder="0,00"></div>
            <div class="campo"><label for="r-prev">Previsão de chegada</label><input type="date" id="r-prev" name="previsao"></div>
        </div>
        <div class="form-acoes">
            <button type="submit" class="btn-link"><i class="fa-solid fa-paper-plane"></i> Registrar solicitação</button>
        </div>
    </form>

    <h3 class="subtitulo">Solicitações</h3>
    <?php if (!$repos): ?>
        <p class="nota">Nenhuma reposição registrada ainda.</p>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead><tr><th>#</th><th>Data</th><th>Produto</th><th>Qtd.</th><th>Fornecedor</th><th>Custo total</th><th>Previsão</th><th>Status</th><th class="col-acoes">Ações</th></tr></thead>
                <tbody>
                    <?php foreach ($repos as $r):
                        $classeRep = $r['status'] === 'Recebida' ? 'entregue' : ($r['status'] === 'Cancelada' ? 'cancelado' : '');
                    ?>
                        <tr>
                            <td><?= (int) $r['id'] ?></td>
                            <td><?= h($r['data']) ?></td>
                            <td><?= h($r['produto']) ?></td>
                            <td><?= (int) $r['qtd'] ?></td>
                            <td><?= h($r['fornecedor']) ?></td>
                            <td><?= moeda($r['custo'] * $r['qtd']) ?></td>
                            <td><?= h($r['previsao'] ?: '—') ?></td>
                            <td><span class="status-tag <?= $classeRep ?>"><?= h($r['status']) ?></span></td>
                            <td class="col-acoes">
                                <?php if ($r['status'] === 'Solicitada'): ?>
                                    <form method="post" data-confirmar="Confirmar o recebimento de <?= (int) $r['qtd'] ?> un. de &quot;<?= h($r['produto']) ?>&quot;? O estoque será atualizado.">
                                        <?= csrf() ?><input type="hidden" name="acao" value="adm_reposicao_receber"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn-mini"><i class="fa-solid fa-box-open"></i> Receber</button>
                                    </form>
                                    <form method="post" data-confirmar="Cancelar esta solicitação?">
                                        <?= csrf() ?><input type="hidden" name="acao" value="adm_reposicao_cancelar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn-mini perigo"><i class="fa-solid fa-ban"></i> Cancelar</button>
                                    </form>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php /* ============================== MENSAGENS ============================== */ ?>
<?php else:
    $mensagensRecebidas = listarMensagens();
    marcarMensagensLidas();   // as marcadas como "Nova" abaixo já foram lidas a partir de agora
?>
    <div class="section-title">
        <h2>Mensagens recebidas</h2>
        <p><?= count($mensagensRecebidas) ?> mensagem(ns) enviada(s) pelo formulário de contato.</p>
    </div>

    <?php if (!$mensagensRecebidas): ?>
        <div class="vazio">
            <i class="fa-solid fa-inbox"></i>
            <h3>Nenhuma mensagem ainda</h3>
            <p>As mensagens enviadas pelos clientes aparecerão aqui.</p>
        </div>
    <?php else: ?>
        <div class="lista-pedidos">
            <?php foreach ($mensagensRecebidas as $m): ?>
                <article class="msg-card">
                    <div class="msg-topo">
                        <div>
                            <strong><?= h($m['nome']) ?></strong>
                            <small>
                                <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
                                <?= !empty($m['telefone']) ? ' · ' . h($m['telefone']) : '' ?>
                            </small>
                        </div>
                        <div style="text-align:right">
                            <?php if (!$m['lida']): ?><span class="status-tag entregue">Nova</span> <?php endif; ?><span class="status-tag"><?= h($m['assunto']) ?></span><br>
                            <small><?= h($m['data']) ?></small>
                        </div>
                    </div>
                    <div class="msg-texto"><?= h($m['mensagem']) ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>