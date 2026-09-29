<?php
function executarCriarMovSerie($conn, $serie, $loginUser, $tabela, $codigo, $operacao)
{
    $sql = " INSERT INTO TB02055
                    (
                    TB02055_PRODUTO,
                    TB02055_NUMSERIE,
                    TB02055_CODIGO,
                    TB02055_TABELA,
                    TB02055_QTPROD,
                    TB02055_CODEMP,
                    TB02055_RMA,
                    TB02055_IMOB,
                    TB02055_OPCAD,
                    TB02055_DTVALIDADE,
                    TB02055_QTPRODB,
                    TB02055_CODEMP2,
                    TB02055_OPERACAO
                    )

                SELECT
                    TB02054_PRODUTO, --TB02055_PRODUTO
                    TB02054_NUMSERIE, --TB02055_NUMSERIE
                    ?, --TB02055_CODIGO
                    ?, --TB02055_TABELA
                    1, --TB02055_QTPROD
                    TB02054_CODEMP, --TB02055_CODEMP
                    TB02054_RMA, --TB02055_RMA
                    TB02054_IMOB, --TB02055_IMOB
                    ?, --TB02055_OPCAD
                    GETDATE(), --TB02055_DTVALIDADE
                    0, --TB02055_QTPRODB
                    TB02054_CODEMP, --TB02055_CODEMP2
                    ? --TB02055_OPERACAO
                FROM TB02054
                WHERE TB02054_NUMSERIE = ?;";

    //parametros
    $stmt = consultarTroca($conn, $sql, array($codigo, $tabela, $loginUser, $operacao, $serie));

    sqlsrv_free_stmt($stmt);

}
