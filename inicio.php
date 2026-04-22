<?PHP
header('Content-type: text/html; charset=utf-8');
session_start();
ini_set('max_input_vars', 3000);
error_reporting(0);
ini_set('display_errors', '0');

include_once "conexaoSQL.php";
include_once "config.php";

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
    <div class="container-fluid px-3 text-start">
      <div class="d-inline-flex flex-wrap gap-2 my-1">

        <span
          class="badge bg-primary bg-opacity-50 text-primary border border-primary-subtle px-3 py-1 rounded-pill shadow-sm text-white"
          style="font-size: 0.75rem;">
          <strong class="me-1">Equipamentos:</strong>
          <span id="totalLinhas"></span>
        </span>
        <span
          class="badge bg-success bg-opacity-50 text-success border border-success-subtle px-3 py-1 rounded-pill shadow-sm text-white"
          style="font-size: 0.75rem;">
          <strong class="me-1">Críticos:</strong>
          <span id="totalLinhasCriticos"></span>
        </span>
      </div>
    </div>


    <div class="header-top">
      <img src="media/logo.png" class="logo-left">
      <h4 class="mb-4">ANÁLISE DE LEITURAS</h4>

      <form action="" method="GET" class="mx-auto bg-white p-3 rounded-4 shadow-sm text-start"
        style="max-width: 900px;">

        <div class="row g-3 align-items-end">

          <!-- MÊS -->
          <div class="col-md-2">
            <label class="form-label small fw-bold mb-1  text-dark">Mês</label>
            <input type="text" class="form-control bg-light border-0 py-2 text-center" name="mesFiltro"
              value="<?php echo htmlspecialchars($mesFiltro); ?>">
          </div>

          <!-- CONTRATO -->
          <div class="col-md-3">
            <label class="form-label small fw-bold mb-1  text-dark">Contrato</label>
            <input type="text" class="form-control bg-light border-0 py-2" name="contratoFiltro"
              value="<?php echo htmlspecialchars($contratoFiltro); ?>">
          </div>

          <!-- TIPO -->
          <div class="col-md-3">
            <label class="form-label small fw-bold mb-1  text-dark">Tipo</label>
            <select name="tipoFiltro" class="form-control bg-light border-0 py-2">
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

          <!-- CHECK -->
          <div class="col-md-2 d-flex align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="criticos" value="1" <?php echo $somenteCriticos ? 'checked' : ''; ?>>
              <label class="form-check-label  text-dark">
                Críticos
              </label>
            </div>
          </div>

          <!-- BOTÃO -->
          <div class="col-md-2">
            <div class="d-flex gap-2">
              <button class="btn w-100 py-2 rounded-3 shadow-sm" type="submit"
                style="background-color: #fff; color: white; border: none; transition: 0.3s;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-search"
                  viewBox="0 0 16 16">
                  <path
                    d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                </svg>
              </button>

              &nbsp;
              &nbsp;

              <button type="submit" name="validar" value="1" class="btn py-2 rounded-3 shadow-sm"
                style="background-color:#28a745; color:white; white-space: nowrap;"
                onclick="return confirmarValidacao();">
                Validar
              </button>
            </div>
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
                    echo "<th class='sortable'>$tipoFiltro ATUAL</th>";
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
                    return '#f8d7da';
                  }

                  if ($media > 0) {

                    $variacao = abs($valor - $media) / $media;

                    if ($variacao > 0.80) {
                      return '#fff3cd';
                    }
                  }

                  return '';
                }

                while ($rowL = sqlsrv_fetch_array($stmtLista, SQLSRV_FETCH_ASSOC)) {

                  if ($tipoFiltro == 'A4PB') {

                    $v120 = isset($rowL['SALDO_A4PB_120']) ? $rowL['SALDO_A4PB_120'] : 0;
                    $v90 = isset($rowL['SALDO_A4PB_90']) ? $rowL['SALDO_A4PB_90'] : 0;
                    $v60 = isset($rowL['SALDO_A4PB_60']) ? $rowL['SALDO_A4PB_60'] : 0;
                    $v30 = isset($rowL['SALDO_A4PB_30']) ? $rowL['SALDO_A4PB_30'] : 0;
                    $vAtual = isset($rowL['SALDO_A4PB']) ? $rowL['SALDO_A4PB'] : 0;
                    $media = isset($rowL['MEDIA_A4PB']) ? $rowL['MEDIA_A4PB'] : 0;

                  } elseif ($tipoFiltro == 'A4COR') {

                    $v120 = isset($rowL['SALDO_A4COR_120']) ? $rowL['SALDO_A4COR_120'] : 0;
                    $v90 = isset($rowL['SALDO_A4COR_90']) ? $rowL['SALDO_A4COR_90'] : 0;
                    $v60 = isset($rowL['SALDO_A4COR_60']) ? $rowL['SALDO_A4COR_60'] : 0;
                    $v30 = isset($rowL['SALDO_A4COR_30']) ? $rowL['SALDO_A4COR_30'] : 0;
                    $vAtual = isset($rowL['SALDO_A4COR']) ? $rowL['SALDO_A4COR'] : 0;
                    $media = isset($rowL['MEDIA_A4COR']) ? $rowL['MEDIA_A4COR'] : 0;

                  } elseif ($tipoFiltro == 'DIG') {

                    $v120 = isset($rowL['SALDO_DIG_120']) ? $rowL['SALDO_DIG_120'] : 0;
                    $v90 = isset($rowL['SALDO_DIG_90']) ? $rowL['SALDO_DIG_90'] : 0;
                    $v60 = isset($rowL['SALDO_DIG_60']) ? $rowL['SALDO_DIG_60'] : 0;
                    $v30 = isset($rowL['SALDO_DIG_30']) ? $rowL['SALDO_DIG_30'] : 0;
                    $vAtual = isset($rowL['SALDO_DIG']) ? $rowL['SALDO_DIG'] : 0;
                    $media = isset($rowL['MEDIA_DIG']) ? $rowL['MEDIA_DIG'] : 0;

                  } elseif ($tipoFiltro == 'A3PB') {

                    $v120 = isset($rowL['SALDO_A3PB_120']) ? $rowL['SALDO_A3PB_120'] : 0;
                    $v90 = isset($rowL['SALDO_A3PB_90']) ? $rowL['SALDO_A3PB_90'] : 0;
                    $v60 = isset($rowL['SALDO_A3PB_60']) ? $rowL['SALDO_A3PB_60'] : 0;
                    $v30 = isset($rowL['SALDO_A3PB_30']) ? $rowL['SALDO_A3PB_30'] : 0;
                    $vAtual = isset($rowL['SALDO_A3PB']) ? $rowL['SALDO_A3PB'] : 0;
                    $media = isset($rowL['MEDIA_A3PB']) ? $rowL['MEDIA_A3PB'] : 0;

                  } elseif ($tipoFiltro == 'A3COR') {

                    $v120 = isset($rowL['SALDO_A3COR_120']) ? $rowL['SALDO_A3COR_120'] : 0;
                    $v90 = isset($rowL['SALDO_A3COR_90']) ? $rowL['SALDO_A3COR_90'] : 0;
                    $v60 = isset($rowL['SALDO_A3COR_60']) ? $rowL['SALDO_A3COR_60'] : 0;
                    $v30 = isset($rowL['SALDO_A3COR_30']) ? $rowL['SALDO_A3COR_30'] : 0;
                    $vAtual = isset($rowL['SALDO_A3COR']) ? $rowL['SALDO_A3COR'] : 0;
                    $media = isset($rowL['MEDIA_A3COR']) ? $rowL['MEDIA_A3COR'] : 0;

                  } else {

                    $v120 = $v90 = $v60 = $v30 = $vAtual = $media = 0;
                  }

                  // cores
                  $cor120 = corCelula($v120, $media);
                  $cor90 = corCelula($v90, $media);
                  $cor60 = corCelula($v60, $media);
                  $cor30 = corCelula($v30, $media);
                  $corAtual = corCelula($vAtual, $media);

                  // qual e critico
                  $critico = (
                    $cor120 != '' ||
                    $cor90 != '' ||
                    $cor60 != '' ||
                    $cor30 != '' ||
                    $corAtual != ''
                  );

                  // filtra
                  if ($somenteCriticos && !$critico) {
                    continue;
                  }

                  echo "<tr>";

                  // colunas fixas
                  echo "<td data-value='" . $rowL['MES'] . "'>" . $rowL['MES'] . "</td>";
                  echo "<td data-value='" . $rowL['CONTRATO'] . "'>" . $rowL['CONTRATO'] . "</td>";
                  echo "<td data-value='" . $rowL['CLIENTE'] . "'>" . $rowL['CLIENTE'] . "</td>";
                  echo "<td data-value='" . $rowL['SERIAL'] . "'>" . $rowL['SERIAL'] . "</td>";
                  echo "<td data-value='" . $rowL['EQUIPAMENTO'] . "'>" . $rowL['EQUIPAMENTO'] . "</td>";
                  echo "<td data-value='" . $rowL['SITUACAO'] . "'>" . $rowL['SITUACAO'] . "</td>";


                  // exibição
                  echo "<td style='background-color: $cor120; border-radius: 10px' data-value='$v120'>" . number_format($v120, 0) . "</td>";
                  echo "<td style='background-color: $cor90; border-radius: 10px' data-value='$v90'>" . number_format($v90, 0) . "</td>";
                  echo "<td style='background-color: $cor60; border-radius: 10px' data-value='$v60'>" . number_format($v60, 0) . "</td>";
                  echo "<td style='background-color: $cor30; border-radius: 10px' data-value='$v30'>" . number_format($v30, 0) . "</td>";
                  echo "<td style='background-color: $corAtual; border-radius: 10px' data-value='$vAtual'>" . number_format($vAtual, 0) . "</td>";
                  echo "<td data-value='$media'>" . number_format($media, 0) . "</td>";

                  echo "</tr>";
                }
                ?>
              </tbody>
            </table>
            <?php if ($ultimaValidacao) { ?>

              <div class="text-end" style="margin-top:15px; font-size:12px; color:#6c757d;">

                Última validação:

                <strong>
                  <?php echo date_format($ultimaValidacao['TB02117_VALIDAALEIT_DTCAD'], 'd/m/Y H:i'); ?>
                </strong>

                | Mês:
                <strong>
                  <?php echo $ultimaValidacao['TB02117_VALIDAALEIT_MES']; ?>
                </strong>

                | Usuário:
                <strong>
                  <?php echo $ultimaValidacao['TB02117_VALIDAALEIT_OPCAD']; ?>
                </strong>

              </div>

            <?php } ?>
            <!-- mostra que já existe validação -->
            <?php if (!empty($jaValidado)) { ?>
              <script>
                alert("Já existe validação para este contrato e mês.");
              </script>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/inicio.js"></script>

</body>

</html>