<?php
header('Content-type: text/html; charset=UTF-8');
require_once '../config/database.php';
require_once '../models/testLogin.php';
require_once '../models/modtroca.php';
require_once '../models/criaOS.php';
testLogin($conn);
$erro = '';
$dadosOs = null;
$produto = null;
$pecas = array();
$orcamento = false;
$transacao = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['os']) || trim((string) $_POST['os']) === ''))
    unset($_SESSION['os_troca'], $_SESSION['nova_os_troca']);
$os = isset($_POST['os']) && is_string($_POST['os']) ? trim($_POST['os']) : (isset($_SESSION['os_troca']) ? trim($_SESSION['os_troca']) : '');
try {
    $token = csrfTroca();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serie']) && is_string($_POST['serie'])) {
        $serie = trim($_POST['serie']);
        if (!isset($_POST['token']) || !is_string($_POST['token']) || $_POST['token'] !== $token)
            throw new Exception('A sessão expirou. Atualize a página.');
        $os = '';
        if ($serie === '' || strlen($serie) > 50)
            throw new Exception('Informe um número de série válido.');
        $sql = "SELECT TOP 1
            TB02115_CODIGO AS os
        FROM TB02115
        WHERE TB02115_NUMSERIE = ?
          AND TB02115_DTFECHA IS NULL
          AND TB02115_STATUS = '9K'
        ORDER BY TB02115_CODIGO DESC";
        $existentes = linhasTroca($conn, $sql, array($serie));
        if (count($existentes)) {
            $os = trim($existentes[0]['os']);
            $_SESSION['os_troca'] = $os;
        } else {
            $tecnico = tecnicoLogado();
            if (trim($tecnico) === '')
                throw new Exception('O usuário precisa de um técnico vinculado para abrir a OS.');
            if (!sqlsrv_begin_transaction($conn))
                throw new Exception('Não foi possível iniciar a gravação.');
            $transacao = true;
            $codigo = executarCriarOS($conn, $serie, $tecnico, $_SESSION['login']);
            if (!sqlsrv_commit($conn))
                throw new Exception('Não foi possível confirmar a abertura da OS.');
            $transacao = false;
            $os = $codigo;
            $_SESSION['os_troca'] = $os;
            $_SESSION['nova_os_troca'] = $os;
        }
        header('Location: index.php');
        exit;
    }
    if ($os !== '') {
        $dadosOs = buscarOsTroca($conn, $os);
        $produto = produtoPaiTroca($conn, $os);
        $pecas = pecasTroca($conn, $os);
        $orcamento = existeOrcamentoTroca($conn, $os);
    }
} catch (Exception $e) {
    if ($transacao)
        sqlsrv_rollback($conn);
    $erro = $e->getMessage();
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Troca de peças</title>
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/troca.css">
</head>

<body>
    <main class="page-shell troca-shell" data-os="<?php echo h($os); ?>"
        data-token="<?php echo isset($token) ? h($token) : ''; ?>">
        <header class="page-header"><span class="eyebrow">Troca de peças</span></header>
        <section class="triagem-card">
            <div class="card-title">
                <div class="title-with-logo"><img src="../img/logo.jpg" alt="DATABIT">
                    <div>
                        <h2>Troca de peças</h2>
                        <p>Abertura da OS e seleção das peças</p>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <div id="mensagem" class="mensagem<?php echo $erro ? ' erro' : ''; ?>" role="alert">
                    <?php echo h($erro); ?></div>
                <?php if (!$dadosOs) { ?>
                        <form id="formSerie" class="form-pesquisa" method="post" action="index.php"><input
                            type="hidden" name="acao" value="abrir_os"><input type="hidden" name="token"
                            value="<?php echo h($token); ?>">
                        <div class="campo"><label for="serie">Número de série do equipamento</label><input id="serie"
                                name="serie" maxlength="50" required autofocus autocomplete="off"
                                placeholder="Digite ou bipe o número de série"></div><button class="btn-acao"
                            type="submit">Abrir / consultar OS</button>
                    </form>
                <?php } else { ?>
                    <div class="resumo-os">
                        <div><small>OS</small><strong><?php echo h($dadosOs['os']); ?></strong></div>
                        <div><small>Série</small><strong><?php echo h($dadosOs['serie']); ?></strong></div>
                        <div class="equipamento"><small>Equipamento
                                original</small><strong><?php echo h($dadosOs['equipamento']); ?></strong></div>
                        <form method="post" action="index.php" class="form-inline-acao"><button class="btn-secundario"
                                type="submit">Consultar outra série</button></form>
                    </div>
                <?php } ?>
            </div>
        </section>
        <?php if ($dadosOs && !$erro) { ?>
            <section class="triagem-card">
                <div class="card-title">
                    <div>
                        <h2>Peças</h2>
                        <p>Inclua peças novas e retiradas para esta OS.</p>
                    </div>
                </div>
                <div class="card-content">
                    <?php if ($produto) { ?>
                        <div class="produto-selecionado">
                            <form id="formLimparProduto" method="post" action="../models/salvarTroca.php"
                                class="produto-fechar"><input type="hidden" name="acao" value="limpar_produto"><input
                                    type="hidden" name="os" value="<?php echo h($os); ?>"><input type="hidden" name="token"
                                    value="<?php echo h($token); ?>"><button type="submit" aria-label="Remover equipamento"
                                    title="Remover equipamento">×</button></form><span class="eyebrow">Novo equipamento
                                associado</span>
                            <h3><?php echo h($produto['nome']); ?></h3>
                            <p>Código: <?php echo h($produto['codigo']); ?> · Referência:
                                <?php echo h($produto['referencia']); ?></p>
                        </div><?php } ?>
                    <div class="pecas-grid">
                        <?php foreach (array('E' => 'Peças novas', 'S' => 'Peças retiradas') as $tipo => $titulo) {
                            $itens = array();
                            $total = 0;
                            foreach ($pecas as $peca) {
                                if ($peca['tipo'] === $tipo) {
                                    $itens[] = $peca;
                                    $total += (float) $peca['total'];
                                }
                            } ?>
                            <section class="triagem-card">
                                <div class="card-title">
                                    <div>
                                        <h2><?php echo h($titulo); ?></h2>
                                        <p><?php echo $tipo === 'E' ? 'Componentes colocados no equipamento' : 'Componentes removidos do equipamento'; ?>
                                        </p>
                                    </div><span class="contador"><?php echo count($itens); ?></span>
                                </div>
                                <div class="card-content">
                                    <section class="busca-produtos" data-tipo="<?php echo h($tipo); ?>">
                                        <form class="form-pesquisa">
                                            <div class="campo"><label for="busca<?php echo h($tipo); ?>">Buscar
                                                    peça</label><input id="busca<?php echo h($tipo); ?>" class="termo-busca"
                                                    maxlength="60" autocomplete="off" placeholder="Código, referência ou nome">
                                            </div><button class="btn-acao" type="submit">Pesquisar</button>
                                        </form>
                                        <div class="resultados" aria-live="polite"></div>
                                        <div class="paginacao"></div>
                                    </section>
                                </div>
                                <div class="table-wrap">
                                    <table class="triagem-table">
                                        <thead>
                                            <tr>
                                                <th>Produto</th>
                                                <th>Qtd.</th>
                                                <th>Custo unitário</th>
                                                <th>Custo total</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!count($itens)) { ?>
                                                <tr>
                                                    <td colspan="5" class="empty-state">Nenhuma peça incluída.</td>
                                                </tr><?php } ?>
                                            <?php foreach ($itens as $item) { ?>
                                                <tr>
                                                    <td class="produto-celula">
                                                        <strong><?php echo h($item['nome']); ?></strong><small><?php echo h($item['codigo']); ?>
                                                            · <?php echo h($item['referencia']); ?></small></td>
                                                    <td><?php echo h($item['quantidade']); ?></td>
                                                    <td><?php echo number_format((float) $item['unitario'], 4, ',', '.'); ?></td>
                                                    <td><?php echo number_format((float) $item['total'], 2, ',', '.'); ?></td>
                                                    <td>
                                                        <?php if (!$orcamento || $tipo !== 'E') { ?>
                                                        <div class="acoes-linha"><button type="button"
                                                                class="btn-secundario editar-peca"
                                                                data-id="<?php echo h($item['id']); ?>"
                                                                data-codigo="<?php echo h($item['codigo']); ?>"
                                                                data-nome="<?php echo h($item['nome']); ?>"
                                                                data-tipo="<?php echo h($tipo); ?>"
                                                                data-qtd="<?php echo h($item['quantidade']); ?>"
                                                                data-custo="<?php echo h($item['unitario']); ?>">Editar</button><button
                                                                type="button" class="btn-excluir excluir-peca"
                                                                data-id="<?php echo h($item['id']); ?>"
                                                                data-tipo="<?php echo h($tipo); ?>"
                                                                data-nome="<?php echo h($item['nome']); ?>">Excluir</button></div>
                                                        <?php } ?>
                                                    </td>
                                                </tr><?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="total-container"><?php if ($tipo === 'E' && count($itens)) { ?><?php if (!$orcamento) { ?><button
                                            class="btn-acao gerar-orcamento" type="button">Gerar
                                            orçamento</button><?php } else { ?><span>Orçamento gerado</span><?php } ?><?php } ?><span>Total:
                                        <?php echo number_format($total, 2, ',', '.'); ?></span></div>
                            </section>
                        <?php } ?>
                    </div>
                    <div class="acao-equipamento">
                        <form method="post" action="pecas.php" class="form-inline-acao"><input type="hidden" name="os"
                                value="<?php echo h($os); ?>"><button class="btn-acao"
                                type="submit"><?php echo $produto ? 'Equipamento' : 'Selecionar novo equipamento'; ?>
                                →</button></form>
                    </div>
                </div>
            </section>
        <?php } ?>
        <div class="modal-backdrop-custom" id="modalPeca" role="dialog" aria-modal="true" hidden>
            <section class="modal-box">
                <h3 id="tituloPeca">Selecionar peça</h3>
                <p id="nomePeca"></p>
                <form id="formPeca" method="post" action="../models/salvarTroca.php"><input type="hidden" name="acao"
                        value="incluir_peca"><input type="hidden" name="os" value="<?php echo h($os); ?>"><input
                        type="hidden" name="pai" value="<?php echo $produto ? h($produto['codigo']) : ''; ?>"><input
                        type="hidden" name="token" value="<?php echo isset($token) ? h($token) : ''; ?>"><input
                        type="hidden" name="id"><input type="hidden" name="codigo"><input type="hidden"
                        name="tipo"><label for="quantidade">Quantidade</label><input id="quantidade" name="quantidade"
                        type="text" inputmode="numeric" pattern="[1-9][0-9]{0,17}" maxlength="18" required value="1">
                    <p class="resumo-custo" id="resumoCusto"></p>
                    <div class="mensagem" role="alert"></div>
                    <div class="acoes-form"><button class="btn-secundario fechar-modal"
                            type="button">Cancelar</button><button class="btn-acao" type="submit">Salvar peça</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="modal-backdrop-custom" id="modalExcluir" role="dialog" aria-modal="true" hidden>
            <section class="modal-box">
                <h3>Excluir peça</h3>
                <p id="nomeExcluir"></p>
                <form id="formExcluir"><input type="hidden" name="acao" value="excluir_peca"><input type="hidden"
                        name="os" value="<?php echo h($os); ?>"><input type="hidden" name="pai"
                        value="<?php echo $produto ? h($produto['codigo']) : ''; ?>"><input type="hidden" name="token"
                        value="<?php echo isset($token) ? h($token) : ''; ?>"><input type="hidden" name="id"><input
                        type="hidden" name="tipo">
                    <div class="mensagem" role="alert"></div>
                    <div class="acoes-form"><button class="btn-secundario fechar-modal"
                            type="button">Cancelar</button><button class="btn-excluir" type="submit">Excluir esta
                            linha</button></div>
                </form>
            </section>
        </div>
        <div class="modal-backdrop-custom" id="modalOrcamento" role="dialog" aria-modal="true" hidden>
            <section class="modal-box">
                <h3>Gerar orçamento</h3>
                <p>Confirma a geração do orçamento com as peças novas selecionadas?</p>
                <form id="formOrcamento" method="post" action="../models/salvarTroca.php"><input type="hidden"
                        name="acao" value="gerar_orcamento"><input type="hidden" name="os"
                        value="<?php echo h($os); ?>"><input type="hidden" name="token"
                        value="<?php echo h($token); ?>">
                    <div class="mensagem" role="alert"></div><div class="acoes-form"><button class="btn-secundario fechar-modal"
                            type="button">Cancelar</button><button class="btn-acao confirmar-orcamento" type="button">Confirmar</button>
                    </div>
                </form>
            </section>
        </div>
    </main>
    <script src="../assets/js/troca.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/troca.js'); ?>"></script>
</body>

</html>
