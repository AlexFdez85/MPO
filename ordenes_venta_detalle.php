<?php
// ordenes_venta_detalle.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';


// 1) Autenticación y permisos
if (!isset($_SESSION['user_id'])
    || !in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)
) {
    header('Location: dashboard.php');
    exit;
}

// 2) Leer ID de la orden de venta
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('Pedido no válido');
}

// 3) Obtener cabecera de la orden
$stmt = $pdo->prepare("
  SELECT ov.*,
         c.nombre AS cliente,
         d.nombre AS distribuidor,
         u.nombre AS creador
  FROM ordenes_venta ov
  LEFT JOIN clientes c ON c.id = ov.cliente_id
  LEFT JOIN clientes d ON d.id = ov.distribuidor_id
  LEFT JOIN usuarios u  ON u.id = ov.usuario_creador
  WHERE ov.id = ?
");
$stmt->execute([$id]);
$ov = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ov) {
    die('Pedido no encontrado');
}

// 4) Helper: disponibles en almacén (mismo criterio visual que en ordenes_venta.php)
if (!function_exists('getDisponibleOV')) {
  function getDisponibleOV(PDO $pdo, int $prodId, int $presId): float {
    $sql = "
      SELECT
        COALESCE((
          SELECT SUM(pt.cantidad)
          FROM productos_terminados pt
          WHERE pt.producto_id = ? AND pt.presentacion_id = ?
       ),0) AS stock,
        COALESCE((
          SELECT SUM(rv.cantidad)
          FROM reservas_venta rv
          WHERE rv.producto_id = ? AND rv.presentacion_id = ?
           AND rv.estado = 'activa'
        ),0) AS apartado
    ";
   $st = $pdo->prepare($sql);
   $st->execute([$prodId, $presId, $prodId, $presId]);
    $r = $st->fetch(PDO::FETCH_ASSOC) ?: ['stock' => 0, 'apartado' => 0];
    $disp = (float)$r['stock'] - (float)$r['apartado'];
    return $disp > 0 ? $disp : 0.0;
  }
}

$stmt = $pdo->prepare("
  SELECT lv.id,
         lv.producto_id,
         lv.presentacion_id,
         p.nombre       AS producto,
         pr.nombre      AS presentacion,
         lv.cantidad    AS pedida
    FROM lineas_venta lv
    JOIN productos      p  ON p.id = lv.producto_id
    JOIN presentaciones pr ON pr.id = lv.presentacion_id
   WHERE lv.orden_venta_id = ?
   ORDER BY lv.id
");
$stmt->execute([$id]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Obtener surtidos por lote para esta orden
$stmt = $pdo->prepare("
  SELECT 
    sv.id,
    p.nombre          AS producto,
    pr.nombre         AS presentacion,
    sv.lote_produccion,
    sv.cantidad,
    sv.fecha_surtido
  FROM surtidos_venta sv
  JOIN lineas_venta    lv ON lv.id = sv.linea_venta_id
  JOIN productos       p  ON p.id  = lv.producto_id
  JOIN presentaciones  pr ON pr.id = lv.presentacion_id
  WHERE sv.orden_venta_id = ?
  ORDER BY sv.fecha_surtido, sv.id
");
$stmt->execute([$id]);
$surtidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mt-4">
  <h3 class="text-primary mb-3">Detalle de Pedido #<?= $ov['id'] ?></h3>

  <div class="card mb-4 p-3">
<p>
  <strong>Cliente:</strong>
  <?php
    $cli  = $ov['cliente']      ?? null;
    $dist = $ov['distribuidor'] ?? null;

    if ($dist && $cli) {
        echo htmlspecialchars("$dist ($cli)");
    } elseif ($dist) {
        echo htmlspecialchars($dist);
    } elseif ($cli) {
        echo htmlspecialchars($cli);
    } else {
        echo '—';
    }
  ?>
</p>
    <p><strong>Creado:</strong> <?= htmlspecialchars($ov['fecha']) ?></p>
    <p><strong>Entrega:</strong> <?= htmlspecialchars($ov['fecha_entrega']) ?></p>
    <p><strong>Estado:</strong>
      <?php
        $e = $ov['estado'];
        $badge = $e==='pendiente' ? 'warning'
               : ($e==='surtido'   ? 'info'
               : ($e==='entregado' ? 'success':'secondary'));
      ?>
      <span class="badge bg-<?= $badge ?>"><?= ucfirst($e) ?></span>
    </p>
    <p><strong>Creador:</strong> <?= htmlspecialchars($ov['creador']) ?></p>
    <p><strong>Paqueteria?:</strong> <?= $ov['incluye_paquete'] ? 'Sí' : 'No' ?></p>
  </div>

  <h5>Productos del Pedido</h5>
  <table class="table table-striped align-middle mb-5">
    <thead>
      <tr>
        <th>#</th>
        <th>Producto</th>
        <th>Presentación</th>
        <th class="text-end">Cantidad Pedida</th>
        <th class="text-end">Disponibles (pzas)</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($lines)): ?>
        <tr>
            <td colspan="5" class="text-center text-muted">
            No hay productos registrados en este pedido.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach($lines as $ln): ?>
        <?php
            $disp = getDisponibleOV(
              $pdo,
              (int)$ln['producto_id'],
              (int)$ln['presentacion_id']
            );
          ?>
          <tr>
            <td>#<?= $ln['id'] ?></td>
            <td><?= htmlspecialchars($ln['producto']) ?></td>
            <td><?= htmlspecialchars($ln['presentacion']) ?></td>
            <td class="text-end"><?= number_format((float)$ln['pedida'], 2) ?></td>
            <td class="text-end"><?= number_format($disp, 2) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- ========================= -->
  <!--  Piezas Apartadas (Reservas activas) -->
  <!-- ========================= -->
  <h5 class="mt-4">Piezas Apartadas</h5>
  <?php
    // Mostrar reservas activas por pieza/lote
    $qApart = $pdo->prepare("
      SELECT
        rv.id,
        p.nombre  AS producto,
        pr.nombre AS presentacion,
        rv.lote_codigo AS lote,
        rv.cantidad,
        rv.creado_en
      FROM reservas_venta rv
      JOIN productos p       ON p.id = rv.producto_id
      JOIN presentaciones pr ON pr.id = rv.presentacion_id
      WHERE rv.orden_venta_id = ?
        AND rv.estado = 'activa'
      ORDER BY rv.creado_en DESC
    ");
    $qApart->execute([$id]);   // <-- usa el id de la OV que ya tienes en la página
    $apartadas = $qApart->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Producto</th>
        <th>Presentación</th>
        <th>Lote</th>
        <th class="text-end">Cantidad</th>
        <th>Fecha Apartado</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($apartadas)): ?>
        <?php foreach ($apartadas as $a): ?>
          <tr>
            <td>#<?= (int)$a['id'] ?></td>
            <td><?= htmlspecialchars($a['producto']) ?></td>
            <td><?= htmlspecialchars($a['presentacion']) ?></td>
            <td><?= htmlspecialchars($a['lote']) ?></td>
            <td class="text-end"><?= number_format((float)$a['cantidad'], 2) ?></td>
            <td><?= htmlspecialchars($a['creado_en']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="text-center text-muted">No hay piezas apartadas.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="mt-3">
    <a href="ordenes_venta.php" class="btn btn-outline-secondary">
      &larr; Volver al listado
    </a>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
