<?php
// autorizar_ajustes.php

// 1) Mostrar errores (solo desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Solo roles admin/gerente pueden entrar
if (!in_array($_SESSION['rol'], ['gerente','admin'])) {
    header('Location: dashboard.php');
    exit;
}

$user = $_SESSION['user_id'];
$now  = date('Y-m-d H:i:s');

// 2) Procesar POST de aprobar/rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['aj_id'], $_POST['origen'])) {
    $id     = intval($_POST['aj_id']);
    $acc    = $_POST['accion'];            // 'aprobar' o 'rechazar'
    $origen = $_POST['origen'];            // 'mp' o 'ic'

    // Determinar par¨¢metros seg¨²n el origen
    $isMp       = ($origen === 'mp');
    $tablaAj    = $isMp ? 'ajustes_mp' : 'ajustes_insumos';
    $tablaMov   = $isMp ? 'movimientos_mp' : 'movimientos_insumos';
    $colItem    = $isMp ? 'mp_id' : 'insumo_id';
    $colStock   = $isMp ? 'existencia' : 'stock';
    $tablaStock = $isMp ? 'materias_primas' : 'insumos_comerciales';

    $colAuth = $isMp ? 'autorizado_por'   : 'autorizador_id';
    $colDate = $isMp ? 'fecha_autorizacion': 'autorizado_en';

    if ($acc === 'aprobar') {
        // 2.a) Marcar ajuste como autorizado
        $stmt = $pdo->prepare(
            "UPDATE {$tablaAj}
                SET estado           = 'autorizado',
                    {$colAuth}       = ?,
                    {$colDate}       = ?
              WHERE id = ?"
        );
        $stmt->execute([$user, $now, $id]);

        // 2.b) Traer datos del ajuste para movimiento
        $sql = "SELECT {$colItem} AS item_id, cantidad FROM {$tablaAj} WHERE id = ?";
        $stmtAj = $pdo->prepare($sql);
        $stmtAj->execute([$id]);
        $f = $stmtAj->fetch(PDO::FETCH_ASSOC);

        if ($f) {
            $pdo->beginTransaction();

            // Insertar movimiento
            $tipoMov = ($f['cantidad'] >= 0 ? 'entrada' : 'salida');
            $insMov  = $pdo->prepare(
                "INSERT INTO {$tablaMov}
                    ({$colItem}, tipo, cantidad, fecha, usuario_id, comentario)
                 VALUES (?, ?, ?, NOW(), ?, ?)"
            );
            $insMov->execute([
                $f['item_id'],
                $tipoMov,
                abs($f['cantidad']),
                $user,
                "Ajuste autorizado #{$id}"
            ]);

            // Ajustar stock
            $updStock = $pdo->prepare(
                "UPDATE {$tablaStock}
                    SET {$colStock} = {$colStock} + ?
                  WHERE id = ?"
            );
            $updStock->execute([$f['cantidad'], $f['item_id']]);

            $pdo->commit();
        }

    } else {
        // 2.d) Rechazar
        $stmt = $pdo->prepare(
            "UPDATE {$tablaAj}
                SET estado             = 'rechazado',
                    autorizado_por     = ?,
                    fecha_autorizacion = ?
              WHERE id = ?"
        );
        $stmt->execute([$user, $now, $id]);
    }

    // 2.e) Recargar pendientes
    header('Location: autorizar_ajustes.php');
    exit;
}

// 3) Ajustes pendientes de MP
$ajMp = $pdo->query(
    "SELECT a.id,
            mp.nombre AS item,
            a.cantidad,
            u.nombre AS solicitante,
            a.comentario,
            'mp' AS origen
       FROM ajustes_mp a
       JOIN materias_primas mp ON a.mp_id = mp.id
       JOIN usuarios u         ON a.solicitante_id = u.id
      WHERE a.estado = 'pendiente'"
)->fetchAll(PDO::FETCH_ASSOC);

// 4) Ajustes pendientes de Insumos Comerciales
$ajIc = $pdo->query(
    "SELECT a.id,
            ic.nombre AS item,
            a.cantidad,
            u.nombre AS solicitante,
            a.comentario,
            'ic' AS origen
       FROM ajustes_insumos a
       JOIN insumos_comerciales ic ON a.insumo_id = ic.id
       JOIN usuarios u             ON a.usuario_id = u.id
      WHERE a.estado = 'pendiente'"
)->fetchAll(PDO::FETCH_ASSOC);

// 5) Unir y ordenar
$pend = array_merge($ajMp, $ajIc);
usort($pend, function($a, $b){ return $a['id'] - $b['id']; });

// 6) Mostrar
include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-warning">Autorizar Ajustes de Inventario</h3>

  <?php if (empty($pend)): ?>
    <div class="alert alert-secondary">No hay ajustes pendientes.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>#</th><th>Item</th><th>Cant.</th><th>Solicita</th><th>Comentario</th><th>Accion</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($pend as $r): ?>
        <tr>
          <td><?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['item']) ?></td>
          <td><?= number_format($r['cantidad'],2,',','.') ?></td>
          <td><?= htmlspecialchars($r['solicitante']) ?></td>
          <td><?= htmlspecialchars($r['comentario']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="aj_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="origen" value="<?= $r['origen'] ?>">
              <button name="accion" value="aprobar" class="btn btn-sm btn-success">Aprobar</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="aj_id" value="<?= $r['id'] ?>">
              <input type="hidden" name="origen" value="<?= $r['origen'] ?>">
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
