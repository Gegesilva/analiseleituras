<?php
header('Content-type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
require_once '../config/database.php'; require_once 'testLogin.php'; require_once 'modtroca.php'; testLogin($conn);
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método inválido.');
    if (!isset($_POST['token']) || !is_string($_POST['token']) || $_POST['token'] !== csrfTroca()) throw new Exception('A sessão expirou. Atualize a página.');
    $os = isset($_POST['os']) && is_string($_POST['os']) ? trim($_POST['os']) : '';
    $tipo = isset($_POST['tipo']) && is_string($_POST['tipo']) ? $_POST['tipo'] : '';
    $termo = isset($_POST['termo']) && is_string($_POST['termo']) ? trim($_POST['termo']) : '';
    if (!in_array($tipo, array('produto', 'E', 'S'), true)) throw new Exception('Tipo de pesquisa inválido.');
    buscarOsTroca($conn, $os);
    $pagina = isset($_POST['pagina']) ? max(1, min(100000, (int) $_POST['pagina'])) : 1;
    $produtos = pesquisarProdutosTroca($conn, $termo, $tipo !== 'produto', $pagina);
    $mais = count($produtos) > 20; if ($mais) array_pop($produtos);
    echo json_encode(array('sucesso' => true, 'produtos' => $produtos, 'mais' => $mais));
} catch (Exception $e) { http_response_code(400); echo json_encode(array('sucesso' => false, 'mensagem' => $e->getMessage())); }