<?PHP
header('Content-type: text/html; charset=utf-8');
session_start();
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');

include_once "conexaoSQL.php";

$login = $_SESSION["login"];
$senha = $_SESSION["password"];
$sql = "SELECT 
      TB01066_USUARIO Usuario,
      TB01066_SENHA Senha
     FROM 
      TB01066
     WHERE 
     TB01066_USUARIO = '$login'
     AND TB01066_SENHA = '$senha'";
$stmt = sqlsrv_query($conn, $sql);
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  $usuario = $row['Usuario'];
  $senha = $row['Senha'];
}
if ($usuario != NULL) {
} else {
  echo "<script>window.alert('É necessário fazer login!')</script>";
  echo "<script>location.href='./login.php'</script>";
}


include_once "models/filtrosIni.php";
include_once "models/consultaIni.php";
include_once "models/totais.php";
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Análise de Leituras</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="shortcut icon" href="media/logo.png" type="image/x-icon">
  <link rel="stylesheet" href="./css/style.css">
</head>

<body>

  <header class="header-bg text-center sticky-top">


    <div class="header-top">
      <img src="media/logo.png" class="logo-left">
      <h4 class="mb-4">ANÁLISE DE LEITURAS</h4>

      <form action="" method="GET" class="mx-auto bg-white p-3 rounded-4 shadow-sm text-start"
        style="max-width: 800px;">
        <img src="media/logo.png" class="logo-left">
        <div class="row g-3 align-items-end">

          <div class="col-md-3">
            <label for="mesFiltro" class="form-label text-muted small fw-bold mb-1">Mês de Referência</label>
            <input type="text" class="form-control bg-light border-0 py-2 text-center" id="mesFiltro" name="mesFiltro"
              value="<?php echo htmlspecialchars($mesFiltro); ?>" placeholder="MM/AAAA" required>
          </div>

          <div class="col-md-3">
            <label for="contratoFiltro" class="form-label text-muted small fw-bold mb-1">Filtrar por Contrato</label>
            <input type="text" class="form-control bg-light border-0 py-2" id="contratoFiltro" name="contratoFiltro"
              value="<?php echo htmlspecialchars($contratoFiltro); ?>" placeholder="Digite o contrato..."
              autocomplete="off">
          </div>

          <div class="col-md-4">
            <label for="tipoFiltro" class="form-label text-muted small fw-bold mb-1">Filtrar por Tipo</label>
            <select name="tipoFiltro" class="form-control bg-light border-0 py-2" required>

              <option value="">Selecione</option>

              <option value="A4PB" <?php if ($tipoFiltro == 'A4PB')
                echo 'selected'; ?>>A4PB</option>
              <option value="A4COR" <?php if ($tipoFiltro == 'A4COR')
                echo 'selected'; ?>>A4COR</option>
              <option value="DIG" <?php if ($tipoFiltro == 'DIG')
                echo 'selected'; ?>>DIG</option>
              <option value="A3PB" <?php if ($tipoFiltro == 'A3PB')
                echo 'selected'; ?>>A3PB</option>
              <option value="A3COR" <?php if ($tipoFiltro == 'A3COR')
                echo 'selected'; ?>>A3COR</option>

            </select>
          </div>

          <div class="col-md-2">
            <button class="btn w-100 py-2 rounded-3 shadow-sm" type="submit"
              style="background-color: #fff; color: white; border: none; transition: 0.3s;">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-search"
                viewBox="0 0 16 16">
                <path
                  d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
              </svg>
            </button>
          </div>
        </div>
      </form>


    </div>
  </header>

  <main class="container-fluid mb-5">
    <div class="row justify-content-center">
      <div class="col-lg-12">
        <div class="card card-table overflow-hidden">
          <div class="table-responsive">
            <table class="table table-hover text-center align-middle tabelaDados" id="tabelaDados">
              <thead>
                <tr>
                  <th class="sortable">MÊS</th>
                  <th class="sortable">CONTRATO</th>
                  <th class="sortable">CLIENTE</th>
                  <th class="sortable">SERIAL</th>
                  <th class="sortable">EQUIPAMENTO</th>
                  <th class="sortable">SITUAÇÃO</th>

                  <?php
                  if ($tipoFiltro == 'A4PB' || $tipoFiltro == 'A4COR' || $tipoFiltro == 'DIG' || $tipoFiltro == 'A3PB' || $tipoFiltro == 'A3COR') {
                    echo "<th class='sortable'>$tipoFiltro 120 dias</th>";
                    echo "<th class='sortable'>$tipoFiltro 90 dias</th>";
                    echo "<th class='sortable'>$tipoFiltro 60 dias</th>";
                    echo "<th class='sortable'>$tipoFiltro 30 dias</th>";
                    echo "<th class='sortable'>$tipoFiltro MÉDIA</th>";
                  }
                  ?>
                </tr>
              </thead>
              <tbody>
                <?php

                $hasData = false;
                /* função para definir cor */
                function corCelula($valor, $media)
                {

                  $valor = (float) $valor;
                  $media = (float) $media;

                  if ($valor == 0) {
                    return 'red';
                  }

                  if ($media > 0) {

                    $variacao = abs($valor - $media) / $media;

                    if ($variacao > 0.30) {
                      return 'yellow';
                    }
                  }

                  return '';
                }

                while ($rowL = sqlsrv_fetch_array($stmtLista, SQLSRV_FETCH_ASSOC)) {

                  echo "<tr>";

                  // colunas fixas
                  echo "<td data-value='" . $rowL['MES'] . "'>" . $rowL['MES'] . "</td>";
                  echo "<td data-value='" . $rowL['CONTRATO'] . "'>" . $rowL['CONTRATO'] . "</td>";
                  echo "<td data-value='" . $rowL['CLIENTE'] . "'>" . $rowL['CLIENTE'] . "</td>";
                  echo "<td data-value='" . $rowL['SERIAL'] . "'>" . $rowL['SERIAL'] . "</td>";
                  echo "<td data-value='" . $rowL['EQUIPAMENTO'] . "'>" . $rowL['EQUIPAMENTO'] . "</td>";
                  echo "<td data-value='" . $rowL['SITUACAO'] . "'>" . $rowL['SITUACAO'] . "</td>";

                  // colunas filtradas
                  if ($tipoFiltro == 'A4PB') {

                    $v120 = isset($rowL['SALDO_A4PB_120']) ? $rowL['SALDO_A4PB_120'] : 0;
                    $v90 = isset($rowL['SALDO_A4PB_90']) ? $rowL['SALDO_A4PB_90'] : 0;
                    $v60 = isset($rowL['SALDO_A4PB_60']) ? $rowL['SALDO_A4PB_60'] : 0;
                    $v30 = isset($rowL['SALDO_A4PB_30']) ? $rowL['SALDO_A4PB_30'] : 0;
                    $media = isset($rowL['MEDIA_A4PB']) ? $rowL['MEDIA_A4PB'] : 0;

                  } elseif ($tipoFiltro == 'A4COR') {

                    $v120 = isset($rowL['SALDO_A4COR_120']) ? $rowL['SALDO_A4COR_120'] : 0;
                    $v90 = isset($rowL['SALDO_A4COR_90']) ? $rowL['SALDO_A4COR_90'] : 0;
                    $v60 = isset($rowL['SALDO_A4COR_60']) ? $rowL['SALDO_A4COR_60'] : 0;
                    $v30 = isset($rowL['SALDO_A4COR_30']) ? $rowL['SALDO_A4COR_30'] : 0;
                    $media = isset($rowL['MEDIA_A4COR']) ? $rowL['MEDIA_A4COR'] : 0;

                  } elseif ($tipoFiltro == 'DIG') {

                    $v120 = isset($rowL['SALDO_DIG_120']) ? $rowL['SALDO_DIG_120'] : 0;
                    $v90 = isset($rowL['SALDO_DIG_90']) ? $rowL['SALDO_DIG_90'] : 0;
                    $v60 = isset($rowL['SALDO_DIG_60']) ? $rowL['SALDO_DIG_60'] : 0;
                    $v30 = isset($rowL['SALDO_DIG_30']) ? $rowL['SALDO_DIG_30'] : 0;
                    $media = isset($rowL['MEDIA_DIG']) ? $rowL['MEDIA_DIG'] : 0;

                  } elseif ($tipoFiltro == 'A3PB') {

                    $v120 = isset($rowL['SALDO_A3PB_120']) ? $rowL['SALDO_A3PB_120'] : 0;
                    $v90 = isset($rowL['SALDO_A3PB_90']) ? $rowL['SALDO_A3PB_90'] : 0;
                    $v60 = isset($rowL['SALDO_A3PB_60']) ? $rowL['SALDO_A3PB_60'] : 0;
                    $v30 = isset($rowL['SALDO_A3PB_30']) ? $rowL['SALDO_A3PB_30'] : 0;
                    $media = isset($rowL['MEDIA_A3PB']) ? $rowL['MEDIA_A3PB'] : 0;

                  } elseif ($tipoFiltro == 'A3COR') {

                    $v120 = isset($rowL['SALDO_A3COR_120']) ? $rowL['SALDO_A3COR_120'] : 0;
                    $v90 = isset($rowL['SALDO_A3COR_90']) ? $rowL['SALDO_A3COR_90'] : 0;
                    $v60 = isset($rowL['SALDO_A3COR_60']) ? $rowL['SALDO_A3COR_60'] : 0;
                    $v30 = isset($rowL['SALDO_A3COR_30']) ? $rowL['SALDO_A3COR_30'] : 0;
                    $media = isset($rowL['MEDIA_A3COR']) ? $rowL['MEDIA_A3COR'] : 0;

                  } else {

                    $v120 = $v90 = $v60 = $v30 = $media = 0;
                  }

                  $cor120 = corCelula(number_format($v120, 2), number_format($media, 2));
                  $cor90 = corCelula(number_format($v90, 2), number_format($media, 2));
                  $cor60 = corCelula(number_format($v60, 2), number_format($media, 2));
                  $cor30 = corCelula(number_format($v30, 2), number_format($media, 2));

                  // exibição
                  echo "<td style='color: " . $cor120 . ";' data-value='" . $v120 . "'>" . number_format($v120, 2) . "</td>";
                  echo "<td style='color: " . $cor90 . ";' data-value='" . $v90 . "'>" . number_format($v90, 2) . "</td>";
                  echo "<td style='color: " . $cor60 . ";' data-value='" . $v60 . "'>" . number_format($v60, 2) . "</td>";
                  echo "<td style='color: " . $cor30 . ";' data-value='" . $v30 . "'>" . number_format($v30, 2) . "</td>";
                  echo "<td data-value='" . $media . "'>" . number_format($media, 2) . "</td>";

                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="modalTecnico" tabindex="-1" aria-labelledby="modalTecnicoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalTecnicoLabel">Selecionar Técnico</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-4">
          <p id="textoModalTecnico" class="text-muted small mb-4"></p>
          <div class="mb-3">
            <label for="selectTecnico" class="form-label fw-bold text-dark">Técnico Responsável:</label>
            <select class="form-select" id="selectTecnico">
              <option value="" disabled selected>Selecione um técnico...</option>
              <?php echo $optionsTecnicos; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer bg-light border-0">
          <button type="button" class="btn rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn rounded-pill px-4" onclick="confirmarGerarOS()">Confirmar e Abrir OS</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/inicio.js"></script>

</body>

</html>