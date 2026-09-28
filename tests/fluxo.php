<?php
// Teste isolado: nao conecta nem grava no ERP.
define('SQLSRV_FETCH_ASSOC', 2);
$cenario = ''; $consultas = array(); $confirmacoes = 0; $reversoes = 0;
function sqlsrv_begin_transaction($conn) { return true; }
function sqlsrv_commit($conn) { global $confirmacoes; $confirmacoes++; return true; }
function sqlsrv_rollback($conn) { global $reversoes; $reversoes++; return true; }
function sqlsrv_errors() { return array('falha simulada'); }
function sqlsrv_free_stmt($s) {}
function sqlsrv_num_fields($s) { return count($s->rows) ? count($s->rows[0]) : 0; }
function sqlsrv_next_result($s) { return null; }
function sqlsrv_fetch_array($s, $tipo) { return $s->pos < count($s->rows) ? $s->rows[$s->pos++] : null; }
function sqlsrv_query($conn, $sql, $params)
{
    global $cenario, $consultas;
    $consultas[] = array($sql, $params); $rows = array();
    if (strpos($sql, 'SELECT TOP 1 TB02115_CODIGO os') !== false && $cenario === 'existente') $rows[] = array('os' => '123456');
    if (strpos($sql, 'SELECT DISTINCT TB02054_PRODUTO') !== false && $cenario !== 'sem_estoque') $rows[] = array('produto' => '00001', 'empresa' => '00');
    if (strpos($sql, 'SELECT TB00002_COD') !== false) $rows[] = array('codigo' => $cenario === 'contador_limite' ? '999999' : '000042');
    if (strpos($sql, 'INSERT INTO TB02115') !== false) $rows[] = array('linhas_alteradas' => 1);
    if (strpos($sql, 'INSERT INTO TB02130') !== false) {
        if ($cenario === 'falha_historico') return false;
        $rows[] = array('linhas_alteradas' => 1);
    }
    $s = new stdClass(); $s->rows = $rows; $s->pos = 0; return $s;
}
require dirname(__FILE__) . '/../models/modtroca.php';
function conferir($condicao, $mensagem) { if (!$condicao) throw new Exception($mensagem); }
function preparar($nome) { global $cenario, $consultas, $confirmacoes, $reversoes; $cenario=$nome; $consultas=array(); $confirmacoes=0; $reversoes=0; }
preparar('existente');
$r=abrirOsTroca(null,'SERIE','0001','usuario');
conferir($r['os']==='123456' && !$r['nova'] && count($consultas)===1 && $confirmacoes===1,'OS existente nao deve gerar nova gravacao');
preparar('nova');
$r=abrirOsTroca(null,'SERIE','0001','usuario');
conferir($r['os']==='000043' && $r['nova'] && $confirmacoes===1 && !$reversoes,'Nova OS deve confirmar transacao');
conferir(strpos($consultas[0][0],'UPDLOCK, HOLDLOCK')!==false,'Consulta concorrente precisa bloquear a faixa');
$ultimo=end($consultas); conferir($ultimo[1][2]==='000043','Historico deve vincular codigo exato da nova OS');
foreach(array('falha_historico','sem_estoque','contador_limite') as $c) {
    preparar($c); $falhou=false;
    try { abrirOsTroca(null,'SERIE','0001','usuario'); } catch(Exception $e) { $falhou=true; }
    conferir($falhou && $reversoes===1 && $confirmacoes===0,'Falha deve reverter toda a transacao: '.$c);
}
foreach(array('0','-1','1.5','1e3','',' 1','1000000000000000000') as $q) {
    $falhou=false; try { quantidadeTroca($q); } catch(Exception $e) { $falhou=true; }
    conferir($falhou,'Quantidade invalida aceita: '.$q);
}
conferir(quantidadeTroca('12')==='12','Quantidade inteira valida');
preparar('pesquisa'); pesquisarProdutosTroca(null,"A%_['",true,2); $q=end($consultas);
conferir(strpos($q[0],'NOT IN (9,11)')!==false,'Pecas devem excluir 9 e 11');
conferir($q[1][0]==="%A[%][_][[]'%" && $q[1][3]===20 && $q[1][4]===41,'Pesquisa literal e paginacao');
pesquisarProdutosTroca(null,'',false,1); $q=end($consultas);
conferir(strpos($q[0],'IN (9,11)')!==false && strpos($q[0],'NOT IN')===false,'Produtos devem usar 9 e 11');
echo "OK: reaproveitamento, abertura, rollback, contador, quantidade, filtros e paginacao.\n";