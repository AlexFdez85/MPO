<?php
// crear_mp.php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente'])) {
  header('Location: dashboard.php');
  exit;
}

// 1) Listado de proveedores para el multi©\select
$allProveedores = $pdo
  ->query("SELECT id, nombre FROM proveedores WHERE activo=1 ORDER BY nombre")
  ->fetchAll(PDO::FETCH_ASSOC);

// 2) Procesar POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_mp'])) {
  // 2.a) Insertar MP (ajusta nombres de columna seg¨²n tu estructura)
  $stmt = $pdo->prepare("
    INSERT INTO materias_primas
      (nombre, unidad, tipo, codigo_interno, precio_unitario, existencia, stock_minimo)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([
    $_POST['nombre'],
    $_POST['unidad'],
    $_POST['tipo'],
    $_POST['codigo'],
    floatval($_POST['precio unitario'] ?: 0),
    floatval($_POST['existencia'] ?? 0),
    floatval($_POST['stock_minimo'] ?? 0),
  ]);
  $newMpId = $pdo->lastInsertId();

  // 2.b) Asociar proveedores
  if (!empty($_POST['proveedores']) && is_array($_POST['proveedores'])) {
    $ins = $pdo->prepare("
      INSERT INTO proveedores_mp (proveedor_id, mp_id)
      VALUES (?,?)
      ON DUPLICATE KEY UPDATE mp_id = mp_id
    ");
    foreach ($_POST['proveedores'] as $provId) {
      $ins->execute([ intval($provId), $newMpId ]);
    }
  }

  header("Location: crear_mp.php?ok=1");
  exit;
}
?>
<?php include 'header.php'; ?>
<div class="container mt-4" style="max-width:600px;">
  <h3 class="text-danger mb-3">Registrar Nueva Materia Prima</h3>

  <?php if(isset($_GET['ok'])): ?>
    <div class="alert alert-success">Materia prima registrada correctamente.</div>
  <?php endif; ?>

  <form method="POST">
    <div class="mb-3">
      <label>Nombre</label>
      <input name="nombre" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Unidad de medida</label>
      <select name="unidad" class="form-select" required>
        <option value="">Selecciona</option>
        <option value="g">Gramos</option>

      </select>
    </div>

    <div class="mb-3">
      <label>Tipo</label>
      <select name="tipo" class="form-select" required>
        <option value="">Selecciona</option>
        <option value="resina">Resina</option>
        <option value="pigmento">Pigmento</option>
        <option value="solvente">Solvente</option>
        <option value="aditivo">Cargas</option>
        <option value="aditivo">Aditivo</option>
        <option value="otro">Otro</option>
      </select>
    </div>

    <div class="mb-3">
      <label>Codigo interno</label>
      <input name="codigo" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Precio estimado (opcional)</label>
      <input type="number" name="precio" step="0.000001" class="form-control">
    </div>

    <div class="mb-3">
      <label>Existencia inicial (g)</label>
      <input type="number" name="existencia" step="0.01" class="form-control" value="0" required>
    </div>

    <div class="mb-3">
      <label>Stock minimo (g)</label>
      <input type="number" name="stock_minimo" step="0.01" class="form-control" value="0" required>
    </div>

    <div class="mb-3">
      <label>Proveedores</label>
      <select name="proveedores[]" class="form-select" multiple>
        <?php foreach($allProveedores as $prov): ?>
          <option value="<?= $prov['id'] ?>">
            <?= htmlspecialchars($prov['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <small class="text-muted">
        Manten presionada Ctrl (Cmd en Mac) para seleccionar varios.
      </small>
    </div>

    <button name="guardar_mp" class="btn btn-primary">Guardar Materia Prima</button>
  </form>
</div>
<?php include 'footer.php'; ?>
