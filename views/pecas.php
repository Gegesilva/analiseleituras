<?php
header('Content-type: text/html; charset=UTF-8');
require_once '../config/database.php';
require_once '../models/testLogin.php';
require_once '../models/modtroca.php';
testLogin($conn);
$erro = ''; $dadosOs = null; $produto = null; $pecas = array();
$os = isset($_POST['os']) && is_string($_POST['os']) ? trim($_POST['os']) : (isset($_SESSION['os_troca']) ? trim($_SESSION['os_troca']) : '');
header('Content-type: text/html; charset=UTF-8');
require_once '../config/database.php';
require_once '../models/testLogin.php';
require_once '../models/modtroca.php';
testLogin($conn);
$erro = ''; $dadosOs = null; $produto = null; $pecas = array();
$os = isset($_POST['os']) && is_string($_POST['os']) ? trim($_POST['os']) : (isset($_SESSION['os_troca']) ? trim($_SESSION['os_troca']) : '');
try {
    $token = csrfTroca(); $dadosOs = buscarOsTroca($conn, $os); $produto = produtoPaiTroca($conn, $os);
    if (!$produto) { $_SESSION['os_troca'] = $os; header('Location: index.php'); exit; }
    $pecas = pecasTroca($conn, $os);
} catch (Exception $e) { $erro = $e->getMessage(); }
?>
<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Selecionar peças · Troca de peças</title><link rel="stylesheet" href="../assets/css/index.css"><link rel="stylesheet" href="../assets/css/troca.css"></head>
<body>
<main class="page-shell troca-shell" data-os="<?php echo h($os); ?>" data-pai="<?php echo $produto ? h($produto['codigo']) : ''; ?>" data-token="<?php echo isset($token) ? h($token) : ''; ?>">
    <header class="page-header"><span class="eyebrow">Troca de peças</span><a class="btn-sair" href="login.php">Sair</a></header>
    <section class="triagem-card"><div class="card-title"><div class="title-with-logo"><img src="../img/logo.jpg" alt="DATABIT"><div><h2>Selecionar peças</h2><p>Peças novas e peças retiradas do equipamento</p></div></div><span class="contador">9K</span></div>
        <div class="card-content"><div id="mensagem" class="mensagem<?php echo $erro ? ' erro' : ''; ?>" role="alert"><?php echo h($erro); ?></div>
        <?php if ($produto && !$erro) { ?><div class="resumo-os"><div><small>OS</small><strong><?php echo h($os); ?></strong></div><div><small>Série</small><strong><?php echo h($dadosOs['serie']); ?></strong></div><div class="equipamento"><small>Produto selecionado · <?php echo h($produto['codigo']); ?> · <?php echo h($produto['referencia']); ?></small><strong><?php echo h($produto['nome']); ?></strong></div></div><?php } ?>
        </div>
    </section>
    <?php if ($produto && !$erro) { ?>
    <div class="pecas-grid">
        <?php foreach (array('E' => 'Peças novas', 'S' => 'Peças retiradas') as $tipo => $titulo) { $total = 0; $itens = array(); foreach ($pecas as $peca) { if ($peca['tipo'] === $tipo) { $itens[] = $peca; $total += (float) $peca['total']; } } ?>
        <section class="triagem-card">
            <div class="card-title"><div><h2><?php echo h($titulo); ?></h2><p><?php echo $tipo === 'E' ? 'Componentes colocados no equipamento' : 'Componentes removidos do equipamento'; ?></p></div><span class="contador"><?php echo count($itens); ?></span></div>
            <div class="card-content"><section class="busca-produtos" data-tipo="<?php echo h($tipo); ?>"><form class="form-pesquisa"><div class="campo"><label for="busca<?php echo h($tipo); ?>">Buscar peça</label><input id="busca<?php echo h($tipo); ?>" class="termo-busca" maxlength="60" autocomplete="off" placeholder="Código, referência ou nome"></div><button class="btn-acao" type="submit">Pesquisar</button></form><div class="resultados" aria-live="polite"></div><div class="paginacao"></div></section></div>
            <div class="table-wrap"><table class="triagem-table"><thead><tr><th>Produto</th><th>Qtd.</th><th>Custo unitário</th><th>Custo total</th><th>Ações</th></tr></thead><tbody>
                <?php if (!count($itens)) { ?><tr><td colspan="5" class="empty-state">Nenhuma peça incluída.</td></tr><?php } ?>
                <?php foreach ($itens as $item) { ?>
                <tr><td class="produto-celula"><strong><?php echo h($item['nome']); ?></strong><small><?php echo h($item['codigo']); ?> · <?php echo h($item['referencia']); ?></small></td><td><?php echo h($item['quantidade']); ?></td><td><?php echo number_format((float) $item['unitario'], 4, ',', '.'); ?></td><td><?php echo number_format((float) $item['total'], 2, ',', '.'); ?></td><td><div class="acoes-linha"><button type="button" class="btn-secundario editar-peca" data-id="<?php echo h($item['id']); ?>" data-codigo="<?php echo h($item['codigo']); ?>" data-nome="<?php echo h($item['nome']); ?>" data-tipo="<?php echo h($tipo); ?>" data-qtd="<?php echo h($item['quantidade']); ?>" data-custo="<?php echo h($item['unitario']); ?>">Editar</button><button type="button" class="btn-excluir excluir-peca" data-id="<?php echo h($item['id']); ?>" data-tipo="<?php echo h($tipo); ?>" data-nome="<?php echo h($item['nome']); ?>">Excluir</button></div></td></tr>
                <?php } ?>
            </tbody></table></div><div class="total-container">Total: <?php echo number_format($total, 2, ',', '.'); ?></div>
        </section>
        <?php } ?>
    </div>
    <footer class="rodape-acoes"><form method="post" action="index.php" class="form-inline-acao"><input type="hidden" name="os" value="<?php echo h($os); ?>"><button class="btn-secundario" type="submit">← Voltar ao produto</button></form><?php if (count($pecas)) { ?><button class="btn-acao" id="conferirProduto" type="button">Conferir produto final →</button><?php } ?></footer>
    <div class="modal-backdrop-custom" id="modalPeca" role="dialog" aria-modal="true" aria-labelledby="tituloPeca" hidden><section class="modal-box"><h3 id="tituloPeca">Selecionar peça</h3><p id="nomePeca"></p>
        <form id="formPeca" method="post" action="../models/salvarTroca.php">
            <input type="hidden" name="acao" value="incluir_peca"><input type="hidden" name="os" value="<?php echo h($os); ?>"><input type="hidden" name="pai" value="<?php echo h($produto['codigo']); ?>"><input type="hidden" name="token" value="<?php echo h($token); ?>"><input type="hidden" name="id"><input type="hidden" name="codigo"><input type="hidden" name="tipo">
            <label for="quantidade">Quantidade</label><input id="quantidade" name="quantidade" type="text" inputmode="numeric" pattern="[1-9][0-9]{0,17}" maxlength="18" required value="1"><p class="resumo-custo" id="resumoCusto"></p>
            <div class="mensagem" role="alert"></div><div class="acoes-form"><button class="btn-secundario fechar-modal" type="button">Cancelar</button><button class="btn-acao" type="submit">Salvar peça</button></div>
        </form>
    </section></div>
    <div class="modal-backdrop-custom" id="modalExcluir" role="dialog" aria-modal="true" aria-labelledby="tituloExcluir" hidden><section class="modal-box"><h3 id="tituloExcluir">Excluir peça</h3><p id="nomeExcluir"></p><form id="formExcluir"><input type="hidden" name="acao" value="excluir_peca"><input type="hidden" name="os" value="<?php echo h($os); ?>"><input type="hidden" name="pai" value="<?php echo h($produto['codigo']); ?>"><input type="hidden" name="token" value="<?php echo h($token); ?>"><input type="hidden" name="id"><input type="hidden" name="tipo"><div class="mensagem" role="alert"></div><div class="acoes-form"><button class="btn-secundario fechar-modal" type="button">Cancelar</button><button class="btn-excluir" type="submit">Excluir esta linha</button></div></form></section></div>
    <div class="modal-backdrop-custom" id="modalConferencia" role="dialog" aria-modal="true" aria-labelledby="tituloConferencia" hidden><section class="modal-box"><h3 id="tituloConferencia">Conferir produto final</h3><p>As peças estão salvas. A etapa de conferência será disponibilizada na próxima fase do projeto.</p><button class="btn-acao fechar-modal" type="button">Fechar</button></section></div>
    <?php } else { ?><form method="post" action="index.php" class="form-inline-acao"><button class="btn-sair" type="submit">Voltar à consulta</button></form><?php } ?>
</main>
<script src="../assets/js/troca.js"></script>
</body></html>