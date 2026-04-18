<?php
// recepcion_historial.php
require 'config.php';

// Sólo admin, gerente o logística pueden verlo
if (!in_array($_SESSION['rol'], ['admin','gerente','logistica'], true)) {
    header('Location: dashboard.php');
    exit;
}

// Traigo todas las recepciones de material
$sql = "
  SELECT rc.id              AS recepcion_id,
         rc.orden_compra_id AS oc_id,
         p.nombre           AS proveedor,
         oc.fecha_emision,
         u_sol.nombre       AS solicitante,
         u_rec.nombre       AS recepcionador,
         rc.fecha_recepcion
    FROM recepciones_cab rc
    JOIN ordenes_compra   oc  ON oc.id = rc.orden_compra_id
    JOIN proveedores      p   ON p.id  = oc.proveedor_id
    JOIN usuarios        u_sol ON u_sol.id = oc.solicitante_id
    JOIN usuarios        u_rec ON u_rec.id = rc.recepcionador_id
   ORDER BY rc.fecha_recepcion DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-secondary">Historial de Recepciones</h3>
  <?php if (empty($rows)): ?>
    <div class="alert alert-info">No hay recepciones registradas.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>#Recep.</th>
          <th>#OC</th>
          <th>Proveedor</th>
          <th>Emitida</th>
          <th>Solicita</th>
          <th>Recibió</th>
          <th>Fecha Recep.</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr>
          <td><?= $r['recepcion_id'] ?></td>
          <td><?= $r['oc_id'] ?></td>
          <td><?= htmlspecialchars($r['proveedor']) ?></td>
          <td><?= $r['fecha_emision'] ?></td>
          <td><?= htmlspecialchars($r['solicitante']) ?></td>
          <td><?= htmlspecialchars($r['recepcionador']) ?></td>
          <td><?= $r['fecha_recepcion'] ?></td>
          <td>
            <a href="recepcion_historial_detalle.php?id=<?= $r['recepcion_id'] ?>"
               class="btn btn-sm btn-outline-primary">
              Ver
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
