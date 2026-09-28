<?php
header('Content-type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once '../config/database.php';
require_once 'testLogin.php';
require_once 'modtroca.php';
testLogin($conn);
try {
    $os = isset($_GET['os']) && is_string($_GET['os']) ? trim($_GET['os']) : '';
    $tipo = isset($_GET['tipo']) && is_string($_GET['tipo']) ? $_GET['tipo'] : '';
    $termo = isset($_GET['termo']) && is_string($_GET['termo']) ? trim($_GET['termo']) : '';
    if (!in_array($tipo, array('produto', 'E', 'S'), true))
        throw new Exception('Tipo de pesquisa inválido.');
    buscarOsTroca($conn, $os);
    $pagina = isset($_GET['pagina']) ? max(1, min(100000, (int) $_GET['pagina'])) : 1;
    $produtos = pesquisarProdutosTroca($conn, $termo, $tipo !== 'produto', $pagina);
    $mais = count($produtos) > 20;
    if ($mais)
        array_pop($produtos);
    echo json_encode(array('sucesso' => true, 'produtos' => $produtos, 'mais' => $mais));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(array('sucesso' => false, 'mensagem' => $e->getMessage()));
}