<?php
require 'config.php';
if (!in_array($_SESSION['rol'], ['logistica','admin','gerente','produccion'])) {
  header('Location: dashboard.php');
  exit;
}

// 1) Procesar aprobación/rechazo
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion'], $_POST['sol_id'])) {
  $sol = intval($_POST['sol_id']);
  $est = $_POST['accion']==='aprobar' ? 'autorizada' : 'rechazada';

  $pdo->prepare("
    UPDATE packaging_requests
       SET estado = ?, autorizador_id = ?, autorizado_en = NOW()
     WHERE id = ?
  ")->execute([$est, $_SESSION['user_id'], $sol]);

  // Si autorizó, aplicamos el movimiento real en insumos:
  if ($est==='autorizada') {
    $items = $pdo->prepare("
      SELECT insumo_comercial_id, cantidad
        FROM packaging_request_items
       WHERE request_id = ?
    ");
    $items->execute([$sol]);
    $mov = $pdo->prepare("
      INSERT INTO movimientos_insumos
        (insumo_id, tipo, cantidad, comentario, usuario_id, creado_en)
      VALUES (?, 'salida', ?, ?, ?, NOW())
    ");
    foreach ($items as $it) {
      $mov->execute([
        $it['insumo_comercial_id'],
        $it['cantidad'],
        "Envases para orden {$orden_id}",
        $_SESSION['user_id']
      ]);
    }
  }
  header('Location: autorizar_envases.php');
  exit;
}

// 2) Listar pendientes
$pend = $pdo->query("
  SELECT pr.id, pr.orden_id, u.nombre AS solicitante, pr.creado_en
    FROM packaging_requests pr
    JOIN usuarios u ON u.id = pr.solicitante_id
   WHERE pr.estado = 'pendiente'
   ORDER BY pr.creado_en
")->fetchAll();

include 'header.php';
?>
<div class="container mt-4">
  <h3>Autorizar Solicitudes de Envases</h3>
  <?php if (empty($pend)): ?>
    <div class="alert alert-secondary">No hay solicitudes pendientes.</div>
  <?php else: ?>
    <table class="table">
      <thead><tr>
        <th>ID</th><th>Orden</th><th>Solicita</th><th>Fecha</th><th>Acción</th>
      </tr></thead>
      <tbody>
      <?php foreach($pend as $s): ?>
        <tr>
          <td><?= $s['id'] ?></td>
          <td><?= $s['orden_id'] ?></td>
          <td><?= htmlspecialchars($s['solicitante']) ?></td>
          <td><?= $s['creado_en'] ?></td>
          <td>
            <form method="POST" class="d-inline">
              <input type="hidden" name="sol_id" value="<?= $s['id'] ?>">
              <button name="accion" value="aprobar" class="btn btn-sm btn-success">Aprobar</button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="sol_id" value="<?= $s['id'] ?>">
              <button name="accion" value="rechazar" class="btn btn-sm btn-danger">Rechazar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
