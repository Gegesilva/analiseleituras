<?php
// Funcoes de consulta e gravacao no mesmo padrao procedural do Checkin/Checkout.
// Escapa valores antes de exibi-los no HTML.
function h($valor)
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
// Acrescenta os erros retornados pelo SQL Server a mensagem original.
function mensagemSqlTroca($mensagem)
{
    $erros = sqlsrv_errors();
    if (!$erros)
        return $mensagem;
    $detalhes = array();
    foreach ($erros as $erro)
        $detalhes[] = 'SQLSTATE ' . $erro['SQLSTATE'] . ' / Código ' . $erro['code'] . ' / ' . trim($erro['message']);
    return $mensagem . ' ' . implode(' ', $detalhes);
}
// Executa uma consulta SQL parametrizada e trata erros do banco.
function consultarTroca($conn, $sql, $params = array())
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) {
        error_log(print_r(sqlsrv_errors(), true));
        throw new Exception(mensagemSqlTroca('Não foi possível acessar os dados. Tente novamente.'));
    }
    return $stmt;
}
// Executa uma consulta e retorna todas as linhas como array PHP.
function linhasTroca($conn, $sql, $params = array())
{
    $stmt = consultarTroca($conn, $sql, $params);
    $rows = array();
    while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($row === false)
            throw new Exception('Não foi possível ler os dados.');
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}
// Executa comandos SQL e recupera a quantidade de linhas alteradas.
function executarTroca($conn, $sql, $params = array())
{
    $stmt = consultarTroca($conn, $sql, $params);
    $alteradas = 0;
    do {
        if (sqlsrv_num_fields($stmt) > 0) {
            while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
                if ($row === false)
                    throw new Exception(mensagemSqlTroca('Não foi possível conferir a gravação.'));
                if (isset($row['linhas_alteradas']))
                    $alteradas = (int) $row['linhas_alteradas'];
            }
        }
        $proximo = sqlsrv_next_result($stmt);
        if ($proximo === false) {
            error_log(print_r(sqlsrv_errors(), true));
            throw new Exception(mensagemSqlTroca('Não foi possível concluir a gravação.'));
        }
    } while ($proximo === true);
    sqlsrv_free_stmt($stmt);
    return $alteradas;
}
// Localiza uma OS aberta em 9K, com bloqueio opcional durante a transacao.
function buscarOsTroca($conn, $os, $bloquear = false)
{
    $rows = linhasTroca($conn, "SELECT 
                                    O.TB02115_CODIGO os, 
                                    O.TB02115_NUMSERIE serie,
                                    O.TB02115_PRODUTO produto, 
                                    O.TB02115_STATUS status,
                                     P.TB01010_NOME equipamento
                                FROM TB02115 O 
                                    " . ($bloquear ? 'WITH (UPDLOCK, HOLDLOCK)' : '') . "
                                LEFT JOIN TB01010 P ON P.TB01010_CODIGO = O.TB02115_PRODUTO
                                WHERE O.TB02115_CODIGO = ? 
                                    AND O.TB02115_DTFECHA IS NULL 
                                    AND O.TB02115_STATUS = '9K'", array($os));
    if (count($rows) !== 1)
        throw new Exception('OS não encontrada, encerrada ou fora do status 9K. Consulte a série novamente.');
    return $rows[0];
}
// O contador e o registro pertencem sempre a mesma transacao.
// Reserva o proximo codigo do contador.
// Busca um produto ativo, filtrando equipamento ou peca conforme o parametro.
function buscarProdutoTroca($conn, $codigo, $peca = false)
{
    $rows = linhasTroca($conn, "SELECT
            TB01010_CODIGO AS codigo,
            TB01010_REFERENCIA AS referencia,
            TB01010_NOME AS nome,
            TB01010_CUSTO AS custo
        FROM TB01010
        WHERE TB01010_CODIGO = ?
          AND TB01010_SITUACAO = 'A'
          AND TB01010_TIPOSUP " . ($peca ? 'NOT IN' : 'IN') . ' (9,11)', array($codigo));
    if (count($rows) !== 1)
        throw new Exception('Produto não encontrado ou indisponível para esta seleção.');
    return $rows[0];
}
// Recupera um produto que ja foi associado a uma OS, sem aplicar filtro de selecao.
function buscarProdutoAssociadoTroca($conn, $codigo)
{
    $rows = linhasTroca($conn, "SELECT
            TB01010_CODIGO AS codigo,
            TB01010_REFERENCIA AS referencia,
            TB01010_NOME AS nome,
            TB01010_CUSTO AS custo
        FROM TB01010
        WHERE TB01010_CODIGO = ?
          AND TB01010_SITUACAO = 'A'", array($codigo));
    if (count($rows) !== 1)
        throw new Exception('Produto associado não encontrado ou indisponível.');
    return $rows[0];
}
// Pesquisa produtos por codigo, referencia ou nome com paginacao.
function pesquisarProdutosTroca($conn, $termo, $peca, $pagina)
{
    $termo = str_replace(array('[', '%', '_'), array('[[]', '[%]', '[_]'), trim($termo));
    $filtro = '%' . $termo . '%';
    $inicio = ($pagina - 1) * 20;
    return linhasTroca(
        $conn,
        "WITH Produtos AS (
        SELECT TB01010_CODIGO codigo, 
            TB01010_REFERENCIA referencia, 
            TB01010_NOME nome,
            TB01010_CUSTO custo, 
            ROW_NUMBER() OVER (ORDER BY TB01010_NOME, TB01010_CODIGO) linha
        FROM TB01010 WHERE TB01010_SITUACAO = 'A' 
            AND TB01010_TIPOSUP " . ($peca ? 'NOT IN' : 'IN') . " (9,11)
            AND (TB01010_CODIGO LIKE ? OR TB01010_REFERENCIA LIKE ? OR TB01010_NOME LIKE ?))
        SELECT
            codigo,
            referencia,
            nome,
            custo
        FROM Produtos
        WHERE linha > ?
          AND linha <= ?
        ORDER BY linha",
        array($filtro, $filtro, $filtro, $inicio, $inicio + 21)
    );
}
// Recupera o produto pai salvo nas pecas ou na sessao atual.
function produtoPaiTroca($conn, $os)
{
    $rows = linhasTroca($conn, 'SELECT DISTINCT
            PRODUTO_PAI AS codigo
        FROM TB_TROCA_PECAS
        WHERE OS = ?
          AND PRODUTO_PAI IS NOT NULL', array($os));
    if (count($rows) > 1)
        throw new Exception('A OS possui mais de um produto pai. Confira os registros antes de continuar.');
    if (count($rows))
        return buscarProdutoAssociadoTroca($conn, trim($rows[0]['codigo']));
    if (isset($_SESSION['produto_troca'][$os]))
        return buscarProdutoAssociadoTroca($conn, $_SESSION['produto_troca'][$os]);
    return null;
}
// Valida o produto pai escolhido antes de liberar a selecao de pecas.
function associarProdutoTroca($conn, $os, $produto)
{
    buscarOsTroca($conn, $os, true);
    $rows = linhasTroca($conn, 'SELECT DISTINCT
            PRODUTO_PAI AS codigo
        FROM TB_TROCA_PECAS
        WHERE OS = ?
          AND PRODUTO_PAI IS NOT NULL', array($os));
    foreach ($rows as $row) {
        if (trim($row['codigo']) !== trim($produto['codigo']))
            throw new Exception('Remova as peças antes de trocar o produto selecionado.');
    }
    executarTroca($conn, 'UPDATE TB_TROCA_PECAS SET PRODUTO_PAI = ? WHERE OS = ? AND PRODUTO_PAI IS NULL', array(trim($produto['codigo']), $os));
    return $produto;
}
// Valida o produto pai escolhido antes de liberar a selecao de pecas.
function selecionarProdutoTroca($conn, $os, $codigo)
{
    $produto = buscarProdutoTroca($conn, $codigo);
    return associarProdutoTroca($conn, $os, $produto);
}
// Lista as pecas da OS para separar entradas e saidas na tela.
function pecasTroca($conn, $os)
{
    return linhasTroca($conn, 'SELECT T.ID id, T.PRODUTO_PECA codigo, T.PRODUTO_PAI pai,
        T.REFERENCIA referencia, T.NOME_PRODUTO nome, T.QTD quantidade, T.CUSTO total, T.TIPO tipo,
        P.TB01010_CUSTO unitario FROM TB_TROCA_PECAS T
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO = T.PRODUTO_PECA WHERE T.OS = ? ORDER BY T.TIPO, T.ID', array($os));
}
// Verifica se a OS ja possui um orcamento gravado.
function existeOrcamentoTroca($conn, $os)
{
    $rows = linhasTroca($conn, 'SELECT TOP 1 TB02018_CODIGO AS codigo
        FROM TB02018
        WHERE TB02018_OS = ?', array($os));
    return count($rows) > 0;
}
// Valida a quantidade inteira e positiva aceita pela tabela de pecas.
function quantidadeTroca($valor)
{
    if (!preg_match('/^[1-9][0-9]{0,17}$/D', $valor))
        throw new Exception('Informe uma quantidade inteira maior que zero, com até 18 dígitos.');
    return $valor;
}
// Cria ou recupera o token da sessao usado nas gravacoes POST.
function csrfTroca()
{
    if (empty($_SESSION['csrf_troca'])) {
        $bytes = false;
        if (function_exists('random_bytes')) {
            $bytes = random_bytes(32);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $forte = false;
            $bytes = openssl_random_pseudo_bytes(32, $forte);
            if (!$forte)
                $bytes = false;
        }
        // PHP 5.4 no Windows pode ter mcrypt nativo sem a extensao OpenSSL.
        if ($bytes === false && function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
            $bytes = mcrypt_create_iv(32, MCRYPT_DEV_URANDOM);
        }
        if (!is_string($bytes) || strlen($bytes) !== 32)
            throw new Exception('Não foi possível iniciar a sessão segura.');
        $_SESSION['csrf_troca'] = bin2hex($bytes);
    }
    return $_SESSION['csrf_troca'];
}
// Cria um produto ativo do tipo 9 usando o proximo codigo do ERP.
function criarProdutoTroca($conn, $referencia, $nome, $codigoProdutoOriginal, $os, $loginUser)
{
    $referencia = trim($referencia);
    $nome = trim($nome);
    if ($referencia === '' || $nome === '' || preg_match_all('/./us', $referencia, $letras) > 20 || preg_match_all('/./us', $nome, $letras) > 60) {
        throw new Exception('Informe referência (até 20 caracteres) e nome completo (até 60 caracteres).');
    }
    $custos = linhasTroca($conn, 'SELECT
            COALESCE(SUM(CASE WHEN TIPO = ? THEN CUSTO ELSE 0 END), 0) AS custoPecasNovas,
            COALESCE(SUM(CASE WHEN TIPO = ? THEN CUSTO ELSE 0 END), 0) AS custoPecasRetiradas
        FROM TB_TROCA_PECAS
        WHERE OS = ?', array('E', 'S', $os));
    if (count($custos) !== 1)
        throw new Exception('Não foi possível calcular o custo das peças.');
    $codigo = executarCriarProduto($conn, $loginUser, $nome, $referencia, $custos[0]['custoPecasNovas'], $custos[0]['custoPecasRetiradas'], $codigoProdutoOriginal);
    $produto = linhasTroca($conn, 'SELECT
            TB01010_CODIGO AS codigo,
            TB01010_REFERENCIA AS referencia,
            TB01010_NOME AS nome,
            TB01010_CUSTO AS custo
        FROM TB01010
        WHERE TB01010_CODIGO = ?', array($codigo));
    if (count($produto) !== 1)
        throw new Exception('O produto foi criado, mas não foi possível recuperar seus dados.');
    return $produto[0];
}
