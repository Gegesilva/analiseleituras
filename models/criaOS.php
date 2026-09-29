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

function executarCriarOS($conn, $serie, $tecnico, $loginUser)
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
    SELECT TOP 1
           ?, --TB02115_CODIGO
           GETDATE(), --TB02115_DTCAD
           0, --TB02115_CONTPB
           TB02054_NUMSERIE, --TB02115_NUMSERIE
           '9K', --TB02115_STATUS
           ?, --TB02115_OPCAD
           '00000000', --TB02115_CODCLI
           'I', --TB02115_TIPOINTERV
           TB02054_PRODUTO, --TB02115_PRODUTO
           ?, --TB02115_CODTEC
           'PAINEL OS', --TB02115_ATENDENTE
           'E', --TB02115_PREVENTIVA
           GETDATE(), --TB02115_DATA
           'A', --TB02115_SITUACAO
           TB02054_CODEMP, --TB02115_CODEMP
           'OS aberta na aplicação Troca de peças', --TB02115_OBS
           '', --TB02115_NOME
           'ESTOQUE', --TB02115_CONTRATO
           'APP Web AtualizaEquip', --TB02115_SOLICITANTE
            NULL, --TB02115_CEP
            NULL, --TB02115_END
            NULL, --TB02115_CIDADE
            NULL, --TB02115_BAIRRO
            NULL, --TB02115_NUM
            NULL, --TB02115_COMP
            'E', --TB02115_ORIGEM
            'ESTOQUE' --TB02115_LOCAL
    FROM TB02054
    WHERE TB02054_NUMSERIE = ?
      AND TB02054_QTPROD > TB02054_QTPRODS;
    SELECT @@ROWCOUNT linhas_alteradas";

    //parametros
    $qtd = executarTroca($conn, $sql, array($codigo, $loginUser, $tecnico, $serie));
    if ($qtd !== 1)
        throw new Exception('Não foi possível abrir a OS para esta série.');

    $sql = "INSERT INTO TB02130 (
                            TB02130_CODIGO, 
                            TB02130_DATA, 
                            TB02130_USER, 
                            TB02130_STATUS, 
                            TB02130_NOME,
                            TB02130_OBS, 
                            TB02130_CODTEC, 
                            TB02130_PREVISAO, 
                            TB02130_NOMETEC, 
                            TB02130_TIPO,
                            TB02130_CODCAD, 
                            TB02130_CODEMP, 
                            TB02130_DATAEXEC, 
                            TB02130_HORASCOM, 
                            TB02130_HORASFIM
                            )
                            SELECT 
                                O.TB02115_CODIGO, -- TB02130_CODIGO
                                GETDATE(), -- TB02130_DATA
                                ?, -- TB02130_USER
                                '9K', -- TB02130_STATUS
                                S.TB01073_NOME, -- TB02130_NOME
                                ?, -- TB02130_OBS
                                O.TB02115_CODTEC, -- TB02130_CODTEC
                                NULL, -- TB02130_PREVISAO
                                T.TB01024_NOME, -- TB02130_NOMETEC
                                'O', -- TB02130_TIPO
                                O.TB02115_CODCLI, -- TB02130_CODCAD
                                O.TB02115_CODEMP, -- TB02130_CODEMP
                                GETDATE(), -- TB02130_DATAEXEC
                                '00:00', -- TB02130_HORASCOM
                                '00:00' -- TB02130_HORASFIM
                            FROM TB02115 O LEFT JOIN TB01073 S ON S.TB01073_CODIGO = O.TB02115_STATUS
                            LEFT JOIN TB01024 T ON T.TB01024_CODIGO = O.TB02115_CODTEC WHERE O.TB02115_CODIGO = ?;
                            SELECT @@ROWCOUNT linhas_alteradas";
    $qtd = executarTroca($conn, $sql, array($loginUser, 'OS aberta na aplicação Troca de peças', $codigo));
    if ($qtd !== 1)
        throw new Exception('Não foi possível gravar o histórico da OS.');

    return $codigo;
}
