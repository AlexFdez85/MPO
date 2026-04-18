<?php
// ventas.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';

// Solo admin, gerente o logistica pueden ver el listado
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente','logistica'])) {
    header('Location: dashboard.php');
    exit;
}

// 1) Traer todas las órdenes de venta
$ventas = $pdo->query("
    SELECT 
      ov.id,
      c.nombre       AS cliente,
      ov.fecha       AS fecha,
      ov.estado      AS estado,
      u.nombre       AS creador
    FROM ordenes_venta ov
    LEFT JOIN clientes c ON c.id = ov.cliente_id
    LEFT JOIN usuarios u  ON u.id = ov.usuario_creador
    ORDER BY ov.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-primary mb-3">Órdenes de Venta</h3>

  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Cliente</th>
        <th>Fecha</th>
        <th>Estado</th>
        <th>Creada por</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ventas as $v): ?>
      <tr>
        <td>#<?= $v['id'] ?></td>
        <td><?= htmlspecialchars($v['cliente'] ?: '–') ?></td>
        <td><?= htmlspecialchars($v['fecha']) ?></td>
        <td>
          <?php
            // Reemplazamos match() por switch para compatibilidad
            switch ($v['estado']) {
              case 'pendiente': $badge = 'warning'; break;
              case 'enviada':   $badge = 'success'; break;
              default:          $badge = 'secondary'; break;
            }
          ?>
          <span class="badge bg-<?= $badge ?>">
            <?= ucfirst($v['estado']) ?>
          </span>
        </td>
        <td><?= htmlspecialchars($v['creador']) ?></td>
        <td>
          <a href="ordenes_venta.php" class="btn btn-sm btn-outline-primary">
            Ver detalle
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include 'footer.php'; ?>

