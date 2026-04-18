<?php
// proveedores_editar.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// 1) Validar sesi¨®n y rol
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente'], true)) {
    header('Location: dashboard.php');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: proveedores.php');
    exit;
}

// 2) Leer datos del proveedor
$stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    header('Location: proveedores.php');
    exit;
}

// 3) Procesar env¨ªo (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre             = trim($_POST['nombre']);
    $contacto           = trim($_POST['contacto']);
    $email              = trim($_POST['email']);
    $telefono           = trim($_POST['telefono']);
    $direccion          = trim($_POST['direccion']);
    $entrega_domicilio  = isset($_POST['entrega_domicilio']) ? 1 : 0;
    $activo             = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '') {
        $error = "El nombre del proveedor es obligatorio.";
    } else {
        $upd = $pdo->prepare("
            UPDATE proveedores
               SET nombre             = ?,
                   contacto           = ?,
                   email              = ?,
                   telefono           = ?,
                   direccion          = ?,
                   entrega_domicilio  = ?,
                   activo             = ?
             WHERE id = ?
        ");
        $upd->execute([
            $nombre,
            $contacto,
            $email,
            $telefono,
            $direccion,
            $entrega_domicilio,
            $activo,
            $id
        ]);
        header("Location: proveedores.php?ok=2");
        exit;
    }
}

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-primary mb-4">Editar Proveedor #<?= $p['id'] ?></h3>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card p-4 mb-4">
    <form method="POST" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Nombre *</label>
        <input type="text"
               name="nombre"
               class="form-control"
               required
               value="<?= htmlspecialchars($p['nombre']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Contacto</label>
        <input type="text"
               name="contacto"
               class="form-control"
               value="<?= htmlspecialchars($p['contacto']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Email</label>
        <input type="email"
               name="email"
               class="form-control"
               value="<?= htmlspecialchars($p['email']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Telefono</label>
        <input type="text"
               name="telefono"
               class="form-control"
               value="<?= htmlspecialchars($p['telefono']) ?>">
      </div>

      <!-- NUEVO: direcci¨®n -->
      <div class="col-md-6">
        <label class="form-label">Direccion</label>
        <input type="text"
               name="direccion"
               class="form-control"
               placeholder="Calle, numero, colonia..."
               value="<?= htmlspecialchars($p['direccion']) ?>">
      </div>

      <!-- NUEVO: forma de entrega -->
      <div class="col-md-4 form-check align-self-center">
        <input class="form-check-input"
               type="checkbox"
               name="entrega_domicilio"
               id="entrega_domicilio"
               <?= $p['entrega_domicilio'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="entrega_domicilio">
          Proveedor entrega en sus instalaciones
        </label>
      </div>

      <div class="col-md-2 form-check align-self-center">
        <input class="form-check-input" type="checkbox"
               name="activo"
               id="activo"
               <?= $p['activo'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="activo">Activo</label>
      </div>

      <div class="col-12 text-end">
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="proveedores.php" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>

