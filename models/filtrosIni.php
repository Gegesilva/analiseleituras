<?php

include_once "conexaoSQL.php";

$mesAtual = date('m/Y');

// mes definido atual
$mesFiltro = (isset($_GET['mesFiltro']) && trim($_GET['mesFiltro']) != '')
  ? trim($_GET['mesFiltro'])
  : $mesAtual;

$contratoFiltro = isset($_GET['contratoFiltro']) ? trim($_GET['contratoFiltro']) : '';
$tipoFiltro = isset($_GET['tipoFiltro']) ? trim($_GET['tipoFiltro']) : 'A4PB';
$somenteCriticos = isset($_GET['criticos']) && $_GET['criticos'] == 1;

$where = array();
$params = array();

// aplica mes
$where[] = "B.MES = ?";
$params[] = $mesFiltro;

// contrato 
if ($contratoFiltro != '') {
  $where[] = "B.CONTRATO LIKE ?";
  $params[] = "%" . $contratoFiltro . "%";
}

// monta where
$whereSql = "WHERE " . implode(" AND ", $where);

$bloqueado = false;

if ($contratoFiltro == '') {
  $bloqueado = true;
}