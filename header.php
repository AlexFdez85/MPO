<?php
// header.php
require_once 'config.php';

$countAjustesMP    = 0;
$countAjustesIC    = 0;
$countAutOP        = 0;
$pendientesCompra  = 0;
$countPagosOC      = 0;
$countRecepcion    = 0;
$countVentasProceso = 0;
$countEmpaques     = 0;   // <¡ª usaremos este para ¡°Empaques¡±

function qcount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Renderiza un link de men¨² con badge si $count > 0
 */
function menu_with_badge(string $href, string $label, int $count, string $badgeClass = 'bg-danger'): string {
    $badge = $count > 0
        ? "<span class=\"badge $badgeClass position-absolute\" style=\"top:0; right:-0.5rem;\">$count</span>"
        : "";
    // position-relative en el <a> para posicionar bien el badge
    return "<a class=\"nav-link position-relative\" href=\"$href\">$label $badge</a>";
}


if (isset($_SESSION['user_id'])) {
    $rol = $_SESSION['rol'];
    // Ajustes de MP/IC
    if (in_array($rol, ['admin','gerente','produccion','logistica'], true)) {
        $countAjustesMP   = (int)$pdo->query("SELECT COUNT(*) FROM ajustes_mp       WHERE estado = 'pendiente'")->fetchColumn();
        $countAjustesIC   = (int)$pdo->query("SELECT COUNT(*) FROM ajustes_insumos WHERE estado = 'pendiente'")->fetchColumn();
        $countAutOP       = (int)$pdo->query("SELECT COUNT(*) FROM ordenes_produccion WHERE estado_autorizacion = 'pendiente'")->fetchColumn();
        $pendientesCompra = (int)$pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado = 'pendiente'")->fetchColumn();
        $countEmpaques = qcount($pdo, "SELECT COUNT(*) FROM empaques_mov WHERE estado='pendiente'");
        
        // Notificaciones Pagos OC y RecepciÃ³n Material
        $countPagosOC   = (int)$pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado = 'autorizada'")->fetchColumn();
        $countRecepcion = (int)$pdo->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado = 'pagada'")->fetchColumn();
        $countVentasProceso = (int)$pdo ->query("SELECT COUNT(*) FROM ordenes_venta WHERE estado IN ('pendiente','proceso_surtido')") ->fetchColumn();
        $countEmpaques = qcount($pdo, "SELECT COUNT(*) FROM lineas_venta WHERE requiere_empaque=1 AND surtido=0");
    }

}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= defined('APP_NAME') ? APP_NAME : 'A4 MPO' ?></title>
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">a4 MPO</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <?php if ($_SESSION['rol'] === 'admin'): ?>
        <li class="nav-item">
          <a class="nav-link" href="usuarios.php">Us</a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','gerente','operaciones'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="inventario_mp.php">Inv MP</a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','produccion'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="ordenes_produccion.php">Producc</a>
        </li>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['admin','gerente','produccion'])): ?>
  <li class="nav-item">
    <a class="nav-link" href="packaging_pendientes.php">Empaque</a>
  </li>
  
<?php endif; ?>


            <?php if (in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)): ?>
              <li class="nav-item position-relative">
                <a class="nav-link <?= ($_SERVER['SCRIPT_NAME']==='/mpo/ordenes_venta.php'?'active':'') ?>"
                   href="ordenes_venta.php">
                  Pedidos
                  <?php if ($countVentasProceso > 0): ?>
                    <span class="badge bg-danger position-absolute"
                          style="top:0; right:-0.5rem;"><?= $countVentasProceso ?></span>
                  <?php endif; ?>
                </a>
              </li>
            <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','gerente'], true)): ?>
        <li class="nav-item">
          <a class="nav-link" href="crear_mp.php">Reg MP</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="crear_ic.php">Reg IC</a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','gerente'], true)): ?>
        <li class="nav-item position-relative">
          <a class="nav-link" href="autorizar_ajustes.php">
            Aut Ajust Inv
            <?php $totalAjs = $countAjustesMP + $countAjustesIC; ?>
            <?php if ($totalAjs > 0): ?>
            <span class="badge bg-danger position-absolute" style="top:0; right:-0.5rem;"><?= $totalAjs ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item position-relative">
          <a class="nav-link" href="autorizar_produccion.php">
            Aut OP
            <?php if ($countAutOP > 0): ?>
            <span class="badge bg-danger position-absolute" style="top:0; right:-0.5rem;"><?= $countAutOP ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li class="nav-item position-relative">
          <a class="nav-link" href="autorizar_compra.php">
            Aut OC
            <?php if ($pendientesCompra > 0): ?>
            <span class="badge bg-danger position-absolute" style="top:0; right:-0.5rem;"><?= $pendientesCompra ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','gerente'], true)): ?>
        <li class="nav-item position-relative">
          <a class="nav-link <?= ($_SERVER['SCRIPT_NAME']==='/mpo/pagos_compra.php'?'active':'') ?>"
             href="pagos_compra.php">
            Pagos OC
            <?php if ($countPagosOC > 0): ?>
            <span class="badge bg-danger position-absolute" style="top:0; right:-0.5rem;"><?= $countPagosOC ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>

        <?php if (in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)): ?>
        <li class="nav-item position-relative">
          <a class="nav-link <?= ($_SERVER['SCRIPT_NAME']==='/mpo/recepcion_compra.php'?'active':'') ?>"
             href="recepcion_compra.php">
            Ingreso Material
            <?php if ($countRecepcion > 0): ?>
            <span class="badge bg-danger position-absolute" style="top:0; right:-0.5rem;"><?= $countRecepcion ?></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endif; ?>

      </ul>
      <div class="d-flex align-items-center">
        <span class="navbar-text text-white me-3">
          <?= htmlspecialchars($_SESSION['nombre']) ?> | <?= ucfirst($_SESSION['rol']) ?>
        </span>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>

<div class="container mt-4">


