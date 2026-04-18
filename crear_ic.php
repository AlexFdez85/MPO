<?php
// crear_ic.php
require_once 'config.php';
if (!in_array($_SESSION['rol'], ['admin','gerente'])) {
  header('Location: dashboard.php');
  exit;
}

// 1) Cargar lista de proveedores activos
$prov = $pdo->query("SELECT id,nombre FROM proveedores WHERE activo=1 ORDER BY nombre")
            ->fetchAll(PDO::FETCH_ASSOC);

// NUEVO: categorías estandarizadas para kits de empaque
$CATEGORIAS = [
  'Envase',
  'Tapa',
  'Etiqueta - Diseño',
  'Etiqueta - Descripción',
  'Etiqueta - Nombre y Lote',
  'Etiqueta - Tiempo de secado',
  'Otro'
];


// 2) Procesar el POST
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar_ic'])) {
  $tipo           = trim($_POST['tipo']);
  $nombre         = trim($_POST['nombre']);
  $stock_inicial  = isset($_POST['stock_inicial']) ? (float)$_POST['stock_inicial'] : 0.0;
  $unidad         = trim($_POST['unidad']) !== '' ? trim($_POST['unidad']) : 'pza';
  $precio_unit    = isset($_POST['precio_unitario']) ? (float)$_POST['precio_unitario'] : 0.0;
  $stock_min      = isset($_POST['stock_minimo']) ? (float)$_POST['stock_minimo'] : 0.0;
  $proveedor_id   = !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null;

  if ($tipo === '' || $nombre === '') {
    $error = "Tipo y nombre son obligatorios.";
  } else {
   // (opcional) traer nombre del proveedor para el campo 'proveedor'
    $provName = null;
    if ($proveedor_id) {
      $st = $pdo->prepare("SELECT nombre FROM proveedores WHERE id=? AND activo=1");
      $st->execute([$proveedor_id]);
      $provName = $st->fetchColumn() ?: null;
    }
    $ins = $pdo->prepare("
      INSERT INTO insumos_comerciales
        (tipo, nombre, stock, unidad, precio_unitario, stock_minimo, proveedor, proveedor_id, activo)
      VALUES (?,?,?,?,?,?,?,?,1)
    ");
    $ins->execute([$tipo, $nombre, $stock_inicial, $unidad, $precio_unit, $stock_min, $provName, $proveedor_id]);
    header('Location: crear_ic.php?ok=1'); exit;
  }
}

include 'header.php';
?>

<div class="container mt-4">
  <?php if(isset($_GET['ok'])): ?>
    <div class="alert alert-success">Insumo comercial creado correctamente.</div>
  <?php endif; ?>

  <div class="card p-4 mb-4">
    <h5>Nuevo Insumo Comercial</h5>
    <form method="POST" class="row g-3">
      <div class="col-md-3">
        <label>Tipo</label>
        <select name="tipo" class="form-select" required>
          <option value="">— Selecciona —</option>
          <?php foreach ($CATEGORIAS as $op): ?>
            <option value="<?=$op?>"><?=$op?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">
          Usa las categorías tal cual para que los kits sean consistentes.
        </small>
      </div>
      <div class="col-md-5">
        <label>Nombre</label>
        <input name="nombre" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label>Stock inicial</label>
        <input type="number" name="stock_inicial" step="0.01" class="form-control" value="0">
      </div>
      <div class="col-md-2">
        <label>Unidad</label>
        <select name="unidad" class="form-select">
          <option value="pza" selected>pza</option>
          <option value="jgo">jgo</option>
          <option value="caja">caja</option>
          <option value="rollo">rollo</option>
        </select>
        <small class="text-muted">
          Recomendado: <b>pza</b>. Si compras en rollo/caja, captura aquí las
          <b>piezas equivalentes</b> (ej. rollo 1000 etiquetas ⇒ stock 1000 pza).
        </small>
      </div>
      <div class="col-md-3">
        <label>Precio unitario</label>
        <input type="number" step="0.0001" name="precio_unitario" class="form-control" value="0">
      </div>
      <div class="col-md-3">
        <label>Stock mínimo</label>
        <input type="number" step="0.01" name="stock_minimo" class="form-control" value="0">
      </div>
      <div class="col-md-4">
        <label>Proveedor</label>
        <select name="proveedor_id" class="form-select">
          <option value="">-- Elige Proveedor --</option>
          <?php foreach($prov as $p): ?>
            <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre'])?></option>
          <?php endforeach; ?>
        </select>
        <small class="text-muted">El nombre del proveedor se guarda automáticamente.</small>
      </div>
      <?php if (!empty($error)): ?>
        <div class="col-12">
          <div class="alert alert-danger py-2 mb-0"><?=htmlspecialchars($error)?></div>
        </div>
      <?php endif; ?>
      <div class="col-12 text-end">
        <button name="guardar_ic" class="btn btn-primary">Guardar Insumo</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
