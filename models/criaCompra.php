<?php
include_once 'movSerie.php';

function proximoCodigoCompra($conn)
{
    $rows = linhasTroca($conn, 'SELECT
            TB00002_COD AS codigo
        FROM TB00002 WITH (UPDLOCK, HOLDLOCK)
        WHERE TB00002_TABELA = ?', array('TB02002'));
    $contador = count($rows) === 1 ? trim($rows[0]['codigo']) : '';
    $parteNumerica = substr($contador, 1);
    if ($contador === '' || $parteNumerica === '' || !ctype_digit($parteNumerica))
        throw new Exception('Contador ausente ou inválido para TB02002.');
    $prefixo = substr($contador, 0, 1);
    $numero = (int) $parteNumerica + 1;
    if (strlen((string) $numero) > strlen($parteNumerica))
        throw new Exception('O contador atingiu o limite de dígitos.');
    $codigo = $prefixo . str_pad($numero, strlen($parteNumerica), '0', STR_PAD_LEFT);
    executarTroca($conn, 'UPDATE TB00002
        SET TB00002_COD = ?
        WHERE TB00002_TABELA = ?', array($codigo, 'TB02002'));
    return $codigo;
}

function executarCriarCompra($conn, $loginUser, $codigoOS, $serie)
{
    $novCompra = proximoCodigoCompra($conn);
    // A consulta permanece idêntica, mas trocamos o conteúdo das variáveis por placeholders :nome
    $sql = "INSERT INTO [dbo].[TB02002](
                    [TB02002_DTCAD],
                    [TB02002_OPCAD],
                    [TB02002_CONTRATO],
                    [TB02002_BASEICMS],
                    [TB02002_BASEICMSSUB],
                    [TB02002_CODEMP],
                    [TB02002_CODFOR],
                    [TB02002_CODIGO],
                    [TB02002_CONDPAG],
                    [TB02002_DATA],
                    [TB02002_DESPADD],
                    [TB02002_OBS],
                    [TB02002_OBSADD],
                    [TB02002_PERCDESCONTO],
                    [TB02002_SITUACAO],
                    [TB02002_TRANSP],
                    [TB02002_VLRBRUTO],
                    [TB02002_VLRDESCONTO],
                    [TB02002_VLRFRETE],
                    [TB02002_VLRICMS],
                    [TB02002_VLRICMSSUB],
                    [TB02002_VLRIPI],
                    [TB02002_VLRNOTA],
                    [TB02002_VLROUTDESP],
                    [TB02002_NATUREZA],
                    [TB02002_TIPOTOTAL],
                    [TB02002_VLRICMSSUB2],
                    [TB02002_VLRBRUTO2],
                    [TB02002_VLRDESCONTO2],
                    [TB02002_VLRFRETE2],
                    [TB02002_VLRFRETE3],
                    [TB02002_VLRIPI2],
                    [TB02002_VLRNOTA2],
                    [TB02002_SOMASUB],
                    [TB02002_CODCEN],
                    [TB02002_CODSUB],
                    [TB02002_PLANCON],
                    [TB02002_CONTABIL],
                    [TB02002_TIPOFRETE],
                    [TB02002_PESOBRUTO],
                    [TB02002_PESOLIQUIDO],
                    [TB02002_DATANOTA],
                    [TB02002_VLROUTDESP2],
                    [TB02002_BASEICMS2],
                    [TB02002_VLRICMS2],
                    [TB02002_SOMAFRETE],
                    [TB02002_OPCOM],
                    [TB02002_QTDE],
                    [TB02002_DEVLOCACAO],
                    [TB02002_NUMERO],
                    [TB02002_NCONTEINER],
                    [TB02002_BONIF],
                    [TB02002_CODSETOR],
                    [TB02002_DTENTRADA]
                )
            SELECT TOP 1 
                GETDATE(),           -- TB02002_DTCAD
                :usuario,            -- TB02002_OPCAD
                NULL,                -- TB02002_CONTRATO
                TB02002_BASEICMS,    -- TB02002_BASEICMS
                TB02002_BASEICMSSUB, -- TB02002_BASEICMSSUB
                '00',                -- TB02002_CODEMP
                '0000',              -- TB02002_CODFOR
                :codigo,             -- TB02002_CODIGO
                TB02002_CONDPAG,     -- TB02002_CONDPAG
                GETDATE(),           -- TB02002_DATA
                0,                   -- TB02002_DESPADD
                CONCAT('Gerado pela Homologação de container - ', :userlogin),                -- TB02002_OBS
                NULL,                -- TB02002_OBSADD
                0,                   -- TB02002_PERCDESCONTO
                'A',                 -- TB02002_SITUACAO
                TB02002_TRANSP,      -- TB02002_TRANSP
                0,-- TB02002_VLRBRUTO
                0,                   -- TB02002_VLRDESCONTO
                0,                   -- TB02002_VLRFRETE
                0,                   -- TB02002_VLRICMS
                0,                   -- TB02002_VLRICMSSUB
                0,                   -- TB02002_VLRIPI
                0,-- TB02002_VLRNOTA
                0,                   -- TB02002_VLROUTDESP
                '1949',                 -- TB02002_NATUREZA
                'P',                 -- TB02002_TIPOTOTAL
                0,                   -- TB02002_VLRICMSSUB2
                :custoTotalCompra2,                                 -- TB02002_VLRBRUTO2
                0,                    -- TB02002_VLRDESCONTO2
                0,                    -- TB02002_VLRFRETE2
                0,                    -- TB02002_VLRFRETE3
                0,                    -- TB02002_VLRIPI2
                :custoTotalCompra3,                                 -- TB02002_VLRNOTA2
                0,                    -- TB02002_SOMASUB
                TB02002_CODCEN,       -- TB02002_CODCEN
                TB02002_CODSUB,       -- TB02002_CODSUB
                TB02002_PLANCON,      -- TB02002_PLANCON
                'N',                  -- TB02002_CONTABIL
                TB02002_TIPOFRETE,    -- TB02002_TIPOFRETE
                0,                    -- TB02002_PESOBRUTO
                0,                    -- TB02002_PESOLIQUIDO
                NULL,                 -- TB02002_DATANOTA
                0,                    -- TB02002_VLROUTDESP2
                0,                    -- TB02002_BASEICMS2
                0,                    -- TB02002_VLRICMS2
                'S',                  -- TB02002_SOMAFRETE
                31,                   -- TB02002_OPCOM
                0,-- TB02002_QTDE
                'N',                  -- TB02002_DEVLOCACAO
                NULL,                 -- TB02002_NUMERO
                :container,           -- TB02002_NCONTEINER
                'S',                  -- TB02002_BONIF
                '2030',               -- TB02002_CODSETOR
                GETDATE()
            FROM TB02002 a
                LEFT JOIN TB01008 ON TB01008_CODIGO = TB02002_CODEMP
                LEFT JOIN TB01001 ON TB01001_CNPJ = TB01008_CNPJ
                LEFT JOIN TB02176 ON TB02176_CONTRATO = TB02002_CODIGO
                LEFT JOIN TB01007 ON TB01007_CODIGO = TB02002_CODEMP
                LEFT JOIN TB00012 ON TB00012_CODIGO = TB02002_CODEMP
                AND TB00012_TABELA = 'TB01007'
                OUTER APPLY(
                    SELECT TB01078_NATUREZA CFOP_D,
                        TB01078_NATUREZA2 CFOP_F
                    FROM TB01078
                    WHERE TB01078_CODIGO = :operacao
                ) AS CFOP
            WHERE TB02002_CODIGO = :compra
            GROUP BY a.TB02002_CODIGO,
                TB02002_CODEMP,
                TB01008_NOME,
                TB01001_CODIGO,
                TB01001_NOME,
                TB02176_ESTADO,
                TB00012_ESTADO,
                CFOP.CFOP_D,
                CFOP.CFOP_F,
                TB02002_BASEICMS,
                TB02002_BASEICMSSUB,
                TB02002_CONDPAG,
                TB02002_TRANSP,
                TB02002_CODCEN,
                TB02002_CODSUB,
                TB02002_PLANCON,
                TB02002_TIPOFRETE,
                TB02002_CODFOR;
                
 ";

    //parametros
    $qtd = executarTroca($conn, $sql, array($novCompra, $loginUser, $codigoOS));
    if ($qtd !== 1)
        throw new Exception('Não foi possível gravar o histórico da compra.');

    executarCriarMovSerie($conn, $serie, $loginUser, 'TB02002', $novCompra, 'E');

    return $novCompra;
}
