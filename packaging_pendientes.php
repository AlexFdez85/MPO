<?php
// packaging_pendientes.php
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once 'config.php';
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin','gerente','produccion'])) {
  header('Location: dashboard.php'); exit;
}

$sql = "
  SELECT
    pr.id,
    pr.estado,
    pr.creado_en,
    pr.autorizado_en,
    pr.orden_id              AS op_id,
    op.fecha                 AS fecha_op,
    pt.lote_produccion       AS lote_produccion,
    COALESCE(u1.nombre,'-')  AS solicitante,
    COALESCE(u2.nombre,'-')  AS autorizador,
    p.nombre                 AS producto
  FROM packaging_requests pr
  LEFT JOIN ordenes_produccion op ON op.id = pr.orden_id
  LEFT JOIN fichas_produccion fp   ON fp.id = op.ficha_id
  LEFT JOIN productos p            ON p.id  = fp.producto_id
  LEFT JOIN usuarios u1            ON u1.id = pr.solicitante_id
  LEFT JOIN usuarios u2            ON u2.id = pr.autorizador_id
  LEFT JOIN (
      SELECT t.orden_id,
             SUBSTRING_INDEX(
               GROUP_CONCAT(t.lote_produccion ORDER BY t.fecha DESC, t.id DESC SEPARATOR ','),
               ',', 1
             ) AS lote_produccion
      FROM productos_terminados t
      GROUP BY t.orden_id
  ) pt ON pt.orden_id = pr.orden_id
  WHERE pr.estado IN ('pendiente','autorizada')
  ORDER BY pr.estado='pendiente' DESC, pr.id DESC
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="mb-3">Solicitudes de empaque</h3>

  <?php if (empty($rows)): ?>
    <div class="alert alert-secondary">No hay solicitudes de empaque.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Estado</th>
          <th>OP</th>
          <th>Lote</th>
          <th>Producto</th>
          <th>Solicitante</th>
          <th>Creada</th>
          <th>Autorizador</th>
          <th>Autorizada</th>
          <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <?php if ($r['estado']==='pendiente'): ?>
                <span class="badge bg-warning text-dark">Pendiente</span>
              <?php elseif ($r['estado']==='autorizada'): ?>
                <span class="badge bg-success">Autorizada</span>
              <?php else: ?>
                <span class="badge bg-secondary"><?= htmlspecialchars($r['estado']) ?></span>
              <?php endif; ?>
            </td>
            <td>#<?= (int)$r['op_id'] ?></td>
            <td><?= htmlspecialchars($r['lote_produccion'] ?? '—') ?></td>
            <td><?= htmlspecialchars($r['producto']??'—') ?></td>
            <td><?= htmlspecialchars($r['solicitante']??'—') ?></td>
            <td><?= htmlspecialchars($r['creado_en']??'—') ?></td>
            <td><?= htmlspecialchars($r['autorizador']??'—') ?></td>
            <td><?= htmlspecialchars($r['autorizado_en']??'—') ?></td>
            <td>
              <a class="btn btn-sm btn-outline-primary" href="packaging_autorizar.php?id=<?= (int)$r['id'] ?>">Abrir</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
