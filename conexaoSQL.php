<?php
  $serverName = 'localhost';
  $connectionInfo = array("Database"=>"teste", "UID"=>"sa", "PWD"=>"teste", "CharacterSet"=>"UTF-8");
  $conn = sqlsrv_connect($serverName, $connectionInfo);
  
  if($conn){
    echo "";
  }else{
    echo "falha na conex�o";
    die( print_r(sqlsrv_errors(), true));
  }
  
  
  function getConnection() {
    $serverName = 'localhost';
    $connectionInfo = array("Database"=>"teste", "UID"=>"sa", "PWD"=>"13012020", "CharacterSet"=>"UTF-8");
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    return($conn);
  }


?> 
