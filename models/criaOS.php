<?php

function proximoCodigoOS($conn)
{
    $rows = linhasTroca($conn, 'SELECT
            TB00002_COD AS codigo
        FROM TB00002 WITH (UPDLOCK, HOLDLOCK)
        WHERE TB00002_TABELA = ?', array('TB02115'));
    $contador = count($rows) === 1 ? trim($rows[0]['codigo']) : '';
    $parteNumerica = substr($contador, 1);
    if ($contador === '' || $parteNumerica === '' || !ctype_digit($parteNumerica))
        throw new Exception('Contador ausente ou inválido para TB02115.');
    $prefixo = substr($contador, 0, 1);
    $numero = (int) $parteNumerica + 1;
    if (strlen((string) $numero) > strlen($parteNumerica))
        throw new Exception('O contador atingiu o limite de dígitos.');
    $codigo = $prefixo . str_pad($numero, strlen($parteNumerica), '0', STR_PAD_LEFT);
    executarTroca($conn, 'UPDATE TB00002
        SET TB00002_COD = ?
        WHERE TB00002_TABELA = ?', array($codigo, 'TB02115'));
    return $codigo;
}

function executarCriarOS($conn, $serie, $tecnico, $loginUser, $produto, $empresa, $cep, $endereco, $cidade, $bairro, $numero, $complemento)
{
    $codigo = proximoCodigoOS($conn);
    $sql = "INSERT INTO [dbo].[TB02115]
           ([TB02115_CODIGO]
           ,[TB02115_DTCAD]
           ,[TB02115_CONTPB]
           ,[TB02115_NUMSERIE]
           ,[TB02115_STATUS]
           ,[TB02115_OPCAD]
           ,[TB02115_CODCLI]
           ,[TB02115_TIPOINTERV]
           ,[TB02115_PRODUTO]
           ,[TB02115_CODTEC]
           ,[TB02115_ATENDENTE]
           ,[TB02115_PREVENTIVA]
           ,[TB02115_DATA]
           ,[TB02115_SITUACAO]
           ,[TB02115_CODEMP]
           ,[TB02115_OBS]
           ,[TB02115_NOME]
           ,[TB02115_CONTRATO]
           ,[TB02115_SOLICITANTE]
           ,[TB02115_CEP]
           ,[TB02115_END]
           ,[TB02115_CIDADE]
           ,[TB02115_BAIRRO]
           ,[TB02115_NUM]
           ,[TB02115_COMP]
           ,[TB02115_ORIGEM]
           ,[TB02115_LOCAL])
     VALUES
           (?, GETDATE(), 0, ?, '9K', ?, '00000000', 'I', ?, ?, 'PAINEL OS', 'E', GETDATE(), 'A', ?,
            'OS aberta na aplicação Troca de peças', '', 'ESTOQUE', 'APP Web AtualizaEquip', ?, ?, ?, ?, ?, ?, 'E', 'ESTOQUE')";

    //parametros
    $stmt = consultarTroca($conn, $sql, array($codigo, $serie, $loginUser, $produto, $tecnico, $empresa, $cep, $endereco, $cidade, $bairro, $numero, $complemento));

    sqlsrv_free_stmt($stmt);

    return $codigo;
}
