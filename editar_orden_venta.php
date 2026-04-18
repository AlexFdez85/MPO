<?php
// editar_orden_venta.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// --- 0) Seguridad básica ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente','produccion'])) {
  header('Location: login.php'); exit;
}
$usuario_id = (int)$_SESSION['user_id'];
$rol        = $_SESSION['rol'];

// --- 1) Parámetro principal ---
$ovId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ovId <= 0) { die('Falta id de Orden de Venta'); }

// --- 2) Helpers ---
function fetchOrden(PDO $pdo, int $ovId): array {
  // En tu BD la columna es 'fecha' (no 'fecha_creacion'), y no existen 'paqueteria' ni 'comentarios'
  $st = $pdo->prepare("SELECT id, cliente_id, estado, fecha AS fecha_creacion, fecha_entrega, incluye_paquete
                         FROM ordenes_venta WHERE id=? LIMIT 1");
  $st->execute([$ovId]);
  $ov = $st->fetch(PDO::FETCH_ASSOC);
  if (!$ov) { die('OV no encontrada'); }
  return $ov;
}

function fetchLineas(PDO $pdo, int $ovId): array {
  $st = $pdo->prepare("
    SELECT lv.id, lv.producto_id, lv.presentacion_id, lv.cantidad,
           COALESCE(SUM(sv.cantidad),0) AS cant_surtida
      FROM lineas_venta lv
 LEFT JOIN surtidos_venta sv ON sv.linea_venta_id = lv.id
     WHERE lv.orden_venta_id = ?
  GROUP BY lv.id
  ORDER BY lv.id ASC
  ");
  $st->execute([$ovId]);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}

function fetchProductosPresentaciones(PDO $pdo): array {
  // Combo (Producto + Presentación)
  $sql = "
    SELECT pp.id AS pp_id, p.id AS producto_id, pr.id AS presentacion_id,
           p.nombre AS producto, pr.nombre AS presentacion
      FROM productos_presentaciones pp
      JOIN productos p      ON p.id = pp.producto_id
      JOIN presentaciones pr ON pr.id = pp.presentacion_id
     WHERE p.es_para_venta = 1
  ORDER BY p.nombre, pr.nombre
  ";
  return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function ppToProdPres(PDO $pdo, int $ppId): array {
  $st = $pdo->prepare("SELECT producto_id, presentacion_id FROM productos_presentaciones WHERE id=?");
  $st->execute([$ppId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) { throw new RuntimeException('Presentación inválida'); }
  return [(int)$r['producto_id'], (int)$r['presentacion_id']];
}

// --- 3) Acciones POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $accion = $_POST['accion'] ?? '';

  if ($accion === 'update_header') {
    $fecha_entrega   = trim($_POST['fecha_entrega'] ?? '');
    // tu tabla tiene 'incluye_paquete' (tinyint/bool), no 'paqueteria'
    $incluye_paquete = isset($_POST['incluye_paquete']) ? 1 : 0;

    $st = $pdo->prepare("UPDATE ordenes_venta
                            SET fecha_entrega = ?, incluye_paquete = ?
                          WHERE id = ? LIMIT 1");
    $st->execute([
      ($fecha_entrega !== '' ? $fecha_entrega : null),
      $incluye_paquete,
      $ovId
    ]);
    header("Location: editar_orden_venta.php?id={$ovId}&ok=hdr"); exit;
  }

  if ($accion === 'add_line') {
    $pp_id   = (int)($_POST['pp_id'] ?? 0);
    $cantidad= (float)($_POST['cantidad'] ?? 0);
    if ($pp_id <= 0 || $cantidad <= 0) { die('Datos de línea inválidos'); }
    [$prodId, $presId] = ppToProdPres($pdo, $pp_id);

    $st = $pdo->prepare("INSERT INTO lineas_venta (orden_venta_id, producto_id, presentacion_id, cantidad)
                         VALUES (?, ?, ?, ?)");
    $st->execute([$ovId, $prodId, $presId, $cantidad]);
    header("Location: editar_orden_venta.php?id={$ovId}&ok=add"); exit;
  }

  if ($accion === 'update_line') {
    $linea_id = (int)($_POST['linea_id'] ?? 0);
    $cantidad = (float)($_POST['cantidad'] ?? 0);
    if ($linea_id <= 0 || $cantidad <= 0) { die('Datos inválidos'); }

    // lee lo ya surtido para la línea
    $st = $pdo->prepare("
      SELECT lv.id, lv.cantidad,
             COALESCE(SUM(sv.cantidad),0) AS cant_surtida
        FROM lineas_venta lv
   LEFT JOIN surtidos_venta sv ON sv.linea_venta_id = lv.id
       WHERE lv.id = ? AND lv.orden_venta_id = ?
    ");
    $st->execute([$linea_id, $ovId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) { die('Línea no encontrada'); }

    $cantSurtida = (float)$r['cant_surtida'];
    if ($cantidad < $cantSurtida) {
      die('No puedes fijar una cantidad menor a lo ya surtido.');
    }

    $up = $pdo->prepare("UPDATE lineas_venta SET cantidad=? WHERE id=? LIMIT 1");
    $up->execute([$cantidad, $linea_id]);
    header("Location: editar_orden_venta.php?id={$ovId}&ok=uline"); exit;
  }

  if ($accion === 'delete_line') {
    $linea_id = (int)($_POST['linea_id'] ?? 0);
    if ($linea_id <= 0) { die('Línea inválida'); }

    // verifica que no tenga surtidos
    $st = $pdo->prepare("SELECT COALESCE(SUM(sv.cantidad),0) FROM surtidos_venta sv WHERE sv.linea_venta_id=?");
    $st->execute([$linea_id]);
    $surtidos = (float)$st->fetchColumn();
    if ($surtidos > 0) { die('No se puede eliminar: la línea ya tiene surtidos.'); }

    $del = $pdo->prepare("DELETE FROM lineas_venta WHERE id=? AND orden_venta_id=? LIMIT 1");
    $del->execute([$linea_id, $ovId]);
    header("Location: editar_orden_venta.php?id={$ovId}&ok=dline"); exit;
  }
}

// --- 4) Datos para render ---
$orden    = fetchOrden($pdo, $ovId);
$lineas   = fetchLineas($pdo, $ovId);
$catalogo = fetchProductosPresentaciones($pdo);

// --- 5) UI ---
include 'header.php';
?>
<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center">
    <h4>Editar Orden de Venta #<?= (int)$orden['id'] ?></h4>
    <div>
      <a class="btn btn-outline-secondary btn-sm" href="ordenes_venta.php">← Volver</a>
    </div>
  </div>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success mt-2">
      Cambios guardados (<?= htmlspecialchars($_GET['ok']) ?>).
    </div>
  <?php endif; ?>

  <!-- Encabezado -->
  <div class="card mb-4">
    <div class="card-header">Encabezado</div>
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="accion" value="update_header">
        <div class="col-md-3">
          <label class="form-label">Fecha de entrega</label>
          <input type="date" name="fecha_entrega" class="form-control"
                 value="<?= !empty($orden['fecha_entrega']) ? date('Y-m-d', strtotime($orden['fecha_entrega'])) : '' ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input type="checkbox" name="incluye_paquete" id="incluye_paquete" class="form-check-input"
                  <?= !empty($orden['incluye_paquete']) ? 'checked' : '' ?>>
            <label for="incluye_paquete" class="form-check-label">Embalado para paquetería</label>
          </div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Comentarios</label>
          <input type="text" name="comentarios" class="form-control" value="">
        </div>
        
        
        <div class="col-12 text-end">
          <button class="btn btn-primary">Guardar encabezado</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Renglones -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Renglones</span>
      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#frmAddLinea">+ Añadir renglón</button>
    </div>
    <div class="card-body">

      <!-- Form agregar línea -->
      <div id="frmAddLinea" class="collapse mb-3">
        <form method="post" class="row g-3">
          <input type="hidden" name="accion" value="add_line">
          <div class="col-md-8">
            <label class="form-label">Producto / Presentación</label>
            <select name="pp_id" class="form-select" required>
              <option value="">Selecciona…</option>
              <?php foreach ($catalogo as $c): ?>
                <option value="<?= (int)$c['pp_id'] ?>">
                  <?= htmlspecialchars($c['producto'].' — '.$c['presentacion']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <input type="number" step="0.01" min="0.01" name="cantidad" class="form-control" required>
          </div>
          <div class="col-md-2 d-flex align-items-end justify-content-end">
            <button class="btn btn-success">Agregar</button>
          </div>
        </form>
      </div>

      <!-- Tabla de líneas -->
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Producto</th>
              <th>Presentación</th>
              <th class="text-end">Cantidad</th>
              <th class="text-end">Surtido</th>
              <th class="text-end">Disponible a editar</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$lineas): ?>
              <tr><td colspan="7" class="text-center text-muted">Sin renglones</td></tr>
            <?php endif; ?>
            <?php foreach ($lineas as $ln): ?>
              <?php
                // traemos nombres legibles
                $p   = $pdo->prepare("SELECT nombre FROM productos WHERE id=?"); $p->execute([(int)$ln['producto_id']]); $prodNom = (string)$p->fetchColumn();
                $pr  = $pdo->prepare("SELECT nombre FROM presentaciones WHERE id=?"); $pr->execute([(int)$ln['presentacion_id']]); $presNom = (string)$pr->fetchColumn();
                $surt = (float)$ln['cant_surtida']; $cant = (float)$ln['cantidad'];
                $editableHasta = max($cant - $surt, 0.0);
              ?>
              <tr>
                <td>#<?= (int)$ln['id'] ?></td>
                <td><?= htmlspecialchars($prodNom) ?></td>
                <td><?= htmlspecialchars($presNom) ?></td>
               <td class="text-end">
              <form method="post" class="d-inline" id="fline<?= (int)$ln['id'] ?>">
                <input type="hidden" name="accion" value="update_line">
                <input type="hidden" name="linea_id" value="<?= (int)$ln['id'] ?>">
                <input
                  type="number"
                  step="0.01"
                  min="<?= $surt ?>"
                  name="cantidad"
                  value="<?= number_format($cant,2,'.','') ?>"
                  class="form-control form-control-sm text-end"
                  style="width:130px; display:inline-block">
              </form>
            </td>
            <td class="text-end"><?= number_format($surt, 2) ?></td>
            <td class="text-end"><?= number_format($editableHasta, 2) ?></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button type="submit" form="fline<?= (int)$ln['id'] ?>" class="btn btn-outline-primary">
                  Guardar
                </button>
                <form method="post" onsubmit="return confirm('¿Eliminar renglón?');" class="d-inline">
                  <input type="hidden" name="accion" value="delete_line">
                  <input type="hidden" name="linea_id" value="<?= (int)$ln['id'] ?>">
                  <button class="btn btn-outline-danger" <?= $surt>0 ? 'disabled' : '' ?>>Eliminar</button>
                </form>
              </div>
            </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="text-muted small">
        * No se puede eliminar un renglón con surtidos.  
        * Si ya hay surtidos, la cantidad mínima permitida es igual a lo ya surtido.
      </div>

    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
