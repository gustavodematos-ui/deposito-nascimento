<?php
/**
 * Depósito Nascimento — telas do painel do administrador (somente exibição).
 * Incluído pelo index.php dentro do <main>. NÃO processa formulários:
 * os POSTs ficam em admin_acoes.php.
 *
 * Variáveis herdadas do index.php: $painel, $mapa, $catalogoTodos, $old, $ehAdmin.
 */
defined('DEPOSITO') or exit;

if (!$ehAdmin) {
    return;
}

/** Rótulo + classe do status do pedido. */
function adminStatusPedido(int $status): array
{
    if ($status === 4) {
        return ['Cancelado', 'cancelado'];
    }
    return [ETAPAS[$status] ?? '—', $status === 3 ? 'entregue' : ''];
}

/** Valor de campo: usa o que o admin digitou (após erro) ou o valor atual. */
function adminValor(array $old, string $chave, $padrao = '')
{
    return array_key_exists($chave, $old) ? $old[$chave] : $padrao;
}

$reservados = reservadoMapa();
?>

<?php /* ============================== PRODUTOS ============================== */ ?>
<?php if ($painel === 'produtos'): ?>

    <?php
    $editarId = (int) ($_GET['editar'] ?? 0);
    $novo     = isset($_GET['novo']);
    $emForm   = $novo || ($editarId && isset($mapa[$editarId]));
    ?>

    <?php if ($emForm):
        $p = $editarId ? $mapa[$editarId] : ['nome' => '', 'categoria' => '', 'descricao' => '', 'preco' => '', 'estoque' => 0, 'minimo' => 10, 'imagem' => '', 'ativo' => true, 'id' => 0];
        $categoriasExistentes = array_values(array_unique(array_column($catalogoTodos, 'categoria')));
        $precoPadrao = $p['preco'] === '' ? '' : number_format((float) $p['preco'], 2, ',', '');
    ?>
        <div class="section-title">
            <h2><?= $editarId ? 'Editar produto' : 'Novo produto' ?></h2>
            <p>Alterar o estoque aqui gera um registro de "Ajuste" na movimentação.</p>
        </div>

        <form method="post" enctype="multipart/form-data" class="form-card">
            <?= csrf() ?>
            <input type="hidden" name="acao" value="adm_produto_salvar">
            <input type="hidden" name="id" value="<?= (int) $editarId ?>">

            <div class="form-produto">
                <div class="imagem-upload">
                    <img id="previaImagem" src="<?= h(srcImagem($p)) ?>" alt="Prévia da imagem">
                    <label class="btn-link btn-outline" for="p-imagem"><i class="fa-solid fa-image"></i> Escolher imagem</label>
                    <input type="file" id="p-imagem" name="imagem" accept="image/jpeg,image/png,image/webp" hidden>
                    <small>JPG, PNG ou WebP, até 2 MB.</small>
                </div>

                <div>
                    <div class="campo">
                        <label for="p-nome">Nome</label>
                        <input type="text" id="p-nome" name="nome" maxlength="100" required value="<?= h(adminValor($old, 'nome', $p['nome'])) ?>">
                    </div>
                    <div class="campo">
                        <label for="p-cat">Categoria</label>
                        <input type="text" id="p-cat" name="categoria" maxlength="60" list="lista-categorias" required value="<?= h(adminValor($old, 'categoria', $p['categoria'])) ?>">
                        <datalist id="lista-categorias">
                            <?php foreach ($categoriasExistentes as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="campo">
                        <label for="p-desc">Descrição <small>(opcional, até 300 caracteres)</small></label>
                        <textarea id="p-desc" name="descricao" maxlength="300" rows="3"><?= h(adminValor($old, 'descricao', $p['descricao'])) ?></textarea>
                    </div>
                    <div class="campo-linha tres">
                        <div class="campo">
                            <label for="p-preco">Preço (R$)</label>
                            <input type="text" id="p-preco" name="preco" inputmode="decimal" required value="<?= h(adminValor($old, 'preco', $precoPadrao)) ?>">
                        </div>
                        <div class="campo">
                            <label for="p-estoque">Estoque</label>
                            <input type="number" id="p-estoque" name="estoque" min="0" step="1" required value="<?= h(adminValor($old, 'estoque', $p['estoque'])) ?>">
                        </div>
                        <div class="campo">
                            <label for="p-minimo">Estoque mínimo</label>
                            <input type="number" id="p-minimo" name="minimo" min="0" step="1" required value="<?= h(adminValor($old, 'minimo', $p['minimo'])) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-acoes">
                <label class="check-linha">
                    <input type="checkbox" name="ativo" value="1" <?= adminValor($old, 'ativo', $p['ativo']) ? 'checked' : '' ?>>
                    Visível na loja
                </label>
                <a href="?painel=produtos" class="btn-link btn-outline">Cancelar</a>
                <button type="submit" class="btn-link"><i class="fa-solid fa-floppy-disk"></i> Salvar produto</button>
            </div>
        </form>

    <?php else:
        $abaixo = 0;
        $ativos = 0;
        foreach ($catalogoTodos as $p) {
            $ativos += $p['ativo'] ? 1 : 0;
            $abaixo += ($p['estoque'] <= $p['minimo']) ? 1 : 0;
        }
    ?>
        <div class="section-title">
            <h2>Produtos</h2>
            <p>Cadastre, edite, oculte ou exclua itens do catálogo.</p>
        </div>

        <div class="cartoes-resumo">
            <div class="cartao-resumo"><span>Produtos cadastrados</span><strong><?= count($catalogoTodos) ?></strong></div>
            <div class="cartao-resumo"><span>Visíveis na loja</span><strong><?= $ativos ?></strong></div>
            <div class="cartao-resumo"><span>Com estoque baixo</span><strong><?= $abaixo ?></strong></div>
        </div>

        <div class="barra-acoes">
            <span><?= count($catalogoTodos) ?> produto(s)</span>
            <a href="?painel=produtos&novo=1" class="btn-link"><i class="fa-solid fa-plus"></i> Novo produto</a>
        </div>

        <?php if (!$catalogoTodos): ?>
            <div class="vazio"><i class="fa-solid fa-boxes-stacked"></i><h3>Nenhum produto</h3><p>Clique em "Novo produto" para começar.</p></div>
        <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead>
                    <tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Estoque</th><th>Situação</th><th class="col-acoes">Ações</th></tr>
                </thead>
                <tbody>
                <?php foreach ($catalogoTodos as $p):
                    $esgotado = $p['estoque'] <= 0;
                    $baixo    = !$esgotado && $p['estoque'] <= $p['minimo'];
                ?>
                    <tr>
                        <td>
                            <div class="celula-produto">
                                <img src="<?= h(srcImagem($p)) ?>" alt="" onerror="this.onerror=null;this.src='<?= h(imagemReserva($p['nome'])) ?>';">
                                <div><strong><?= h($p['nome']) ?></strong><small>#<?= (int) $p['id'] ?></small></div>
                            </div>
                        </td>
                        <td><?= h($p['categoria']) ?></td>
                        <td><?= moeda($p['preco']) ?></td>
                        <td>
                            <?= (int) $p['estoque'] ?>
                            <?php if ($esgotado): ?><span class="status-tag esgotado">Esgotado</span>
                            <?php elseif ($baixo): ?><span class="status-tag baixo">Baixo</span><?php endif; ?>
                        </td>
                        <td><?= $p['ativo'] ? '<span class="status-tag entregue">Visível</span>' : '<span class="status-tag inativo">Oculto</span>' ?></td>
                        <td class="col-acoes">
                            <a class="btn-mini" href="?painel=produtos&editar=<?= (int) $p['id'] ?>"><i class="fa-solid fa-pen"></i> Editar</a>
                            <form method="post" data-confirmar="Excluir &quot;<?= h($p['nome']) ?>&quot;? Pedidos antigos mantêm o histórico.">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adm_produto_excluir">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn-mini perigo"><i class="fa-solid fa-trash"></i> Excluir</button>
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
<?php elseif ($painel === 'pedidos'): ?>

    <?php
    $aba = in_array($_GET['aba'] ?? '', ['andamento', 'vendas', 'cancelados'], true) ? $_GET['aba'] : 'andamento';
    $todos = listarPedidos();
    $grupos = ['andamento' => [], 'vendas' => [], 'cancelados' => []];
    foreach ($todos as $ped) {
        $grupos[$ped['status'] === 3 ? 'vendas' : ($ped['status'] === 4 ? 'cancelados' : 'andamento')][] = $ped;
    }
    $lista = $grupos[$aba];
    $rotulos = ['andamento' => 'Em andamento', 'vendas' => 'Vendas concluídas', 'cancelados' => 'Cancelados'];
    ?>

    <div class="section-title">
        <h2>Pedidos</h2>
        <p>Avance o pedido a cada etapa. O estoque só é baixado quando o pedido é entregue.</p>
    </div>

    <nav class="abas" aria-label="Filtro de pedidos">
        <?php foreach ($rotulos as $chave => $rotulo): ?>
            <a href="?painel=pedidos&aba=<?= $chave ?>" class="<?= $aba === $chave ? 'ativa' : '' ?>">
                <?= h($rotulo) ?> (<?= count($grupos[$chave]) ?>)
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$lista): ?>
        <div class="vazio"><i class="fa-solid fa-clipboard-list"></i><h3>Nenhum pedido aqui</h3><p>Quando houver pedidos nesta situação, eles aparecem nesta aba.</p></div>
    <?php endif; ?>

    <div class="lista-pedidos">
    <?php foreach ($lista as $ped):
        [$rotuloStatus, $classeStatus] = adminStatusPedido($ped['status']);
        $aberto = $ped['status'] < 3;
    ?>
        <article class="pedido-card">
            <div class="pedido-topo">
                <div>
                    <span class="pedido-numero">Pedido #<?= (int) $ped['numero'] ?></span>
                    <span class="pedido-data"><?= h($ped['data']) ?> · <?= h($ped['cliente_nome']) ?> (<?= h($ped['cliente_email']) ?>)</span>
                </div>
                <span class="status-tag <?= $classeStatus ?>"><?= h($rotuloStatus) ?></span>
            </div>
            <div class="pedido-corpo">
                <ul class="pedido-itens">
                    <?php foreach ($ped['itens'] as $i): ?>
                        <li>
                            <span><?= (int) $i['qtd'] ?>× <?= h($i['nome']) ?></span>
                            <small><?= moeda($i['preco']) ?> un.</small>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($ped['pagamento']): ?>
                    <div class="pedido-pagto"><i class="fa-solid fa-credit-card"></i> <?= h($ped['pagamento']['nome']) ?></div>
                <?php endif; ?>

                <?php if ($ped['status'] !== 4): ?>
                    <div class="etapas">
                        <?php foreach (ETAPAS as $n => $nomeEtapa): ?>
                            <div class="etapa <?= $ped['status'] >= $n ? 'feita' : '' ?> <?= $ped['status'] === $n ? 'atual' : '' ?>">
                                <span class="etapa-bolinha"><?= $ped['status'] >= $n ? '<i class="fa-solid fa-check"></i>' : $n + 1 ?></span>
                                <?= h($nomeEtapa) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="pedido-rodape">
                    <span class="pedido-total">Total: <strong><?= moeda($ped['total']) ?></strong></span>
                    <?php if ($aberto): ?>
                        <div class="grupo-botoes">
                            <form method="post">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adm_pedido_status">
                                <input type="hidden" name="numero" value="<?= (int) $ped['numero'] ?>">
                                <input type="hidden" name="aba" value="<?= h($aba) ?>">
                                <input type="hidden" name="op" value="avancar">
                                <button type="submit" class="btn-link"><i class="fa-solid fa-arrow-right"></i> Avançar para "<?= h(ETAPAS[$ped['status'] + 1]) ?>"</button>
                            </form>
                            <form method="post" data-confirmar="Cancelar o pedido #<?= (int) $ped['numero'] ?>?">
                                <?= csrf() ?>
                                <input type="hidden" name="acao" value="adm_pedido_status">
                                <input type="hidden" name="numero" value="<?= (int) $ped['numero'] ?>">
                                <input type="hidden" name="aba" value="<?= h($aba) ?>">
                                <input type="hidden" name="op" value="cancelar">
                                <button type="submit" class="btn-link btn-outline"><i class="fa-solid fa-ban"></i> Cancelar</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>

<?php /* ============================== ESTOQUE ============================== */ ?>
<?php elseif ($painel === 'estoque'): ?>

    <div class="section-title">
        <h2>Estoque</h2>
        <p>Disponível para venda = estoque físico − unidades reservadas em pedidos ainda não entregues.</p>
    </div>

    <div class="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr><th>Produto</th><th>Estoque</th><th>Reservado</th><th>Disponível</th><th>Mínimo</th><th class="col-acoes">Ajustar</th></tr>
            </thead>
            <tbody>
            <?php foreach ($catalogoTodos as $p):
                $res = $reservados[$p['id']] ?? 0;
            ?>
                <tr>
                    <td><strong><?= h($p['nome']) ?></strong><span class="sub"><?= h($p['categoria']) ?></span></td>
                    <td>
                        <?= (int) $p['estoque'] ?>
                        <?php if ($p['estoque'] <= 0): ?><span class="status-tag esgotado">Esgotado</span>
                        <?php elseif ($p['estoque'] <= $p['minimo']): ?><span class="status-tag baixo">Baixo</span><?php endif; ?>
                    </td>
                    <td><?= (int) $res ?></td>
                    <td><?= (int) disponivel($p) ?></td>
                    <td>
                        <form method="post" class="form-inline">
                            <?= csrf() ?>
                            <input type="hidden" name="acao" value="adm_estoque_minimo">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="number" class="input-mini" name="minimo" min="0" value="<?= (int) $p['minimo'] ?>" aria-label="Estoque mínimo">
                            <button type="submit" class="btn-mini" title="Salvar mínimo"><i class="fa-solid fa-check"></i></button>
                        </form>
                    </td>
                    <td class="col-acoes">
                        <form method="post" class="form-inline">
                            <?= csrf() ?>
                            <input type="hidden" name="acao" value="adm_estoque_ajustar">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <input type="number" class="input-mini" name="delta" step="1" placeholder="+10 / -3" required aria-label="Quantidade">
                            <input type="text" class="input-motivo" name="motivo" maxlength="120" placeholder="Motivo" aria-label="Motivo">
                            <button type="submit" class="btn-mini"><i class="fa-solid fa-plus-minus"></i> Ajustar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="nota">Use números negativos para saídas (ex.: -3). O estoque nunca pode ficar abaixo de zero.</p>

    <h3 class="subtitulo">Últimas movimentações</h3>
    <?php $movs = listarMovimentos(20); ?>
    <?php if (!$movs): ?>
        <div class="vazio"><i class="fa-solid fa-warehouse"></i><h3>Sem movimentações</h3></div>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd</th><th>Saldo</th><th>Motivo</th></tr></thead>
                <tbody>
                <?php foreach ($movs as $m): ?>
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

<?php /* ============================== PAGAMENTOS ============================== */ ?>
<?php elseif ($painel === 'pagamentos'): ?>

    <?php $pagamentos = carregarPagamentos(); ?>

    <div class="section-title">
        <h2>Formas de pagamento</h2>
        <p>Defina o que aparece para o cliente ao finalizar o pedido.</p>
    </div>

    <div class="product-grid">
    <?php foreach ($pagamentos as $pg): ?>
        <div class="pagto-card <?= $pg['ativo'] ? '' : 'desativada' ?>">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="adm_pagamento_salvar">
                <input type="hidden" name="id" value="<?= (int) $pg['id'] ?>">
                <div class="pagto-topo">
                    <strong><i class="fa-solid fa-credit-card"></i> <?= h($pg['nome']) ?></strong>
                    <label class="check-linha"><input type="checkbox" name="ativo" value="1" <?= $pg['ativo'] ? 'checked' : '' ?>> Ativa</label>
                </div>
                <div class="campo">
                    <label>Nome</label>
                    <input type="text" name="nome" maxlength="40" required value="<?= h($pg['nome']) ?>">
                </div>
                <div class="campo">
                    <label>Descrição</label>
                    <input type="text" name="descricao" maxlength="120" value="<?= h($pg['descricao']) ?>">
                </div>
                <div class="campo">
                    <label>Parcelas (1 a 12)</label>
                    <input type="number" name="parcelas" min="1" max="12" required value="<?= (int) $pg['parcelas'] ?>">
                </div>
                <button type="submit" class="btn-link"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </form>
            <form method="post" class="pagto-excluir" data-confirmar="Remover &quot;<?= h($pg['nome']) ?>&quot;? Pedidos antigos mantêm o nome.">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="adm_pagamento_excluir">
                <input type="hidden" name="id" value="<?= (int) $pg['id'] ?>">
                <button type="submit" class="btn-mini perigo"><i class="fa-solid fa-trash"></i> Remover</button>
            </form>
        </div>
    <?php endforeach; ?>

        <div class="pagto-card">
            <form method="post">
                <?= csrf() ?>
                <input type="hidden" name="acao" value="adm_pagamento_salvar">
                <input type="hidden" name="id" value="0">
                <div class="pagto-topo">
                    <strong><i class="fa-solid fa-plus"></i> Nova forma</strong>
                    <label class="check-linha"><input type="checkbox" name="ativo" value="1" checked> Ativa</label>
                </div>
                <div class="campo"><label>Nome</label><input type="text" name="nome" maxlength="40" required></div>
                <div class="campo"><label>Descrição</label><input type="text" name="descricao" maxlength="120"></div>
                <div class="campo"><label>Parcelas (1 a 12)</label><input type="number" name="parcelas" min="1" max="12" value="1" required></div>
                <button type="submit" class="btn-link"><i class="fa-solid fa-plus"></i> Adicionar</button>
            </form>
        </div>
    </div>

<?php /* ============================== REPOSIÇÃO ============================== */ ?>
<?php elseif ($painel === 'reposicao'): ?>

    <?php $reposicoes = listarReposicoes(); ?>

    <div class="section-title">
        <h2>Reposição de mercadoria</h2>
        <p>Registre pedidos ao fornecedor. Ao marcar como recebida, o estoque é atualizado automaticamente.</p>
    </div>

    <form method="post" class="form-card">
        <?= csrf() ?>
        <input type="hidden" name="acao" value="adm_reposicao_criar">
        <div class="campo-linha">
            <div class="campo">
                <label for="r-prod">Produto</label>
                <select id="r-prod" name="produto_id" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($catalogoTodos as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= h($p['nome']) ?> (estoque: <?= (int) $p['estoque'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="r-forn">Fornecedor</label>
                <input type="text" id="r-forn" name="fornecedor" maxlength="100" required>
            </div>
        </div>
        <div class="campo-linha tres">
            <div class="campo"><label for="r-qtd">Quantidade</label><input type="number" id="r-qtd" name="qtd" min="1" step="1" required></div>
            <div class="campo"><label for="r-custo">Custo unitário (R$)</label><input type="text" id="r-custo" name="custo" inputmode="decimal" value="0"></div>
            <div class="campo"><label for="r-prev">Previsão de chegada</label><input type="date" id="r-prev" name="previsao"></div>
        </div>
        <div class="form-acoes">
            <button type="submit" class="btn-link"><i class="fa-solid fa-truck-ramp-box"></i> Registrar reposição</button>
        </div>
    </form>

    <h3 class="subtitulo">Histórico</h3>
    <?php if (!$reposicoes): ?>
        <div class="vazio"><i class="fa-solid fa-truck-ramp-box"></i><h3>Nenhuma reposição registrada</h3></div>
    <?php else: ?>
        <div class="tabela-wrap">
            <table class="tabela">
                <thead><tr><th>#</th><th>Data</th><th>Produto</th><th>Qtd</th><th>Fornecedor</th><th>Custo un.</th><th>Previsão</th><th>Situação</th><th class="col-acoes">Ações</th></tr></thead>
                <tbody>
                <?php foreach ($reposicoes as $r):
                    $classe = $r['status'] === 'Recebida' ? 'entregue' : ($r['status'] === 'Cancelada' ? 'cancelado' : 'baixo');
                ?>
                    <tr>
                        <td><?= (int) $r['id'] ?></td>
                        <td><?= h($r['data']) ?></td>
                        <td><?= h($r['produto']) ?></td>
                        <td><?= (int) $r['qtd'] ?></td>
                        <td><?= h($r['fornecedor']) ?></td>
                        <td><?= moeda($r['custo']) ?></td>
                        <td><?= $r['previsao'] !== '' ? h($r['previsao']) : '—' ?></td>
                        <td><span class="status-tag <?= $classe ?>"><?= h($r['status']) ?></span></td>
                        <td class="col-acoes">
                            <?php if ($r['status'] === 'Solicitada'): ?>
                                <form method="post">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="adm_reposicao_receber">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn-mini"><i class="fa-solid fa-box-open"></i> Receber</button>
                                </form>
                                <form method="post" data-confirmar="Cancelar a reposição #<?= (int) $r['id'] ?>?">
                                    <?= csrf() ?>
                                    <input type="hidden" name="acao" value="adm_reposicao_cancelar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button type="submit" class="btn-mini perigo"><i class="fa-solid fa-ban"></i> Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

<?php endif; ?>