<?php

function proximoCodigoVenda($conn)
{
    $rows = linhasTroca($conn, 'SELECT
            TB00002_COD AS codigo
        FROM TB00002 WITH (UPDLOCK, HOLDLOCK)
        WHERE TB00002_TABELA = ?', array('TB02021'));
    $contador = count($rows) === 1 ? trim($rows[0]['codigo']) : '';
    $parteNumerica = substr($contador, 1);
    if ($contador === '' || $parteNumerica === '' || !ctype_digit($parteNumerica))
        throw new Exception('Contador ausente ou inválido para TB02021.');
    $prefixo = substr($contador, 0, 1);
    $numero = (int) $parteNumerica + 1;
    if (strlen((string) $numero) > strlen($parteNumerica))
        throw new Exception('O contador atingiu o limite de dígitos.');
    $codigo = $prefixo . str_pad($numero, strlen($parteNumerica), '0', STR_PAD_LEFT);
    executarTroca($conn, 'UPDATE TB00002
        SET TB00002_COD = ?
        WHERE TB00002_TABELA = ?', array($codigo, 'TB02021'));
    return $codigo;
}

function executarCriarVenda($conn, $loginUser, $codigoOS)
{
    $novVend = proximoCodigoVenda($conn);
    $sql = " INSERT INTO TB02021 (
     TB02021_CODIGO,
     TB02021_CODEMP,
     TB02021_CODCLI,
     TB02021_VEND,
     TB02021_TIPODESC,
     TB02021_CONDPAG,
     TB02021_STATUS,
     TB02021_TRANSP,
     TB02021_CLATENDE,
     TB02021_TIPOFRETE,
     TB02021_CODORIGINAL,
     TB02021_CODCAI,
     TB02021_DTCAD,
     TB02021_OPCAD,
     TB02021_DATA,
     TB02021_OPERACAO,
     TB02021_SITUACAO,
     TB02021_NATUREZA,
     TB02021_CODCEN,
     TB02021_CODSUB,
     TB02021_PLANCON,
     TB02021_OBS
 )
 SELECT 
     ?,                                                         /* TB02021_CODIGO */     
     TB02115_CODEMP,                                           /* TB02021_CODEMP */     
     TB02115_CODCLI,                                           /* TB02021_CODCLI */     
     NULL,                                             /* TB02021_VEND */       
     '',                                         /* TB02021_TIPODESC */   
     '',                                          /* TB02021_CONDPAG */    
     '22',                                                     /* TB02021_STATUS */     
     '',                                           /* TB02021_TRANSP */     
     '',                                         /* TB02021_CLATENDE */   
     '',                                        /* TB02021_TIPOFRETE */  
     '',                                      /* TB02021_CODORIGINAL */  
     '',                                           /* TB02021_CODCAI */     
     GETDATE(),                                                /* TB02021_DTCAD */      
     'APP ATUAL EQUIP',                                         /* TB02021_OPCAD */      
     GETDATE(),                                                /* TB02021_DATA */       
     '',                                         /* TB02021_OPERACAO */   
     'A',                                         /* TB02021_SITUACAO */   
     '',                                         /* TB02021_NATUREZA */   
     '',                                           /* TB02021_CODCEN */     
     '',                                           /* TB02021_CODSUB */     
     '',                                          /* TB02021_PLANCON */    
     'VENDA GERADA IOS: ' + $codigoOS                          /* TB02021_OBS */        
 FROM TB02115
 WHERE TB02115_CODIGO = ?;";

    //parametros
    $stmt = consultarTroca($conn, $sql, array($novVend, $loginUser,  $codigoOS));

    sqlsrv_free_stmt($stmt);

    //historico da venda
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
                TB02130_HORASFIM)
                SELECT 
                    ?, --TB02130_CODIGO
                    GETDATE(), --TB02130_DATA
                    ?, --TB02130_USER
                    '00', --TB02130_STATUS
                    S.TB01073_NOME, --TB02130_NOME
                    ?, --TB02130_OBS
                    NULL, --TB02130_CODTEC
                    NULL, --TB02130_PREVISAO
                    NULL, --TB02130_NOMETEC
                    'V', --TB02130_TIPO
                    O.TB02115_CODCLI, --TB02130_CODCAD
                    O.TB02115_CODEMP, --TB02130_CODEMP
                    GETDATE(), --TB02130_DATAEXEC
                    '00:00', --TB02130_HORASCOM
                    '00:00' --TB02130_HORASFIM
                FROM TB02115 O 
                LEFT JOIN TB01073 S ON S.TB01073_CODIGO = '00'
                WHERE O.TB02115_CODIGO = ?;
                SELECT @@ROWCOUNT linhas_alteradas";

    //parametros
    $qtd = executarTroca($conn, $sql, array($novVend, $loginUser, 'Venda criada na aplicação Troca de peças', $codigoOS));
    if ($qtd !== 1)
        throw new Exception('Não foi possível gravar o histórico da venda.');

    return $novVend;
}
