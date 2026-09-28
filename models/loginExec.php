<?php
ob_start();
session_start();
header('Content-type: text/html; charset=UTF-8');

include_once '../config/database.php';

$login = isset($_POST['login']) ? trim($_POST['login']) : '';
$senha = isset($_POST['password']) ? $_POST['password'] : '';

$sql = "SELECT
            TB01066_USUARIO AS Usuario,
            TB01066_SENHA AS Senha,
            TB01066_TECNICO AS Tecnico,
            TB01066_FINANCEIRO AS Finc
        FROM TB01066
        WHERE TB01066_USUARIO = ?
          AND TB01066_SENHA = ?";

$params = array($login, $senha);
$stmt = sqlsrv_query($conn, $sql, $params);
$usuario = null;
$codTecnico = '';
$Finc = null;

if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $usuario = $row['Usuario'];
    $codTecnico = isset($row['Tecnico']) ? trim($row['Tecnico']) : '';
    $Finc = $row['Finc'];
}

if ($usuario !== null && $Finc == 1) {
    session_regenerate_id(true);
    $_SESSION['login'] = $usuario;
    $_SESSION['password'] = $senha;
    $_SESSION['tecnico'] = $codTecnico;

    ob_end_clean();
    header('Location: ../views/index.php');
    exit;
}

session_unset();
$_SESSION['login_erro'] = true;
ob_end_clean();
header('Location: ../views/login.php');
exit;
