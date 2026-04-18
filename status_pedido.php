<?php
// status_pedido.php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';

function reservadoLote(PDO $pdo, int $lineaId, int $prodId, int $presId, string $loteCodigo): float {
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad),0)
        FROM reservas_venta
        WHERE linea_venta_id = ?
          AND producto_id = ?
          AND presentacion_id = ?
          AND lote_codigo = ?
          AND estado = 'activa'
    ");
    $st->execute([$lineaId, $prodId, $presId, $loteCodigo]);
    return (float)$st->fetchColumn();
}

// Reservado TOTAL del lote (todas las líneas) -> para calcular "Disponible" real del lote
function reservadoTotalLote(PDO $pdo, int $prodId, int $presId, string $loteCodigo): float {
    $st = $pdo->prepare("
        SELECT COALESCE(SUM(cantidad),0)
        FROM reservas_venta
        WHERE producto_id = ?
          AND presentacion_id = ?
          AND lote_codigo = ?
          AND estado = 'activa'
    ");
    $st->execute([$prodId, $presId, $loteCodigo]);
    return (float)$st->fetchColumn();
}


// 1) Autenticaci¨®n y permisos
if (!isset($_SESSION['user_id'])
  || !in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)
) {
  header('Location: dashboard.php');
  exit;
}
$rol = $_SESSION['rol'];

// 2) Leer ID de la orden
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
  die('Pedido inv¨¢lido');
}

// 3) Traer cabecera de la orden
$stmt = $pdo->prepare("
  SELECT ov.*, c.nombre AS cliente
    FROM ordenes_venta ov
    LEFT JOIN clientes c ON c.id = ov.cliente_id
   WHERE ov.id = ?
");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
  die('Pedido no encontrado');
}

// 4) Traer l¨ªneas del pedido
$stmt = $pdo->prepare("
  SELECT lv.id, lv.producto_id, p.nombre AS producto,
         lv.presentacion_id, pr.nombre AS presentacion,
         lv.cantidad AS pedida
    FROM lineas_venta lv
    JOIN productos p       ON p.id = lv.producto_id
    JOIN presentaciones pr ON pr.id = lv.presentacion_id
   WHERE lv.orden_venta_id = ?
");
$stmt->execute([$id]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5) Procesar POST de apartado (reservas activas por lote)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_surtido'])) {
    // Mapa para resolver producto/presentación por línea
    $lineMap = [];
    foreach ($lines as $ln) {
        $lineMap[(int)$ln['id']] = [
            'producto_id'     => (int)$ln['producto_id'],
            'presentacion_id' => (int)$ln['presentacion_id'],
        ];
    }

    $posted = $_POST['surtido'] ?? [];
    if (!is_array($posted)) $posted = [];

    $pdo->beginTransaction();
    try {
        foreach ($posted as $lineaIdStr => $lotes) {
            $lineaId = (int)$lineaIdStr;
            if (!$lineaId || !isset($lineMap[$lineaId]) || !is_array($lotes)) continue;
            $prodId = $lineMap[$lineaId]['producto_id'];
            $presId = $lineMap[$lineaId]['presentacion_id'];

            // Estado actual de reservas activas para esta línea (por lote)
            $curStmt = $pdo->prepare("
                SELECT id, lote_codigo, cantidad
                FROM reservas_venta
                WHERE linea_venta_id = ? AND estado = 'activa'
            ");
            $curStmt->execute([$lineaId]);
            $current = [];
            foreach ($curStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $current[$r['lote_codigo']] = ['id' => (int)$r['id'], 'cantidad' => (float)$r['cantidad']];
            }

            // Normaliza claves (lote) y valores (cantidades)
            $seen = [];
            foreach ($lotes as $loteCodigo => $qStr) {
                $lote = trim((string)$loteCodigo);
                $q    = (float)$qStr;
                if ($q < 0) $q = 0;
                $seen[$lote] = true;

                // Topes por disponibilidad del lote:
                // disponible = stock_lote - (reservado_total_lote - reservado_de_esta_linea_en_este_lote)
                $stockLote = (float)$pdo->query("
                    SELECT COALESCE(SUM(cantidad),0) FROM productos_terminados
                    WHERE producto_id = {$prodId} AND presentacion_id = {$presId}
                      AND lote_produccion = " . $pdo->quote($lote)
                )->fetchColumn();
                $reservadoTotal = reservadoTotalLote($pdo, $prodId, $presId, $lote);
                $reservadoLinea = $current[$lote]['cantidad'] ?? 0.0;
                $dispLote       = max(0, $stockLote - max(0, $reservadoTotal - $reservadoLinea));
                if ($q > $dispLote) $q = $dispLote; // clamp

                if (isset($current[$lote])) {
                    if ($q > 0) {
                        // Actualiza cantidad
                        $upd = $pdo->prepare("UPDATE reservas_venta SET cantidad = ? WHERE id = ?");
                        $upd->execute([$q, $current[$lote]['id']]);
                    } else {
                        // Liberar si el usuario puso 0
                        $pdo->prepare("UPDATE reservas_venta SET estado='liberada' WHERE id = ?")
                            ->execute([$current[$lote]['id']]);
                    }
                } else {
                    if ($q > 0) {
                        // Crear nueva reserva activa
                        $ins = $pdo->prepare("
                          INSERT INTO reservas_venta
                            (orden_venta_id, linea_venta_id, producto_id, presentacion_id, lote_codigo, cantidad, estado, creado_por)
                          VALUES
                            (?, ?, ?, ?, ?, ?, 'activa', ?)
                        ");
                        $ins->execute([$id, $lineaId, $prodId, $presId, $lote, $q, $_SESSION['user_id'] ?? null]);
                    }
                }
            }

            // Lotes que existían y ya no vienen en POST -> liberar
            foreach ($current as $lote => $info) {
                if (!isset($seen[$lote])) {
                    $pdo->prepare("UPDATE reservas_venta SET estado='liberada' WHERE id = ?")
                        ->execute([$info['id']]);
                }
            }
        }

        $pdo->commit();
        header("Location: status_pedido.php?id={$id}&ok=1");
    exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        die('Error al actualizar reservas: ' . htmlspecialchars($e->getMessage()));
    }
}

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-primary mb-3">Avance del Pedido #<?= $order['id'] ?></h3>
  <p>
    <strong>Cliente:</strong> <?= htmlspecialchars($order['cliente']) ?> -
    <strong>Entrega:</strong> <?= htmlspecialchars($order['fecha_entrega']) ?>
  </p>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Avance guardado correctamente.</div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="update_surtido" value="1">

    <?php foreach ($lines as $line): 
      // 6) Lotes disponibles para esta l¨ªnea
      $stmt = $pdo->prepare("
        SELECT lote_produccion,
               SUM(cantidad) AS disponible
          FROM productos_terminados
         WHERE producto_id = ?
           AND presentacion_id = ?
         GROUP BY lote_produccion
      ");
      $stmt->execute([
        $line['producto_id'],
        $line['presentacion_id']
      ]);
      $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // 7) Reservas (previo) por lote para esta línea
      $stmt2 = $pdo->prepare("
        SELECT lote_codigo, SUM(cantidad) AS reservado
        FROM reservas_venta
        WHERE orden_venta_id = ? AND linea_venta_id = ? AND estado = 'activa'
        GROUP BY lote_codigo
      ");
      $stmt2->execute([$id, $line['id']]);
      $prev = [];
      foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $prev[$r['lote_codigo']] = (float)$r['reservado'];
      }

      // 8) Totales para el progreso
      $totalPrev = array_sum($prev); // total reservado (activa) de la línea
      $porc = $line['pedida']>0
            ? min(100, round($totalPrev / $line['pedida'] * 100))
            : 0;
    ?>
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title mb-3">
            <?= htmlspecialchars($line['producto']) ?>
            <small class="text-muted">- <?= htmlspecialchars($line['presentacion']) ?></small>
          </h5>
          <p>
            <strong>Pedida:</strong> <?= $line['pedida'] ?> 
            &nbsp;&nbsp;
            <strong>Surtido:</strong> <?= number_format($totalPrev,2) ?>
            (<span class="badge bg-info"><?= $porc ?>%</span>)
          </p>
          <div class="progress mb-3" style="height: 1.5rem;">
            <div class="progress-bar" role="progressbar"
                 style="width: <?= $porc ?>%;">
              <?= $porc ?>%
            </div>
          </div>

          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Lote</th>
                <th class="text-end">Disponible</th>
                <th class="text-end">Previo</th>
                <th class="text-end">A surtir</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lots as $lot):
                $loteName      = $lot['lote_produccion'];
                $stockLote     = (float)$lot['disponible']; // físico en ese lote
                $reservadoTot  = reservadoTotalLote($pdo, (int)$line['producto_id'], (int)$line['presentacion_id'], $loteName);
                $disponibleLot = max(0, $stockLote - $reservadoTot); // lo que realmente queda libre
                $sPrev         = $prev[$loteName] ?? 0.0;            // reservado por ESTA línea
               ?>
                <tr>
                  <td><?= htmlspecialchars($loteName) ?></td>
                  <td class="text-end"><?= number_format($disponibleLot,2) ?></td>
                  <td class="text-end"><?= number_format($sPrev,2) ?></td>
                  <td class="text-end" style="width:120px">
                    <input type="number" name="surtido[<?= $line['id'] ?>][<?= htmlspecialchars($loteName) ?>]"
                           class="form-control form-control-sm"
                           min="0" max="<?= $disponibleLot ?>"
                           step="0.01"
                           value="<?= $sPrev>0 ? $sPrev : '' ?>">
                  </td>
                </tr>
              <?php endforeach; ?>

              <?php if (empty($lots)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">
                    No hay lotes disponibles para surtir.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>

    <button class="btn btn-primary">Actualizar Avance</button>
  </form>

  <div class="mt-4">
    <a href="ordenes_venta.php" class="btn btn-outline-secondary">
      &larr; Volver al listado de pedidos
    </a>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<?php include 'footer.php'; ?>
