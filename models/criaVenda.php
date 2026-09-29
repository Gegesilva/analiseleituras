<?php

function proximoCodigoProduto($conn)
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

function executarCriarProduto($conn, $loginUser, $codigoOS)
{
    $codigo = proximoCodigoProduto($conn);
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
 WHERE TB02115_CODIGO = ?;


 ---------------------------------------------------------------------------------------------------------
 -- Grava o histórico da nova venda gerada
 ---------------------------------------------------------------------------------------------------------
 INSERT INTO TB02130 (
     TB02130_CODIGO,
     TB02130_DATA,
     TB02130_USER,
     TB02130_STATUS,
     TB02130_NOME,
     TB02130_CODCAD,
     TB02130_CODEMP,
     TB02130_TIPO
 )
 VALUES (
     @NovaVenda,                       /* TB02130_CODIGO */ 
     GETDATE(),                        /* TB02130_DATA */   
     'TR_SEPARA_SERV',                 /* TB02130_USER */   
     '22',                             /* TB02130_STATUS */ 
     'VENDA DESMEMBRADA',   /* TB02130_NOME */   
     @CodCli,                          /* TB02130_CODCAD */ 
     @CodEmp,                          /* TB02130_CODEMP */ 
     'V'                               /* TB02130_TIPO */   
 );
	";

    //parametros
    $stmt = consultarTroca($conn, $sql, array($codigo, $loginUser,  $codigoOS));

    sqlsrv_free_stmt($stmt);

    return $codigo;
}
