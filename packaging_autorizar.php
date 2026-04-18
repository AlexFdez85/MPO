<?php
// packaging_autorizar.php — Autorizar/ajustar renglones de empaque
ini_set('display_errors',1); ini_set('display_startup_errors',1); error_reporting(E_ALL);
require_once 'config.php';
require_once 'lib_packaging.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['admin','gerente','produccion'])) {
  header('Location: dashboard.php'); exit;
}

$reqId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($reqId <= 0) die('ID inválido');
$uid   = (int)($_SESSION['user_id'] ?? 0); // usar user_id de forma consistente

// ---- Handlers POST (antes de cargar la vista) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Eliminar solicitud vacía
  if (isset($_POST['eliminar_vacia'])) {
    $pdo->prepare("DELETE FROM packaging_requests WHERE id = ?")->execute([$reqId]);
    header('Location: packaging_pendientes.php?clean=1'); exit;
  }
  // Rechazar solicitud completa
  if (isset($_POST['rechazar'])) {
    $motivoGeneral = trim($_POST['motivo_general'] ?? '');
    try {
      packaging_reject_request($pdo, $reqId, $uid, $motivoGeneral);
      header('Location: packaging_pendientes.php?rech=1'); exit;
    } catch (Throwable $e) {
      http_response_code(500);
      die('Error al rechazar: '.$e->getMessage());
    }
  }
  // Autorizar y descontar
  if (isset($_POST['autorizar'])) {
    // Persistir ajustes de la forma (aprobado/cantidad/motivo) antes de autorizar
    $data = $_POST['item'] ?? [];
    if (is_array($data) && !empty($data)) {
      $up = $pdo->prepare("
        UPDATE packaging_request_items
           SET cantidad_autorizada = ?, aprobado = ?
         WHERE id = ? AND request_id = ?
      ");
      foreach ($data as $itemId => $r) {
        $ok   = isset($r['ok']) ? 1 : 0;
        $qty  = isset($r['qty']) ? (float)$r['qty'] : 0.0;
        $qty  = $ok ? $qty : 0.0; // si no apruebas, deja 0 autorizado
        $up->execute([$qty, $ok, (int)$itemId, $reqId]);
      }
    }
    try {
      // La librería maneja la transacción (no abrir/commit aquí)
      packaging_authorize_and_consume($pdo, $reqId, $uid);
      header('Location: packaging_autorizar.php?id='.$reqId.'&ok=1'); exit;
    } catch (Throwable $e) {
      http_response_code(500);
      die('Error al autorizar: '.$e->getMessage());
    }
  }
}

// Carga cabecera de la solicitud
$hdr = $pdo->prepare("
  SELECT pr.*, op.id AS op_id, p.nombre AS producto, u1.nombre AS solicitante
    FROM packaging_requests pr
    JOIN ordenes_produccion op ON op.id = pr.orden_id
    JOIN fichas_produccion fp ON fp.id = op.ficha_id
    JOIN productos p          ON p.id  = fp.producto_id
    JOIN usuarios  u1         ON u1.id = pr.solicitante_id
   WHERE pr.id = ?
");
$hdr->execute([$reqId]);
$req = $hdr->fetch(PDO::FETCH_ASSOC);
if (!$req) die('Solicitud no encontrada');

// Carga renglones
$itStmt = $pdo->prepare("
  SELECT it.id, it.insumo_comercial_id, it.cantidad,
         it.cantidad_solicitada, it.cantidad_autorizada, it.aprobado,
         ic.nombre AS insumo, ic.unidad
    FROM packaging_request_items it
    JOIN insumos_comerciales ic ON ic.id = it.insumo_comercial_id
   WHERE it.request_id = ?
   ORDER BY ic.nombre
");
$itStmt->execute([$reqId]);
$items = $itStmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<?php if (empty($items)): ?>
  <div class="alert alert-warning">Esta solicitud no tiene renglones de empaque.</div>
  <form method="post" class="mt-2">
    <button name="eliminar_vacia" class="btn btn-outline-danger"
            onclick="return confirm('¿Eliminar esta solicitud vacía?');">
      Eliminar solicitud
    </button>
  </form>
<?php endif; ?>


<div class="container mt-4">
  <h3 class="mb-3">Autorizar empaque — OP #<?= (int)$req['orden_id'] ?> · <?= htmlspecialchars($req['producto']) ?></h3>
  <p class="text-muted mb-4">
    Solicitó: <strong><?= htmlspecialchars($req['solicitante']) ?></strong> ·
    Creada: <strong><?= htmlspecialchars($req['creado_en']) ?></strong> ·
    Estado actual: +  <?php
    $estadoKey = strtolower(trim($req['estado'] ?? 'pendiente'));
    $labelMap  = ['pendiente' => 'En espera', 'autorizada' => 'Revisada', 'rechazada' => 'Rechazada'];
    $colorMap  = ['pendiente' => 'secondary', 'autorizada' => 'success',  'rechazada' => 'danger'];
    $estadoTxt   = $labelMap[$estadoKey] ?? ucfirst($estadoKey);
    $estadoClass = $colorMap[$estadoKey] ?? 'secondary';
  ?>
  <span class="badge bg-<?= $estadoClass ?>"><?= $estadoTxt ?></span>
  </p>

  <form method="post" class="card p-3 mb-4">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Insumo</th>
            <th class="text-end">Solicitado</th>
            <th class="text-center">Aprobar</th>
            <th style="width:160px">Cantidad aprobada</th>
            <th>Motivo (si omites o ajustas)</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($items as $it):
                $sol = (float)($it['cantidad_solicitada'] ?? $it['cantidad'] ?? 0);
                $apb = isset($it['cantidad_autorizada']) && $it['cantidad_autorizada'] !== null
                       ? (float)$it['cantidad_autorizada'] : $sol;
                $chk = (int)($it['aprobado'] ?? 1) === 1;
          ?>
          <tr>
            <td><?= $i++ ?></td>
            <td>
              <?= htmlspecialchars($it['insumo']) ?>
              <small class="text-muted"> (<?= htmlspecialchars($it['unidad'] ?? '') ?>)</small>
            </td>
            <td class="text-end"><?= number_format($sol,2) ?></td>
            <td class="text-center">
              <input type="checkbox" name="item[<?= (int)$it['id'] ?>][ok]" <?= $chk?'checked':'' ?>>
            </td>
            <td>
              <input type="number" step="0.01" min="0" class="form-control"
                     name="item[<?= (int)$it['id'] ?>][qty]" value="<?= htmlspecialchars($apb, ENT_QUOTES) ?>">
            </td>
            <td>
              <input type="text" maxlength="255" class="form-control"
                     name="item[<?= (int)$it['id'] ?>][motivo]" value="<?= htmlspecialchars($it['motivo'] ?? '', ENT_QUOTES) ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="d-flex gap-2 justify-content-end mt-3">
      <button type="submit" name="rechazar" class="btn btn-outline-danger"
              onclick="return confirm('¿Rechazar la solicitud completa?');">
        Rechazar solicitud
      </button>
      <button type="submit" name="autorizar" class="btn btn-success">
        Revisar y descontar
      </button>
    </div>
  </form>
</div>
<?php include 'footer.php'; ?>