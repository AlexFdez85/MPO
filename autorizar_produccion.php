<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// CSRF simple
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];


// === Mini-endpoint: contenido del modal (Fórmula + Faltantes) ===
if (isset($_GET['modal']) && $_GET['modal']==='formula' && isset($_GET['op'])) {
    $opId = (int)$_GET['op'];
    $st = $pdo->prepare("
      SELECT op.id, op.ficha_id, op.cantidad_a_producir AS cantidad, f.unidad_produccion AS unidad,
             p.id AS producto_id, p.nombre AS producto, COALESCE(p.densidad_kg_por_l,1) AS densidad
        FROM ordenes_produccion op
        JOIN fichas_produccion f ON f.id = op.ficha_id
        JOIN productos p         ON p.id = f.producto_id
       WHERE op.id = ?
    "); $st->execute([$opId]); $op = $st->fetch(PDO::FETCH_ASSOC);
    if (!$op) { http_response_code(404); echo '<div class="p-3">OP no encontrada.</div>'; exit; }
    $gramos = ($op['unidad']==='kg') ? ((float)$op['cantidad']*1000.0) : (float)$op['cantidad'];
    if (!function_exists('getPresentacionIdGramos')) { require_once 'config.php'; }
    $presG = getPresentacionIdGramos($pdo);
    $q = $pdo->prepare("
      SELECT
        CASE WHEN fmp.mp_id<=100000 THEN mp.id ELSE (fmp.mp_id-100000) END AS id_norm,
        COALESCE(mp.nombre, prod.nombre) AS nombre,
        fmp.porcentaje_o_gramos          AS gramos_formula,
        COALESCE(mp.existencia, prod_ex.stock_total, 0) AS stock_actual
      FROM ficha_mp fmp
      LEFT JOIN materias_primas mp ON mp.id = fmp.mp_id
      LEFT JOIN productos prod     ON prod.id = (fmp.mp_id - 100000)
      LEFT JOIN (
        SELECT producto_id, SUM(cantidad) AS stock_total
          FROM productos_terminados
         WHERE presentacion_id = :presG
         GROUP BY producto_id
      ) prod_ex ON prod_ex.producto_id = prod.id
      WHERE fmp.ficha_id = :ficha
    "); $q->execute([':presG'=>$presG, ':ficha'=>$op['ficha_id']]);
    $ings = $q->fetchAll(PDO::FETCH_ASSOC);
    $totalBase = array_sum(array_map(fn($r)=> (float)$r['gramos_formula'], $ings));
    ob_start(); ?>
    <div class="p-2">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div><strong>OP #<?= (int)$op['id'] ?></strong> — <?= htmlspecialchars($op['producto']) ?></div>
        <div class="text-muted small">Lote solicitado: <?= number_format($gramos,2) ?> g</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Insumo</th><th class="text-end">%</th><th class="text-end">Necesario (g)</th><th class="text-end">Stock (g)</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($ings as $r):
            $pct = $totalBase>0 ? ((float)$r['gramos_formula']/$totalBase)*100.0 : 0.0;
            $nec = ($pct/100.0)*$gramos;
            $stk = (float)$r['stock_actual'];
            $estado = ($stk < $nec) ? 'text-danger' : (($stk < $nec*1.1)? 'text-warning' : 'text-success');
          ?>
            <tr>
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td class="text-end"><?= number_format($pct,2) ?></td>
              <td class="text-end"><?= number_format($nec,2) ?></td>
              <td class="text-end"><?= number_format($stk,2) ?></td>
              <td class="text-end"><span class="<?= $estado ?>">●</span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php echo ob_get_clean(); exit;
}

// === Mini-endpoint: modal de EDICIÓN de OP ===
if (isset($_GET['modal']) && $_GET['modal']==='edit' && isset($_GET['op'])) {
    $opId = (int)$_GET['op'];
    $st = $pdo->prepare("
      SELECT op.id, op.ficha_id, op.cantidad_a_producir AS cantidad, op.fecha,
             f.unidad_produccion AS unidad, p.id AS producto_id, p.nombre AS producto
        FROM ordenes_produccion op
        JOIN fichas_produccion f ON f.id = op.ficha_id
        JOIN productos p         ON p.id = f.producto_id
       WHERE op.id = ? AND op.estado_autorizacion = 'pendiente'
    ");
    $st->execute([$opId]);
    $op = $st->fetch(PDO::FETCH_ASSOC);
    if (!$op) { http_response_code(404); echo '<div class="p-3">OP no encontrada o no editable.</div>'; exit; }
    // Fichas disponibles para el mismo producto (cambian unidad si aplica)
    $fs = $pdo->prepare("SELECT id, unidad_produccion FROM fichas_produccion WHERE producto_id = ? ORDER BY id DESC");
    $fs->execute([(int)$op['producto_id']]);
    $fichas = $fs->fetchAll(PDO::FETCH_ASSOC);
    ob_start(); ?>
      <form id="formEditarOP" method="POST" class="p-2">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="editar_op">
        <input type="hidden" name="orden_id" value="<?= (int)$op['id'] ?>">
        <div class="mb-3">
          <div class="small text-muted">Producto</div>
          <div><strong><?= htmlspecialchars($op['producto']) ?></strong></div>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Ficha</label>
            <select name="ficha_id" class="form-select form-select-sm" id="selFicha">
              <?php foreach ($fichas as $f): ?>
                <option value="<?= (int)$f['id'] ?>" <?= $op['ficha_id']==$f['id']?'selected':'' ?>>
                  #<?= (int)$f['id'] ?> — <?= htmlspecialchars($f['unidad_produccion']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">La unidad la define la ficha.</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Unidad</label>
            <input class="form-control form-control-sm" id="unidadFicha" value="<?= htmlspecialchars($op['unidad']) ?>" readonly>
          </div>
          <div class="col-md-4">
            <label class="form-label">Cantidad a producir</label>
            <input type="number" step="0.01" min="0" name="cantidad" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$op['cantidad']) ?>" required>
          </div>
        </div>
        <div class="mt-3 text-end">
          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary btn-sm px-3"><i class="bi bi-save"></i> Guardar</button>
        </div>
      </form>
      <script>
        // Al cambiar la ficha, refrescamos la unidad mostrada
        (function(){
          const sel = document.getElementById('selFicha');
          const unidad = document.getElementById('unidadFicha');
          if (!sel) return;
          sel.addEventListener('change', function(){
            // Consultar la unidad de la ficha seleccionada
            fetch('autorizar_produccion.php?ajax=unidad_ficha&ficha=' + encodeURIComponent(this.value))
              .then(r => r.json()).then(j => { unidad.value = j.unidad || unidad.value; })
              .catch(()=>{});
          });
        })();
      </script>
    <?php echo ob_get_clean(); exit;
}

// === AJAX: obtener unidad por ficha ===
if (isset($_GET['ajax']) && $_GET['ajax']==='unidad_ficha' && isset($_GET['ficha'])) {
    $fid = (int)$_GET['ficha'];
    $s = $pdo->prepare("SELECT unidad_produccion FROM fichas_produccion WHERE id = ?");
    $s->execute([$fid]);
    $u = $s->fetchColumn();
    header('Content-Type: application/json');
    echo json_encode(['unidad' => $u ?: null]);
    exit;
}


if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Autorizar o rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['orden_id'];
    $accion = $_POST['accion']; // 'autorizar' o 'rechazar'

    $nuevo_estado = ($accion === 'autorizar') ? 'autorizada' : 'rechazada';
    $stmt = $pdo->prepare("UPDATE ordenes_produccion SET estado_autorizacion = ?, autorizado_por = ? WHERE id = ?");
    $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $id]);

    header("Location: autorizar_produccion.php");
    exit;
    
    // Validar CSRF
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403); exit('CSRF inválido');
    }
    // Acciones
    if (isset($_POST['accion'])) {
        $id = (int)$_POST['orden_id'];
        $accion = $_POST['accion']; // 'autorizar' | 'rechazar'
        $nuevo_estado = ($accion === 'autorizar') ? 'autorizada' : 'rechazada';
        $stmt = $pdo->prepare("UPDATE ordenes_produccion SET estado_autorizacion = ?, autorizado_por = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $id]);
        header("Location: autorizar_produccion.php"); exit;
    }
    if (isset($_POST['action']) && $_POST['action']==='editar_op') {
        $id       = (int)$_POST['orden_id'];
        $ficha_id = (int)$_POST['ficha_id'];
        $cantidad = (float)$_POST['cantidad'];
        // Solo permitir edición si sigue pendiente
        $val = $pdo->prepare("SELECT estado_autorizacion FROM ordenes_produccion WHERE id=?");
        $val->execute([$id]);
        if ($val->fetchColumn() !== 'pendiente') { header("Location: autorizar_produccion.php"); exit; }
        $upd = $pdo->prepare("UPDATE ordenes_produccion SET ficha_id=?, cantidad_a_producir=? WHERE id=?");
        $upd->execute([$ficha_id, $cantidad, $id]);
        header("Location: autorizar_produccion.php"); exit;
    }
    
}

// Cargar órdenes pendientes
$ordenes = $pdo->query("
    SELECT op.id, p.nombre AS producto, op.cantidad_a_producir, f.unidad_produccion, u.nombre AS solicitante, op.fecha
    FROM ordenes_produccion op
    JOIN fichas_produccion f ON op.ficha_id = f.id
    JOIN productos p ON f.producto_id = p.id
    JOIN usuarios u ON op.usuario_creador = u.id
    WHERE op.estado_autorizacion = 'pendiente'
    ORDER BY op.fecha DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>
<div class="container mt-5">
  <h3 class="text-danger mb-4">Autorizar Órdenes de Producción</h3>

  <?php if (count($ordenes) === 0): ?>
    <div class="alert alert-info">No hay órdenes pendientes de autorización.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Producto</th>
          <th>Cantidad</th>
          <th>Unidad</th>
          <th>Solicitado por</th>
          <th>Fecha</th>
          <th style="width:320px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ordenes as $o): ?>
          <tr>
            <td>
              #<?= $o['id'] ?>
              <button type="button"
                      class="btn btn-link btn-sm p-0 ms-1 align-baseline js-ver-formula"
                      data-op="<?= (int)$o['id'] ?>">
                (--OP--)
              </button>
            </td>
            <td><?= $o['producto'] ?></td>
            <td><?= $o['cantidad_a_producir'] ?></td>
            <td><?= $o['unidad_produccion'] ?></td>
            <td><?= $o['solicitante'] ?></td>
            <td><?= $o['fecha'] ?? '—' ?></td>
            <td>
              <!-- Editar -->
              <button type="button"
                      class="btn btn-outline-primary btn-sm px-2 me-2 js-editar-op"
                      data-op="<?= (int)$o['id'] ?>">
                <i class="bi bi-pencil-square"></i> Editar
              </button>
              <!-- Autorizar/Rechazar -->
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                <input type="hidden" name="orden_id" value="<?= $o['id'] ?>">
                <button name="accion" value="autorizar" class="btn btn-success btn-sm px-3">Autorizar</button>
                <button name="accion" value="rechazar" class="btn btn-danger btn-sm px-3 ms-1">Rechazar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Modal reutilizable -->

<div class="modal fade" id="modalFormula" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Fórmula y faltantes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalFormulaBody">
        <div class="text-muted">Cargando…</div>
      </div>
    </div>
  </div>
</div>


<!-- Modal edición -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Editar Orden de Producción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalEditBody">
        <div class="text-muted">Cargando…</div>
      </div>
    </div>
  </div>
  </div>

<?php include 'footer.php'; ?>
<script>
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-ver-formula');
    if (!btn) return;
    const op = btn.getAttribute('data-op');
    const body = document.getElementById('modalFormulaBody');
    body.innerHTML = '<div class="text-muted">Cargando…</div>';
    const modal = new bootstrap.Modal(document.getElementById('modalFormula'));
    modal.show();
    fetch('autorizar_produccion.php?modal=formula&op=' + encodeURIComponent(op))
      .then(r => r.text())
      .then(html => body.innerHTML = html)
      .catch(() => body.innerHTML = '<div class="text-danger">Error al cargar.</div>');
  }, false);
  
  // Abrir modal de edición
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-editar-op');
    if (!btn) return;
    const op = btn.getAttribute('data-op');
    const body = document.getElementById('modalEditBody');
    body.innerHTML = '<div class="text-muted">Cargando…</div>';
    const modal = new bootstrap.Modal(document.getElementById('modalEdit'));
    modal.show();
    fetch('autorizar_produccion.php?modal=edit&op=' + encodeURIComponent(op))
      .then(r => r.text())
      .then(html => body.innerHTML = html)
      .catch(() => body.innerHTML = '<div class="text-danger">Error al cargar.</div>');
  }, false);
  
</script>
