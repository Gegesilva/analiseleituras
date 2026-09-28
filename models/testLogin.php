<?php
// Valida a sessao do usuario antes de liberar as telas do AtualizaEquip.
function testLogin($conn)
{
    if (session_id() == '') {
        session_start();
    }

    if (!isset($_SESSION['login']) || !isset($_SESSION['password'])) {
        header('Location: ../views/login.php');
        exit;
    }

    $sql = "SELECT
                TB01066_USUARIO AS Usuario,
                TB01066_TECNICO AS Tecnico,
                TB01066_FINANCEIRO AS Finc
            FROM TB01066
            WHERE TB01066_USUARIO = ?
              AND TB01066_SENHA = ?";

    $params = array($_SESSION['login'], $_SESSION['password']);
    $stmt = sqlsrv_query($conn, $sql, $params);
    $usuario = null;
    $codTecnico = '';
    $Finc = null;

    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $usuario = $row['Usuario'];
        $codTecnico = isset($row['Tecnico']) ? trim($row['Tecnico']) : '';
        $Finc = $row['Finc'];
    }

    if ($usuario === null || $Finc != 1) {
        session_unset();
        session_destroy();
        header('Location: ../views/login.php?erro=1');
        exit;
    }

    $_SESSION['tecnico'] = $codTecnico;

    return true;
}

function tecnicoLogado()
{
    return isset($_SESSION['tecnico']) ? trim($_SESSION['tecnico']) : '';
}
