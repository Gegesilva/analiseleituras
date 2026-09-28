<?php
header('Content-type: text/html; charset=UTF-8');
require_once '../config/database.php';
require_once '../models/testLogin.php';
require_once '../models/modtroca.php';
testLogin($conn);
$erro = '';
$dadosOs = null;
$produto = null;
$os = isset($_GET['os']) && is_string($_GET['os']) ? trim($_GET['os']) : '';
try {
    $token = csrfTroca();
    if ($os !== '') {
        $dadosOs = buscarOsTroca($conn, $os);
        $produto = produtoPaiTroca($conn, $os);
    }
} catch (Exception $e) {
    $erro = $e->getMessage();
}
$novaOs = isset($_SESSION['nova_os_troca']) && $_SESSION['nova_os_troca'] === $os;
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Troca de peçasK</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/troca.css">
</head>

<body>
    <main class="page-shell troca-shell" data-os="<?php echo h($os); ?>"
        data-token="<?php echo isset($token) ? h($token) : ''; ?>">
        <header class="page-header"><span class="eyebrow">Troca de peçasK</span><a class="btn-sair"
                href="login.php">Sair</a></header>
        <section class="triagem-card">
            <div class="card-title">
                <div class="title-with-logo"><img src="../img/logo.jpg" alt="DATABIT">
                    <div>
                        <h2>Troca de peçasK</h2>
                        <p>Abertura da OS e seleção do produto</p>
                    </div>
                </div><?php if ($dadosOs) { ?><span class="contador">9K</span><?php } ?>
            </div>
            <div class="card-content">
                <div id="mensagem" class="mensagem<?php echo $erro ? ' erro' : ''; ?>" role="alert">
                    <?php echo h($erro); ?></div>
                <?php if (!$dadosOs) { ?>
                    <form id="formSerie" class="form-pesquisa" method="post" action="../models/salvarTroca.php">
                        <input type="hidden" name="acao" value="abrir_os"><input type="hidden" name="token"
                            value="<?php echo isset($token) ? h($token) : ''; ?>">
                        <div class="campo"><label for="serie">Número de série do equipamento</label><input id="serie"
                                name="serie" maxlength="50" required autofocus autocomplete="off"
                                placeholder="Digite ou bipe o número de série"></div>
                        <button class="btn-acao" type="submit">Abrir / consultar OS</button>
                    </form>
                    <p class="ajuda">Se já houver uma OS aberta em 9K para esta série, os dados serão apresentados para
                        continuar o processo.</p>
                <?php } else { ?>
                    <div class="resumo-os">
                        <div><small>OS</small><strong><?php echo h($dadosOs['os']); ?></strong></div>
                        <div><small>Série</small><strong><?php echo h($dadosOs['serie']); ?></strong></div>
                        <div class="equipamento"><small>Equipamento
                                original</small><strong><?php echo h($dadosOs['equipamento']); ?></strong></div><a
                            class="btn-secundario" href="index.php">Consultar outra série</a>
                    </div>
                <?php } ?>
            </div>
        </section>
        <?php if ($dadosOs && !$erro) { ?>
            <section class="triagem-card">
                <div class="card-title">
                    <div>
                        <h2>Produto do processo</h2>
                        <p>Pesquise pelo código, referência ou nome completo.</p>
                    </div>
                </div>
                <div class="card-content">
                    <?php if ($produto) { ?>
                        <div class="produto-selecionado"><span class="eyebrow">Produto selecionado</span>
                            <h3><?php echo h($produto['nome']); ?></h3>
                            <p>Código: <?php echo h($produto['codigo']); ?> · Referência:
                                <?php echo h($produto['referencia']); ?></p><a class="btn-acao"
                                href="pecas.php?os=<?php echo rawurlencode($os); ?>">Selecionar peças →</a>
                        </div><?php } ?>
                    <section class="busca-produtos" data-tipo="produto">
                        <form class="form-pesquisa">
                            <div class="campo"><label for="buscaProduto">Buscar produto</label><input id="buscaProduto"
                                    class="termo-busca" maxlength="60" autocomplete="off"
                                    placeholder="Código, referência ou nome completo"></div><button class="btn-acao"
                                type="submit">Pesquisar</button>
                        </form>
                        <div class="resultados" aria-live="polite"></div>
                        <div class="paginacao"></div>
                        <button class="btn-secundario criar-produto" type="button" hidden>Não encontrou? Criar
                            produto</button>
                    </section>
                </div>
            </section>
            <div class="modal-backdrop-custom" id="modalProduto" role="dialog" aria-modal="true"
                aria-labelledby="tituloProduto" hidden>
                <section class="modal-box">
                    <h3 id="tituloProduto">Criar produto</h3>
                    <p>Informe a referência e o nome completo.</p>
                    <form id="formProduto" method="post" action="../models/salvarTroca.php">
                        <input type="hidden" name="acao" value="criar_produto"><input type="hidden" name="os"
                            value="<?php echo h($os); ?>"><input type="hidden" name="token"
                            value="<?php echo h($token); ?>">
                        <label for="referencia">Referência</label><input id="referencia" name="referencia" maxlength="20"
                            required>
                        <label for="nomeProduto">Nome completo</label><input id="nomeProduto" name="nome" maxlength="60"
                            required>
                        <div class="mensagem" role="alert"></div>
                        <div class="acoes-form"><button class="btn-secundario fechar-modal"
                                type="button">Cancelar</button><button class="btn-acao" type="submit">Criar e
                                selecionar</button></div>
                    </form>
                </section>
            </div>
        <?php } ?>
        <?php if ($novaOs && $dadosOs) { ?>
            <div class="modal-backdrop-custom is-open" id="modalNovaOs" role="dialog" aria-modal="true"
                aria-labelledby="tituloNovaOs">
                <section class="modal-box">
                    <h3 id="tituloNovaOs">OS aberta com sucesso!</h3>
                    <p>Número da ordem de serviço</p><strong class="numero-os"><?php echo h($os); ?></strong><button
                        class="btn-acao" id="fecharNovaOs" type="button" autofocus>Fechar</button>
                    <div class="mensagem" role="alert"></div>
                </section>
            </div>
        <?php } ?>
    </main>
    <script src="../assets/js/troca.js"></script>
</body>

</html>