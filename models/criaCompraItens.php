<?php

function executarCriarItensCompra($conn, $loginUser, $novCompra, $codigoOS)
{
    $sql = "INSERT INTO [dbo].[TB02003](
                [TB02003_DTCAD],
                [TB02003_OPCAD],
                [TB02003_CODIGO],
                [TB02003_CUSTO],
                [TB02003_PERDESC],
                [TB02003_PRODUTO],
                [TB02003_PRUNIT],
                [TB02003_QTPROD],
                [TB02003_QTPRODB],
                [TB02003_SITUACAO],
                [TB02003_TOTVALOR],
                [TB02003_TOTVALORB],
                [TB02003_PERCIPI],
                [TB02003_VLRIPI],
                [TB02003_PERCST],
                [TB02003_VLRST],
                [TB02003_REGISTRO],
                [TB02003_VLRDESC],
                [TB02003_ICMS],
                [TB02003_VLRICMS],
                [TB02003_NATUREZA],
                [TB02003_VLRFRETE],
                [TB02003_VLROUTDESP],
                [TB02003_VLRFRETEB],
                [TB02003_VLROUTDESPB],
                [TB02003_BASERED],
                [TB02003_ICMSST],
                [TB02003_SOMAST],
                [TB02003_BASEICMS],
                [TB02003_BASEST],
                [TB02003_CST],
                [TB02003_OPCOM],
                [TB02003_COFINS],
                [TB02003_CSTCOFINS],
                [TB02003_CSTIPI],
                [TB02003_CSTPIS],
                [TB02003_PIS],
                [TB02003_UNPROD],
                [TB02003_QTPRODUN]
            )
            SELECT TOP 1
                GETDATE(),                                  -- TB02003_DTCAD
                ?,                  -- TB02003_OPCAD
                ?,                  -- TB02003_CODIGO
                CUSTO / QTD,                              -- TB02003_CUSTO
                0,                                          -- TB02003_PERDESC
                PRODUTO_PECA,                -- TB02003_PRODUTO
                CUSTO / QTD,                              -- TB02003_PRUNIT
                QTD,                  -- TB02003_QTPROD
                0,                                          -- TB02003_QTPRODB
                'A',                                        -- TB02003_SITUACAO
                CUSTO,                 -- TB02003_TOTVALOR
                0,                                          -- TB02003_TOTVALORB
                0,                                          -- TB02003_PERCIPI
                0,                                          -- TB02003_VLRIPI
                0,                                          -- TB02003_PERCST
                0,                                          -- TB02003_VLRST
                '',                                  -- TB02003_REGISTRO
                0,                                          -- TB02003_VLRDESC
                0,                               -- TB02003_ICMS
                0,                               -- TB02003_VLRICMS
                '1949',                                     -- TB02003_NATUREZA
                0,                                          -- TB02003_VLRFRETE
                0,                                          -- TB02003_VLROUTDESP
                0,                                          -- TB02003_VLRFRETEB
                0,                                          -- TB02003_VLROUTDESPB
                '',                            -- TB02003_BASERED
                0,                                          -- TB02003_ICMSST
                0,                                          -- TB02003_SOMAST
                0,                                          -- TB02003_BASEICMS
                0,                                          -- TB02003_BASEST
                '041',                                      -- TB02003_CST
                '31',                                       -- TB02003_OPCOM
                '0',                                        -- TB02003_COFINS
                '99',                                       -- TB02003_CSTCOFINS
                '99',                                        -- TB02003_CSTIPI
                '99',                                       -- TB02003_CSTPIS
                '0',                                        -- TB02003_PIS
                '00',                                       -- TB02003_UNPROD
                QTD                                          -- TB02003_QTPRODUN
            FROM TB_TROCA_PECAS
            WHERE OS = ?
	";

    $stmt = consultarTroca($conn, $sql, array($loginUser, $novCompra, $codigoOS));
    sqlsrv_free_stmt($stmt);

    return true;
}
