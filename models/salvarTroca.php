<?php
header('Content-type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once '../config/database.php';
require_once 'testLogin.php';
require_once 'modtroca.php';
require_once 'criarOrcamento.php';
require_once 'criarItensOrcamento.php';
testLogin($conn);

// Le e normaliza um campo textual enviado pelo formulario POST.
function campoTroca($nome)
{
    return isset($_POST[$nome]) && is_string($_POST[$nome]) ? trim($_POST[$nome]) : '';
}

$transacao = false;
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        throw new Exception('Método inválido.');
    if (campoTroca('token') === '' || campoTroca('token') !== csrfTroca())
        throw new Exception('A sessão expirou. Atualize a página.');

    $acao = campoTroca('acao');
    $os = campoTroca('os');
    $destino = '../views/index.php';

    if ($acao === 'abrir_os') {
        $resultado = abrirOsTroca($conn, campoTroca('serie'), tecnicoLogado(), $_SESSION['login']);
        $os = $resultado['os'];
        $_SESSION['os_troca'] = $os;
        if ($resultado['nova'])
            $_SESSION['nova_os_troca'] = $os;
    } elseif ($acao === 'limpar_produto') {
        buscarOsTroca($conn, $os, true);
        executarTroca($conn, 'UPDATE TB_TROCA_PECAS SET PRODUTO_PAI = NULL WHERE OS = ?', array($os));
        unset($_SESSION['produto_troca'][$os]);
    } elseif ($acao === 'fechar_aviso') {
        if (isset($_SESSION['nova_os_troca']) && $_SESSION['nova_os_troca'] === $os)
            unset($_SESSION['nova_os_troca']);
    } elseif ($acao === 'gerar_orcamento') {
        if (!sqlsrv_begin_transaction($conn))
            throw new Exception('Não foi possível iniciar a gravação.');
        $transacao = true;
        buscarOsTroca($conn, $os, true);
        if (existeOrcamentoTroca($conn, $os))
            throw new Exception('Já existe um orçamento para esta OS.');
        $dadosOrcamento = linhasTroca($conn, 'SELECT
                SUM(QTD) AS QTProd,
                SUM(CUSTO) AS custo,
                COUNT(*) AS quantidade
            FROM TB_TROCA_PECAS
            WHERE OS = ?
              AND TIPO = ?', array($os, 'E'));
        if (!count($dadosOrcamento) || (int) $dadosOrcamento[0]['quantidade'] < 1)
            throw new Exception('Inclua pelo menos uma peça nova para gerar o orçamento.');
        $novOrc = proximoCodigoTroca($conn, 'TB02018', 5);
        $QTProd = $dadosOrcamento[0]['QTProd'];
        $custo = $dadosOrcamento[0]['custo'];
        executarCriarVenda($conn, $novOrc, $QTProd, $custo, $os, $_SESSION['login']);
        executarCriarItensVenda($conn, $_SESSION['login'], $novOrc, $os);
        if (!sqlsrv_commit($conn))
            throw new Exception('Não foi possível confirmar a gravação.');
        $transacao = false;
    } else {
        if (!in_array($acao, array('selecionar_produto', 'criar_produto', 'incluir_peca', 'editar_peca', 'excluir_peca'), true))
            throw new Exception('Ação inválida.');
        if (!sqlsrv_begin_transaction($conn))
            throw new Exception('Não foi possível iniciar a gravação.');
        $transacao = true;
        buscarOsTroca($conn, $os, true);

        if ($acao === 'selecionar_produto' || $acao === 'criar_produto') {
            if ($acao === 'criar_produto')
                $produto = criarProdutoTroca($conn, campoTroca('referencia'), campoTroca('nome'));
            else
                $produto = buscarProdutoTroca($conn, campoTroca('codigo'));

            $produto = selecionarProdutoTroca($conn, $os, trim($produto['codigo']));
            $_SESSION['produto_troca'][$os] = trim($produto['codigo']);
        } else {
            $tipo = campoTroca('tipo');
            if ($tipo !== 'E' && $tipo !== 'S')
                throw new Exception('Tipo de peça inválido.');

            if ($acao === 'incluir_peca') {
                $peca = buscarProdutoTroca($conn, campoTroca('codigo'), true);
                $quantidade = quantidadeTroca(campoTroca('quantidade'));
                if ($peca['custo'] === null || !is_numeric($peca['custo']))
                    throw new Exception('A peça não possui custo cadastrado. Confira o produto no ERP.');
                $pai = produtoPaiTroca($conn, $os);
                $paiCodigo = $pai ? trim($pai['codigo']) : null;
                $qtd = executarTroca($conn, 'INSERT INTO TB_TROCA_PECAS
                    (
                        OS,
                        PRODUTO_PECA,
                        PRODUTO_PAI,
                        REFERENCIA,
                        NOME_PRODUTO,
                        QTD,
                        CUSTO,
                        TIPO
                    )
                    SELECT
                        ?,
                        TB01010_CODIGO,
                        ?,
                        TB01010_REFERENCIA,
                        TB01010_NOME,
                        CONVERT(NUMERIC(18, 0), ?),
                        CONVERT(NUMERIC(18, 0), CONVERT(NUMERIC(18, 0), ?) * TB01010_CUSTO),
                        ?
                    FROM TB01010
                    WHERE TB01010_CODIGO = ?
                      AND TB01010_SITUACAO = \'A\'
                      AND TB01010_TIPOSUP NOT IN (9, 11);
                    SELECT @@ROWCOUNT AS linhas_alteradas', array($os, $paiCodigo, $quantidade, $quantidade, $tipo, trim($peca['codigo'])));
            } else {
                $id = campoTroca('id');
                if (!ctype_digit($id) || strlen($id) > 10 || (float) $id > 2147483647 || (float) $id < 1)
                    throw new Exception('Linha inválida.');
                $rows = linhasTroca($conn, 'SELECT
                        PRODUTO_PECA AS codigo
                    FROM TB_TROCA_PECAS WITH (UPDLOCK, HOLDLOCK)
                    WHERE ID = ?
                      AND OS = ?
                      AND TIPO = ?', array($id, $os, $tipo));
                if (count($rows) !== 1)
                    throw new Exception('Esta linha já foi removida ou não pertence ao processo. Atualize a página.');

                if ($acao === 'excluir_peca') {
                    $qtd = executarTroca($conn, 'DELETE FROM TB_TROCA_PECAS
                    WHERE ID = ?
                      AND OS = ?
                      AND TIPO = ?;
                    SELECT @@ROWCOUNT AS linhas_alteradas', array($id, $os, $tipo));
                } else {
                    $quantidade = quantidadeTroca(campoTroca('quantidade'));
                    $peca = buscarProdutoTroca($conn, trim($rows[0]['codigo']), true);
                    if ($peca['custo'] === null || !is_numeric($peca['custo']))
                        throw new Exception('A peça não possui custo cadastrado. Confira o produto no ERP.');
                    $qtd = executarTroca($conn, 'UPDATE T
                    SET QTD = CONVERT(NUMERIC(18, 0), ?),
                        CUSTO = CONVERT(NUMERIC(18, 0), CONVERT(NUMERIC(18, 0), ?) * P.TB01010_CUSTO)
                    FROM TB_TROCA_PECAS T
                    INNER JOIN TB01010 P
                        ON P.TB01010_CODIGO = T.PRODUTO_PECA
                    WHERE T.ID = ?
                      AND T.OS = ?
                      AND T.TIPO = ?;
                    SELECT @@ROWCOUNT AS linhas_alteradas', array($quantidade, $quantidade, $id, $os, $tipo));
                }
            }

            if ($qtd !== 1)
                throw new Exception('A peça não foi gravada. Atualize a tela para conferir os dados.');
        }

        if (!sqlsrv_commit($conn))
            throw new Exception('Não foi possível confirmar a gravação.');
        $transacao = false;
    }

    echo json_encode(array('sucesso' => true, 'destino' => $destino));
} catch (Exception $e) {
    if ($transacao)
        sqlsrv_rollback($conn);
    http_response_code(400);
    echo json_encode(array('sucesso' => false, 'mensagem' => $e->getMessage()));
}
