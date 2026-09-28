<?php
require_once dirname(__FILE__) . '/config.php';
$conn = sqlsrv_connect($server, array('Database' => $base, 'UID' => $usuarioBanco,
    'PWD' => $SenhaBanco, 'CharacterSet' => 'UTF-8', 'LoginTimeout' => 5));
if (!$conn) {
    error_log(print_r(sqlsrv_errors(), true));
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados.');
}