<?php
  include_once "config.php";

  $serverName =  "$server";
  $connectionInfo = array("Database"=>"$base", "UID"=>"$usuarioBanco", "PWD"=>"$SenhaBanco", "CharacterSet"=>"UTF-8");
  $conn = sqlsrv_connect($serverName, $connectionInfo);
  
  if ($conn === false) {
    die(print_r(sqlsrv_errors(), true));
  } else {
    // echo "Conexão estabelecida com sucesso!";
  } 

