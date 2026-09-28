<?php
// Funcoes de consulta e gravacao no mesmo padrao procedural do Checkin/Checkout.
// Escapa valores antes de exibi-los no HTML.
function h($valor) { return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'); }
// Executa uma consulta SQL parametrizada e trata erros do banco.
function consultarTroca($conn, $sql, $params = array())
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if (!$stmt) { error_log(print_r(sqlsrv_errors(), true)); throw new Exception('Não foi possível acessar os dados. Tente novamente.'); }
    return $stmt;
}
// Executa uma consulta e retorna todas as linhas como array PHP.
function linhasTroca($conn, $sql, $params = array())
{
    $stmt = consultarTroca($conn, $sql, $params);
    $rows = array();
    while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) !== null) {
        if ($row === false) throw new Exception('Não foi possível ler os dados.');
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
                if ($row === false) throw new Exception('Não foi possível conferir a gravação.');
                if (isset($row['linhas_alteradas'])) $alteradas = (int) $row['linhas_alteradas'];
            }
        }
        $proximo = sqlsrv_next_result($stmt);
        if ($proximo === false) { error_log(print_r(sqlsrv_errors(), true)); throw new Exception('Não foi possível concluir a gravação.'); }
    } while ($proximo === true);
    sqlsrv_free_stmt($stmt);
    return $alteradas;
}
// Localiza uma OS aberta em 9K, com bloqueio opcional durante a transacao.
function buscarOsTroca($conn, $os, $bloquear = false)
{
    $rows = linhasTroca($conn, "SELECT O.TB02115_CODIGO os, O.TB02115_NUMSERIE serie,
        O.TB02115_PRODUTO produto, O.TB02115_STATUS status, P.TB01010_NOME equipamento
        FROM TB02115 O " . ($bloquear ? 'WITH (UPDLOCK, HOLDLOCK)' : '') . "
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO = O.TB02115_PRODUTO
        WHERE O.TB02115_CODIGO = ? AND O.TB02115_DTFECHA IS NULL AND O.TB02115_STATUS = '9K'", array($os));
    if (count($rows) !== 1) throw new Exception('OS não encontrada, encerrada ou fora do status 9K. Consulte a série novamente.');
    return $rows[0];
}
// O contador e o registro pertencem sempre a mesma transacao.
// Reserva o proximo codigo do contador do ERP.
function proximoCodigoTroca($conn, $tabela, $tamanho)
{
    $rows = linhasTroca($conn, 'SELECT TB00002_COD codigo FROM TB00002 WITH (UPDLOCK, HOLDLOCK) WHERE TB00002_TABELA = ?', array($tabela));
    if (count($rows) !== 1 || !ctype_digit(trim($rows[0]['codigo']))) throw new Exception('Contador ausente ou inválido para ' . $tabela . '.');
    $numero = (int) trim($rows[0]['codigo']) + 1;
    if (strlen((string) $numero) > $tamanho) throw new Exception('O contador atingiu o limite de dígitos.');
    $codigo = str_pad($numero, $tamanho, '0', STR_PAD_LEFT);
    executarTroca($conn, 'UPDATE TB00002 SET TB00002_COD = ? WHERE TB00002_TABELA = ?', array($codigo, $tabela));
    return $codigo;
}
// Reutiliza uma OS 9K ou grava uma nova OS e seu primeiro historico.
function abrirOsTroca($conn, $serie, $tecnico, $usuario)
{
    $serie = trim($serie);
    if ($serie === '' || strlen($serie) > 50) throw new Exception('Informe um número de série válido.');
    if (!sqlsrv_begin_transaction($conn)) throw new Exception('Não foi possível iniciar a gravação.');
    try {
        $existentes = linhasTroca($conn, "SELECT TOP 1 TB02115_CODIGO os FROM TB02115 WITH (UPDLOCK, HOLDLOCK)
            WHERE TB02115_NUMSERIE = ? AND TB02115_DTFECHA IS NULL AND TB02115_STATUS = '9K'
            ORDER BY TB02115_CODIGO DESC", array($serie));
        if (count($existentes)) {
            if (!sqlsrv_commit($conn)) throw new Exception('Não foi possível concluir a consulta da OS.');
            return array('os' => trim($existentes[0]['os']), 'nova' => false);
        }
        if (trim($tecnico) === '') throw new Exception('O usuário precisa de um técnico vinculado para abrir a OS.');
        $equipamentos = linhasTroca($conn, 'SELECT DISTINCT TB02054_PRODUTO produto, TB02054_CODEMP empresa
            FROM TB02054 WITH (UPDLOCK, HOLDLOCK) WHERE TB02054_NUMSERIE = ? AND TB02054_QTPROD > TB02054_QTPRODS', array($serie));
        if (!count($equipamentos)) throw new Exception('Série não encontrada com saldo disponível no estoque.');
        if (count($equipamentos) !== 1) throw new Exception('Série vinculada a mais de um produto ou empresa. Confira o cadastro no ERP.');
        $os = proximoCodigoTroca($conn, 'TB02115', 6);
        $obs = 'OS aberta na aplicação Troca de peçasK';
        $qtd = executarTroca($conn, "INSERT INTO TB02115 (
            TB02115_CODIGO, TB02115_DTCAD, TB02115_CONTPB, TB02115_NUMSERIE,
            TB02115_STATUS, TB02115_OPCAD, TB02115_CODCLI, TB02115_TIPOINTERV,
            TB02115_PRODUTO, TB02115_CODTEC, TB02115_ATENDENTE, TB02115_PREVENTIVA,
            TB02115_DATA, TB02115_SITUACAO, TB02115_CODEMP, TB02115_OBS, TB02115_NOME,
            TB02115_CONTRATO, TB02115_SOLICITANTE, TB02115_CEP, TB02115_END, TB02115_CIDADE,
            TB02115_BAIRRO, TB02115_NUM, TB02115_COMP, TB02115_ORIGEM, TB02115_LOCAL)
            SELECT TOP 1 ?, GETDATE(), 0, S.TB02054_NUMSERIE, '9K', ?, '00000000', 'I',
                S.TB02054_PRODUTO, ?, 'PAINEL OS', 'E', GETDATE(), 'A', S.TB02054_CODEMP, ?, '',
                'ESTOQUE', 'APP Web AtualizaEquip', E.TB00012_CEP, E.TB00012_END, E.TB00012_CIDADE,
                E.TB00012_BAIRRO, E.TB00012_NUM, CAST(E.TB00012_COMP AS VARCHAR(20)), 'E', 'ESTOQUE'
            FROM TB02054 S LEFT JOIN TB00012 E ON E.TB00012_CODIGO = S.TB02054_CODEMP
                AND E.TB00012_TABELA = 'TB01007' AND E.TB00012_TIPO = '01'
            WHERE S.TB02054_NUMSERIE = ? AND S.TB02054_PRODUTO = ? AND S.TB02054_CODEMP = ?
                AND S.TB02054_QTPROD > S.TB02054_QTPRODS;
            SELECT @@ROWCOUNT linhas_alteradas", array($os, $usuario, $tecnico, $obs, $serie, $equipamentos[0]['produto'], $equipamentos[0]['empresa']));
        if ($qtd !== 1) throw new Exception('Não foi possível abrir a OS para esta série.');
        $qtd = executarTroca($conn, "INSERT INTO TB02130 (
            TB02130_CODIGO, TB02130_DATA, TB02130_USER, TB02130_STATUS, TB02130_NOME,
            TB02130_OBS, TB02130_CODTEC, TB02130_PREVISAO, TB02130_NOMETEC, TB02130_TIPO,
            TB02130_CODCAD, TB02130_CODEMP, TB02130_DATAEXEC, TB02130_HORASCOM, TB02130_HORASFIM)
            SELECT O.TB02115_CODIGO, GETDATE(), ?, '9K', S.TB01073_NOME, ?, O.TB02115_CODTEC,
                NULL, T.TB01024_NOME, 'O', O.TB02115_CODCLI, O.TB02115_CODEMP, GETDATE(), '00:00', '00:00'
            FROM TB02115 O LEFT JOIN TB01073 S ON S.TB01073_CODIGO = O.TB02115_STATUS
            LEFT JOIN TB01024 T ON T.TB01024_CODIGO = O.TB02115_CODTEC WHERE O.TB02115_CODIGO = ?;
            SELECT @@ROWCOUNT linhas_alteradas", array($usuario, $obs, $os));
        if ($qtd !== 1) throw new Exception('Não foi possível gravar o histórico da OS.');
        if (!sqlsrv_commit($conn)) throw new Exception('Não foi possível confirmar a abertura da OS.');
        return array('os' => $os, 'nova' => true);
    } catch (Exception $e) { sqlsrv_rollback($conn); throw $e; }
}
// Busca um produto ativo, filtrando equipamento ou peca conforme o parametro.
function buscarProdutoTroca($conn, $codigo, $peca = false)
{
    $rows = linhasTroca($conn, "SELECT TB01010_CODIGO codigo, TB01010_REFERENCIA referencia,
        TB01010_NOME nome, TB01010_CUSTO custo FROM TB01010 WHERE TB01010_CODIGO = ?
        AND TB01010_SITUACAO = 'A' AND TB01010_TIPOSUP " . ($peca ? 'NOT IN' : 'IN') . ' (9,11)', array($codigo));
    if (count($rows) !== 1) throw new Exception('Produto não encontrado ou indisponível para esta seleção.');
    return $rows[0];
}
// Pesquisa produtos por codigo, referencia ou nome com paginacao.
function pesquisarProdutosTroca($conn, $termo, $peca, $pagina)
{
    $termo = str_replace(array('[', '%', '_'), array('[[]', '[%]', '[_]'), trim($termo));
    $filtro = '%' . $termo . '%';
    $inicio = ($pagina - 1) * 20;
    return linhasTroca($conn, "WITH Produtos AS (
        SELECT TB01010_CODIGO codigo, TB01010_REFERENCIA referencia, TB01010_NOME nome,
            TB01010_CUSTO custo, ROW_NUMBER() OVER (ORDER BY TB01010_NOME, TB01010_CODIGO) linha
        FROM TB01010 WHERE TB01010_SITUACAO = 'A' AND TB01010_TIPOSUP " . ($peca ? 'NOT IN' : 'IN') . " (9,11)
            AND (TB01010_CODIGO LIKE ? OR TB01010_REFERENCIA LIKE ? OR TB01010_NOME LIKE ?))
        SELECT codigo, referencia, nome, custo FROM Produtos WHERE linha > ? AND linha <= ? ORDER BY linha",
        array($filtro, $filtro, $filtro, $inicio, $inicio + 21));
}
// Recupera o produto pai salvo nas pecas ou na sessao atual.
function produtoPaiTroca($conn, $os)
{
    $rows = linhasTroca($conn, 'SELECT DISTINCT PRODUTO_PAI codigo FROM TB_TROCA_PECAS WHERE OS = ?', array($os));
    if (count($rows) > 1) throw new Exception('A OS possui mais de um produto pai. Confira os registros antes de continuar.');
    if (count($rows)) return buscarProdutoTroca($conn, trim($rows[0]['codigo']));
    if (isset($_SESSION['produto_troca'][$os])) return buscarProdutoTroca($conn, $_SESSION['produto_troca'][$os]);
    return null;
}
// Valida o produto pai escolhido antes de liberar a selecao de pecas.
function selecionarProdutoTroca($conn, $os, $codigo)
{
    buscarOsTroca($conn, $os, true);
    $produto = buscarProdutoTroca($conn, $codigo);
    $rows = linhasTroca($conn, 'SELECT DISTINCT PRODUTO_PAI codigo FROM TB_TROCA_PECAS WHERE OS = ?', array($os));
    foreach ($rows as $row) {
        if (trim($row['codigo']) !== $codigo) throw new Exception('Remova as peças antes de trocar o produto selecionado.');
    }
    return $produto;
}
// Lista as pecas da OS para separar entradas e saidas na tela.
function pecasTroca($conn, $os)
{
    return linhasTroca($conn, 'SELECT T.ID id, T.PRODUTO_PECA codigo, T.PRODUTO_PAI pai,
        T.REFERENCIA referencia, T.NOME_PRODUTO nome, T.QTD quantidade, T.CUSTO total, T.TIPO tipo,
        P.TB01010_CUSTO unitario FROM TB_TROCA_PECAS T
        LEFT JOIN TB01010 P ON P.TB01010_CODIGO = T.PRODUTO_PECA WHERE T.OS = ? ORDER BY T.TIPO, T.ID', array($os));
}
// Valida a quantidade inteira e positiva aceita pela tabela de pecas.
function quantidadeTroca($valor)
{
    if (!preg_match('/^[1-9][0-9]{0,17}$/D', $valor)) throw new Exception('Informe uma quantidade inteira maior que zero, com até 18 dígitos.');
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
            if (!$forte) $bytes = false;
        }
        // PHP 5.4 no Windows pode ter mcrypt nativo sem a extensao OpenSSL.
        if ($bytes === false && function_exists('mcrypt_create_iv') && defined('MCRYPT_DEV_URANDOM')) {
            $bytes = mcrypt_create_iv(32, MCRYPT_DEV_URANDOM);
        }
        if (!is_string($bytes) || strlen($bytes) !== 32) throw new Exception('Não foi possível iniciar a sessão segura.');
        $_SESSION['csrf_troca'] = bin2hex($bytes);
    }
    return $_SESSION['csrf_troca'];
}
// Cria um produto ativo do tipo 9 usando o proximo codigo do ERP.
function criarProdutoTroca($conn, $referencia, $nome)
{
    $referencia = trim($referencia); $nome = trim($nome);
    if ($referencia === '' || $nome === '' || preg_match_all('/./us', $referencia, $letras) > 20 || preg_match_all('/./us', $nome, $letras) > 60) {
        throw new Exception('Informe referência (até 20 caracteres) e nome completo (até 60 caracteres).');
    }
    $codigo = proximoCodigoTroca($conn, 'TB01010', 5);
    $qtd = executarTroca($conn, "INSERT INTO TB01010 (TB01010_CODIGO, TB01010_REFERENCIA, TB01010_NOME, TB01010_SITUACAO, TB01010_TIPOSUP)
        VALUES (?, ?, ?, 'A', 9); SELECT @@ROWCOUNT linhas_alteradas", array($codigo, $referencia, $nome));
    if ($qtd !== 1) throw new Exception('Não foi possível cadastrar o produto.');
    return buscarProdutoTroca($conn, $codigo);
}