<?php
header('Content-type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once '../config/database.php';
require_once 'testLogin.php';
require_once 'modtroca.php';
testLogin($conn);
// Le e normaliza um campo textual enviado pelo formulario.
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
    $destino = '../views/index.php?os=' . rawurlencode($os);
    if ($acao === 'abrir_os') {
        $resultado = abrirOsTroca($conn, campoTroca('serie'), tecnicoLogado(), $_SESSION['login']);
        $os = $resultado['os'];
        if ($resultado['nova'])
            $_SESSION['nova_os_troca'] = $os;
        $destino = '../views/index.php?os=' . rawurlencode($os);
    } elseif ($acao === 'fechar_aviso') {
        if (isset($_SESSION['nova_os_troca']) && $_SESSION['nova_os_troca'] === $os)
            unset($_SESSION['nova_os_troca']);
    } else {
        if (!in_array($acao, array('selecionar_produto', 'criar_produto', 'incluir_peca', 'editar_peca', 'excluir_peca'), true))
            throw new Exception('Ação inválida.');
        if (!sqlsrv_begin_transaction($conn))
            throw new Exception('Não foi possível iniciar a gravação.');
        $transacao = true;
        buscarOsTroca($conn, $os, true);
        if ($acao === 'selecionar_produto' || $acao === 'criar_produto') {
            if ($acao === 'criar_produto') {
                $existentes = linhasTroca($conn, 'SELECT TOP 1
                OS
            FROM TB_TROCA_PECAS
            WHERE OS = ?', array($os));
                if (count($existentes))
                    throw new Exception('Remova as peças antes de criar outro produto para esta OS.');
                $produto = criarProdutoTroca($conn, campoTroca('referencia'), campoTroca('nome'));
            } else
                $produto = selecionarProdutoTroca($conn, $os, campoTroca('codigo'));
            $selecionado = trim($produto['codigo']);
        } else {
            $pai = produtoPaiTroca($conn, $os);
            if (!$pai || trim($pai['codigo']) !== campoTroca('pai'))
                throw new Exception('O produto selecionado mudou. Atualize a página.');
            $tipo = campoTroca('tipo');
            if ($tipo !== 'E' && $tipo !== 'S')
                throw new Exception('Tipo de peça inválido.');
            if ($acao === 'incluir_peca') {
                $peca = buscarProdutoTroca($conn, campoTroca('codigo'), true);
                $quantidade = quantidadeTroca(campoTroca('quantidade'));
                if ($peca['custo'] === null || !is_numeric($peca['custo']))
                    throw new Exception('A peça não possui custo cadastrado. Confira o produto no ERP.');
                $qtd = executarTroca($conn, "INSERT INTO TB_TROCA_PECAS
                    (OS, PRODUTO_PECA, PRODUTO_PAI, REFERENCIA, NOME_PRODUTO, QTD, CUSTO, TIPO)
                    SELECT ?, TB01010_CODIGO, ?, TB01010_REFERENCIA, TB01010_NOME,
                        CONVERT(NUMERIC(18,0), ?), CONVERT(NUMERIC(18,0), CONVERT(NUMERIC(18,0), ?) * TB01010_CUSTO), ?
                    FROM TB01010 WHERE TB01010_CODIGO = ? AND TB01010_SITUACAO = 'A' AND TB01010_TIPOSUP NOT IN (9,11);
                    SELECT @@ROWCOUNT linhas_alteradas", array($os, trim($pai['codigo']), $quantidade, $quantidade, $tipo, trim($peca['codigo'])));
            } else {
                $id = campoTroca('id');
                if (!ctype_digit($id) || strlen($id) > 10 || (float) $id > 2147483647 || (float) $id < 1)
                    throw new Exception('Linha inválida.');
                $rows = linhasTroca($conn, 'SELECT
                        PRODUTO_PECA AS codigo
                    FROM TB_TROCA_PECAS WITH (UPDLOCK, HOLDLOCK)
                    WHERE ID = ?
                      AND OS = ?
                      AND PRODUTO_PAI = ?
                      AND TIPO = ?', array($id, $os, trim($pai['codigo']), $tipo));
                if (count($rows) !== 1)
                    throw new Exception('Esta linha já foi removida ou não pertence ao processo. Atualize a página.');
                if ($acao === 'excluir_peca') {
                    $qtd = executarTroca($conn, 'DELETE FROM TB_TROCA_PECAS
                    WHERE ID = ?
                      AND OS = ?
                      AND PRODUTO_PAI = ?
                      AND TIPO = ?;
                        SELECT @@ROWCOUNT linhas_alteradas', array($id, $os, trim($pai['codigo']), $tipo));
                } else {
                    $quantidade = quantidadeTroca(campoTroca('quantidade'));
                    $peca = buscarProdutoTroca($conn, trim($rows[0]['codigo']), true);
                    if ($peca['custo'] === null || !is_numeric($peca['custo']))
                        throw new Exception('A peça não possui custo cadastrado. Confira o produto no ERP.');
                    $qtd = executarTroca($conn, 'UPDATE T SET QTD = CONVERT(NUMERIC(18,0), ?),
                        CUSTO = CONVERT(NUMERIC(18,0), CONVERT(NUMERIC(18,0), ?) * P.TB01010_CUSTO)
                        FROM TB_TROCA_PECAS T INNER JOIN TB01010 P ON P.TB01010_CODIGO = T.PRODUTO_PECA
                        WHERE T.ID = ? AND T.OS = ? AND T.PRODUTO_PAI = ? AND T.TIPO = ?;
                        SELECT @@ROWCOUNT linhas_alteradas', array($quantidade, $quantidade, $id, $os, trim($pai['codigo']), $tipo));
                }
            }
            if ($qtd !== 1)
                throw new Exception('A peça não foi gravada. Atualize a tela para conferir os dados.');
            $destino = '../views/pecas.php?os=' . rawurlencode($os);
        }
        if (!sqlsrv_commit($conn))
            throw new Exception('Não foi possível confirmar a gravação.');
        $transacao = false;
        if (isset($selecionado))
            $_SESSION['produto_troca'][$os] = $selecionado;
    }
    echo json_encode(array('sucesso' => true, 'destino' => $destino));
} catch (Exception $e) {
    if ($transacao)
        sqlsrv_rollback($conn);
    http_response_code(400);
    echo json_encode(array('sucesso' => false, 'mensagem' => $e->getMessage()));
}