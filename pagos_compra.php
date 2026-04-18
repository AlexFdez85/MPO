<?php
// pagos_compra.php
require 'config.php';

// s車lo admin/gerente
if (!in_array($_SESSION['rol'], ['admin','gerente'], true)) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['oc_id'])) {
    $oc = intval($_POST['oc_id']);
    // Calcular subtotal de líneas (neto, sin IVA)
    $s = $pdo->prepare("SELECT COALESCE(SUM(subtotal),0) FROM lineas_compra WHERE orden_compra_id = ?");
    $s->execute([$oc]);
    $subtotal = (float)$s->fetchColumn();
    $iva = round($subtotal * 0.16, 2);
    $total = $subtotal + $iva;
    $pdo->prepare("
      UPDATE ordenes_compra
        SET estado        = 'pagada',
             fecha_pago    = NOW(),
             subtotal_neto = ?,
             iva_monto     = ?,
             total_con_iva = ?
       WHERE id = ?
    ")->execute([$subtotal, $iva, $total, $oc]);
    header("Location: recepcion_compra.php");
    exit;
}

// 1) Traer OCs autorizadas + productos + subtotal
$ocs = $pdo->query("
  SELECT 
    oc.id,
    p.nombre AS proveedor,
    oc.fecha_emision,
    u.nombre AS solicitante,
    -- productos en un solo campo
    GROUP_CONCAT(DISTINCT COALESCE(mp.nombre, ic.nombre) 
                 SEPARATOR ', ') AS productos,
    -- suma de subtotales
    COALESCE(SUM(lc.subtotal),0) AS subtotal
  FROM ordenes_compra oc
  JOIN proveedores p ON p.id = oc.proveedor_id
  JOIN usuarios    u ON u.id = oc.solicitante_id
  LEFT JOIN lineas_compra       lc ON lc.orden_compra_id = oc.id
  LEFT JOIN materias_primas     mp ON mp.id = lc.mp_id
  LEFT JOIN insumos_comerciales ic ON ic.id = lc.ic_id
  WHERE oc.estado = 'autorizada'
  GROUP BY oc.id, p.nombre, oc.fecha_emision, u.nombre
  ORDER BY oc.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-danger">Marcar OC como Pagada</h3>
  <?php if (empty($ocs)): ?>
    <div class="alert alert-info">No hay ordenes autorizadas pendientes de pago.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Proveedor</th>
          <th>Fecha emision</th>
          <th>Solicita</th>
          <th>Productos</th>
          <th class="text-end">Sub-total</th>
          <th class="text-end">IVA (16%)</th>
          <th class="text-end">Total</th>
          <th>Accion</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($ocs as $o): 
        // calc IVA y total
        $sub  = floatval($o['subtotal']);
        $iva  = $sub * 0.16;
        $tot  = $sub + $iva;
      ?>
        <tr>
          <td><?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['proveedor']) ?></td>
          <td><?= $o['fecha_emision'] ?></td>
          <td><?= htmlspecialchars($o['solicitante']) ?></td>
          <td><?= htmlspecialchars($o['productos']) ?></td>
          <td class="text-end">
            $<?= number_format($sub,2,',','.') ?>
          </td>
          <td class="text-end">
            $<?= number_format($iva,2,',','.') ?>
          </td>
          <td class="text-end">
            $<?= number_format($tot,2,',','.') ?>
          </td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="oc_id" value="<?= $o['id'] ?>">
              <button class="btn btn-sm btn-danger">Marcar como pagada</button>
            </form>
          </td>
          <td>
            <a href="autorizar_compra.php?view=historial&oc=<?= $o['id'] ?>"
               class="btn btn-sm btn-outline-secondary">Ver OC</a>
            <a href="ver_oc_pdf.php?id=<?= $o['id'] ?>" target="_blank"
               class="btn btn-sm btn-outline-primary">PDF</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
