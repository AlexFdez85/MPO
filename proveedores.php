<?php
// proveedores.php

// 1) Mostrar errores (solo desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// 2) Validar sesión y rol
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente'])) {
    header('Location: dashboard.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

// 3) Procesar creación de nuevo proveedor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_proveedor'])) {
    $nombre    = trim($_POST['nombre']);
    $contacto  = trim($_POST['contacto']);
    $email     = trim($_POST['email']);
    $telefono  = trim($_POST['telefono']);
    $direccion  = trim($_POST['direccion']);
    $entrega_domicilio = isset($_POST['entrega_domicilio']) ? 1 : 0;
    $activo    = isset($_POST['activo']) ? 1 : 0;
    

    if ($nombre === '') {
        $error = "El nombre del proveedor es obligatorio.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO proveedores
      (nombre, contacto, email, telefono, direccion, entrega_domicilio, activo)
    VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $contacto, $email, $telefono, $direccion, $entrega_domicilio, $activo]); 
        header("Location: proveedores.php?ok=1");
        exit;
    }
}

// 4) Listar proveedores
$proveedores = $pdo->query("
    SELECT id, nombre, contacto, email, telefono,
           direccion, entrega_domicilio, activo
    FROM proveedores
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mt-4">
  <h3 class="text-primary mb-3">Proveedores</h3>

  <!-- Mensajes -->
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif(isset($_GET['ok'])): ?>
    <div class="alert alert-success">Proveedor creado correctamente.</div>
  <?php endif; ?>

  <!-- Formulario para crear proveedor -->
  <div class="card mb-4 p-3">
    <h5>Nuevo Proveedor</h5>
    <form method="POST">
      <div class="row g-3">
        <div class="col-md-4">
          <label>Nombre *</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label>Contacto</label>
          <input type="text" name="contacto" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Email</label>
          <input type="email" name="email" class="form-control">
        </div>
        <div class="col-md-2">
          <label>Teléfono</label>
          <input type="text" name="telefono" class="form-control">
        </div>
        
          <div class="col-md-4 form-check mt-3">
    <input class="form-check-input"
           type="checkbox"
           name="entrega_domicilio"
           id="entrega_domicilio"
            <?= isset($_POST['entrega_domicilio']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="entrega_domicilio">
      Proveedor entrega a domicilio
    </label>
  </div>
        
        <div class="col-md-6">
          <label>Dirección</label>
          <input type="text" name="direccion" class="form-control" placeholder="Calle, número, colonia...">
        </div>
        
        <div class="col-md-2 form-check mt-2">
          <input class="form-check-input" type="checkbox" name="activo" id="activo" checked>
          <label class="form-check-label" for="activo">Activo</label>
        </div>
        <div class="col-md-2 text-end">
          <button type="submit" name="crear_proveedor" class="btn btn-primary mt-3">Crear</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Tabla de proveedores -->
  <table class="table table-striped">
    <thead>
      <tr>
        <th>ID</th><th>Nombre</th><th>Contacto</th><th>Email</th>
        <th>Teléfono</th><th>Dirección</th><th>Activo</th><th>Entregan?</th><th>Acciones</th> 
      </tr>
    </thead>
    <tbody>
      <?php foreach($proveedores as $p): ?>
        <tr>
          <td><?= $p['id'] ?></td>
          <td><?= htmlspecialchars($p['nombre']) ?></td>
          <td><?= htmlspecialchars($p['contacto']) ?></td>
          <td><?= htmlspecialchars($p['email']) ?></td>
          <td><?= htmlspecialchars($p['telefono']) ?></td>
          <td><?= htmlspecialchars($p['direccion']) ?></td>

          <td>
            <?= $p['activo'] ? '<span class="badge bg-success">Sí</span>'
                             : '<span class="badge bg-secondary">No</span>' ?>
          </td>
          
<td>
  <?php if ($p['entrega_domicilio']): ?> 
    <span class="badge bg-success">Ir por el</span>
  <?php else: ?>
    <span class="badge bg-secondary">Entregan</span>
  <?php endif; ?>
</td>
          
          <td>
            <a href="proveedores_editar.php?id=<?= $p['id'] ?>"
               class="btn btn-sm btn-outline-primary">
              Editar
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include 'footer.php'; ?>
