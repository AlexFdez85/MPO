<?php
// ficha_editar.php
session_start();
require_once __DIR__ . '/config.php';

// --- Permisos básicos (ajústalo a tu modelo: admin/director) ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'] ?? $_SESSION['role'] ?? '', ['admin','gerente'], true)) {
  header('Location: auth/login.php'); exit;
}
$uid = (int)$_SESSION['user_id'];

// --- Parámetros ---
$ficha_id    = isset($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : 0;
$producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

// --- Cargar catálogo de materias primas ---
$mp = $pdo->query("SELECT id, nombre FROM materias_primas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// --- Cargar producto y ficha si aplica ---
$producto = null; $ficha = null; $lineas = [];
if ($ficha_id > 0) {
  $stmt = $pdo->prepare("SELECT fp.*, p.nombre AS producto_nombre
                         FROM fichas_produccion fp
                         JOIN productos p ON p.id = fp.producto_id
                         WHERE fp.id = ?");
  $stmt->execute([$ficha_id]);
  $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($ficha) {
    $producto_id = (int)$ficha['producto_id'];
    $producto = ['id'=>$producto_id, 'nombre'=>$ficha['producto_nombre']];
    $stmt = $pdo->prepare("SELECT fm.id, fm.mp_id, mp.nombre AS materia, fm.porcentaje_o_gramos AS valor
                           FROM ficha_mp fm
                           JOIN materias_primas mp ON mp.id = fm.mp_id
                           WHERE fm.ficha_id = ?
                           ORDER BY fm.id");
    $stmt->execute([$ficha_id]);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}
if (!$producto && $producto_id > 0) {
  $stmt = $pdo->prepare("SELECT id, nombre FROM productos WHERE id = ?");
  $stmt->execute([$producto_id]);
  $producto = $stmt->fetch(PDO::FETCH_ASSOC);
  // buscar si existe alguna ficha previa
  $stmt = $pdo->prepare("SELECT id, lote_minimo, unidad_produccion, instrucciones
                         FROM fichas_produccion WHERE producto_id = ?
                         ORDER BY id DESC LIMIT 1");
  $stmt->execute([$producto_id]);
  $ficha = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  if ($ficha) {
    $ficha_id = (int)$ficha['id'];
    $stmt = $pdo->prepare("SELECT fm.id, fm.mp_id, mp.nombre AS materia, fm.porcentaje_o_gramos AS valor
                           FROM ficha_mp fm
                           JOIN materias_primas mp ON mp.id = fm.mp_id
                           WHERE fm.ficha_id = ?
                           ORDER BY fm.id");
    $stmt->execute([$ficha_id]);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

if (!$producto) { echo "Producto no encontrado."; exit; }

// --- Guardado ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $lote_minimo = (float)($_POST['lote_minimo'] ?? 0);
  $unidad      = trim($_POST['unidad_produccion'] ?? 'g'); // g | kg | %
  $instr       = trim($_POST['instrucciones'] ?? '');

  $mp_ids = $_POST['mp_id'] ?? [];
  $vals   = $_POST['valor'] ?? [];

  try {
    $pdo->beginTransaction();

    if ($ficha_id > 0) {
      $up = $pdo->prepare("UPDATE fichas_produccion
                           SET lote_minimo = ?, unidad_produccion = ?, instrucciones = ?
                           WHERE id = ?");
      $up->execute([$lote_minimo, $unidad, $instr, $ficha_id]);
      // limpiar líneas y reinsertar
      $pdo->prepare("DELETE FROM ficha_mp WHERE ficha_id = ?")->execute([$ficha_id]);
    } else {
      $ins = $pdo->prepare("INSERT INTO fichas_produccion (producto_id, lote_minimo, unidad_produccion, instrucciones, creado_por)
                            VALUES (?,?,?,?,?)");
      $ins->execute([$producto['id'], $lote_minimo, $unidad, $instr, $uid]);
      $ficha_id = (int)$pdo->lastInsertId();
    }

    $insL = $pdo->prepare("INSERT INTO ficha_mp (ficha_id, mp_id, porcentaje_o_gramos)
                           VALUES (?,?,?)");
    $n = count($mp_ids);
    for ($i=0; $i<$n; $i++) {
      $mpId = (int)$mp_ids[$i];
      $val  = (float)($vals[$i] ?? 0);
      if ($mpId > 0 && $val > 0) {
        $insL->execute([$ficha_id, $mpId, $val]);
      }
    }

    $pdo->commit();
    header('Location: productos.php?ok=receta');
    exit;
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $err = $e->getMessage();
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $ficha_id ? 'Editar' : 'Crear' ?> receta — <?=h($producto['nombre'])?></title>
  <link rel="stylesheet" href="assets/bootstrap.min.css">
  <style>
    .table-sm td input, .table-sm td select { width: 100%; }
    .sticky-actions { position: sticky; bottom: 0; background:#fff; padding: 12px 0; }
  </style>
</head>
<body class="container py-4">
  <h3 class="mb-3"><?= $ficha_id ? 'Editar' : 'Crear' ?> receta</h3>
  <p class="text-muted mb-1"><strong>Producto:</strong> <?=h($producto['nombre'])?></p>

  <?php if (!empty($err)): ?>
    <div class="alert alert-danger"><?=$err?></div>
  <?php endif; ?>

  <form method="post">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">Lote mínimo</label>
        <input type="number" step="0.01" name="lote_minimo" class="form-control"
               value="<?=h($ficha['lote_minimo'] ?? '')?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Unidad de producción</label>
        <select name="unidad_produccion" class="form-select">
          <?php
            $uSel = strtolower($ficha['unidad_produccion'] ?? 'g');
            foreach (['g'=>'g (gramos)','kg'=>'kg (kilogramos)','%'=>'% (porcentaje)'] as $k=>$lbl) {
              $sel = $uSel===$k ? 'selected' : '';
              echo "<option value=\"$k\" $sel>$lbl</option>";
            }
          ?>
        </select>
      </div>
      <div class="col-md-12">
        <label class="form-label">Instrucciones</label>
        <textarea name="instrucciones" class="form-control" rows="3"><?=h($ficha['instrucciones'] ?? '')?></textarea>
      </div>
    </div>

    <hr>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Materias primas</h5>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow()">+ Añadir fila</button>
    </div>

    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th style="width:60%">Materia prima</th>
          <th style="width:30%">Valor</th>
          <th style="width:10%"></th>
        </tr>
      </thead>
      <tbody id="rows">
        <?php
          if (empty($lineas)) {
            $lineas = [['mp_id'=>0,'valor'=>'']];
          }
          foreach ($lineas as $l):
        ?>
        <tr>
          <td>
            <select name="mp_id[]" class="form-select" required>
              <option value="">— Selecciona MP —</option>
              <?php foreach ($mp as $m):
                $sel = ((int)$l['mp_id'] === (int)$m['id']) ? 'selected' : ''; ?>
                <option value="<?=$m['id']?>" <?=$sel?>><?=h($m['nombre'])?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <input type="number" step="0.01" name="valor[]" class="form-control"
                   value="<?=h($l['valor'] ?? '')?>" required>
          </td>
          <td class="text-end">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)">Quitar</button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="sticky-actions d-flex gap-2">
      <a href="productos.php" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">Guardar receta</button>
    </div>
  </form>

  <template id="row-tpl">
    <tr>
      <td>
        <select name="mp_id[]" class="form-select" required>
          <option value="">— Selecciona MP —</option>
          <?php foreach ($mp as $m): ?>
            <option value="<?=$m['id']?>"><?=h($m['nombre'])?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input type="number" step="0.01" name="valor[]" class="form-control" required></td>
      <td class="text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="delRow(this)">Quitar</button>
      </td>
    </tr>
  </template>

  <script>
    function addRow(){
      const tpl = document.getElementById('row-tpl').content.cloneNode(true);
      document.getElementById('rows').appendChild(tpl);
    }
    function delRow(btn){
      const tr = btn.closest('tr');
      const tbody = document.getElementById('rows');
      if (tbody.children.length > 1) tr.remove();
    }
  </script>
</body>
</html>
