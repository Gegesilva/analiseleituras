<?php
header('Content-type: text/html; charset=UTF-8');
require_once '../config/database.php';
require_once '../models/testLogin.php';
require_once '../models/modtroca.php';
testLogin($conn);
$erro = '';
$dadosOs = null;
$produto = null;
$os = isset($_POST['os']) && is_string($_POST['os']) ? trim($_POST['os']) : (isset($_SESSION['os_troca']) ? trim($_SESSION['os_troca']) : '');
try {
    $token = csrfTroca();
    $dadosOs = buscarOsTroca($conn, $os);
    $produto = produtoPaiTroca($conn, $os);
} catch (Exception $e) { $erro = $e->getMessage(); }
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Selecionar equipamento · Troca de peças</title><link rel="stylesheet" href="../assets/css/index.css"><link rel="stylesheet" href="../assets/css/troca.css"></head><body>
<main class="page-shell troca-shell" data-os="<?php echo h($os); ?>" data-token="<?php echo isset($token) ? h($token) : ''; ?>">
<header class="page-header"><span class="eyebrow">Troca de peças</span></header>
<section class="triagem-card"><div class="card-title"><div><h2>Selecionar novo equipamento</h2><p>O equipamento será associado às peças desta OS.</p></div></div><div class="card-content"><div id="mensagem" class="mensagem<?php echo $erro ? ' erro' : ''; ?>" role="alert"><?php echo h($erro); ?></div>
<?php if ($dadosOs && !$erro) { ?><div class="resumo-os"><div><small>OS</small><strong><?php echo h($dadosOs['os']); ?></strong></div><div><small>Série</small><strong><?php echo h($dadosOs['serie']); ?></strong></div><div class="equipamento"><small>Equipamento original</small><strong><?php echo h($dadosOs['equipamento']); ?></strong></div></div><?php } ?>
</div></section>
<?php if ($dadosOs && !$erro) { ?>
<section class="triagem-card"><div class="card-title"><div><h2>Novo equipamento</h2><p>Pesquise por código, referência ou nome completo.</p></div></div><div class="card-content">
<?php if ($produto) { ?><div class="produto-selecionado"><span class="eyebrow">Equipamento atual</span><h3><?php echo h($produto['nome']); ?></h3><p>Código: <?php echo h($produto['codigo']); ?> · Referência: <?php echo h($produto['referencia']); ?></p></div><?php } ?>
<section class="busca-produtos" data-tipo="produto"><form class="form-pesquisa"><div class="campo"><label for="buscaProduto">Buscar equipamento</label><input id="buscaProduto" class="termo-busca" maxlength="60" autocomplete="off" placeholder="Código, referência ou nome completo"></div><button class="btn-acao" type="submit">Pesquisar</button></form><div class="resultados" aria-live="polite"></div><div class="paginacao"></div><button class="btn-secundario criar-produto" type="button" hidden>Não encontrou? Criar equipamento</button></section>
</div></section>
<footer class="rodape-acoes"><form method="post" action="index.php" class="form-inline-acao"><input type="hidden" name="os" value="<?php echo h($os); ?>"><button class="btn-secundario" type="submit">← Voltar às peças</button></form></footer>
<div class="modal-backdrop-custom" id="modalProduto" role="dialog" aria-modal="true" hidden><section class="modal-box"><h3>Criar equipamento</h3><form id="formProduto" method="post" action="../models/salvarTroca.php"><input type="hidden" name="acao" value="criar_produto"><input type="hidden" name="os" value="<?php echo h($os); ?>"><input type="hidden" name="token" value="<?php echo h($token); ?>"><label for="referencia">Referência</label><input id="referencia" name="referencia" maxlength="20" required><label for="nomeProduto">Nome completo</label><input id="nomeProduto" name="nome" maxlength="60" required><div class="mensagem" role="alert"></div><div class="acoes-form"><button class="btn-secundario fechar-modal" type="button">Cancelar</button><button class="btn-acao" type="submit">Criar e associar</button></div></form></section></div>
<?php } ?>
</main><script src="../assets/js/troca.js"></script></body></html>
