<?php
// clientes.php
require 'config.php';

// 1) Permisos: sólo admin y gerente
if (!in_array($_SESSION['rol'], ['admin','gerente'], true)) {
    header('Location: dashboard.php');
    exit;
}

// ==== Acciones adicionales: promover prospecto a cliente / eliminar prospecto ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'promote_prospect') {
        $pid = (int)($_POST['prospecto_id'] ?? 0);
        if ($pid > 0) {
            $pdo->beginTransaction();
            try {
                $r = $pdo->prepare("SELECT * FROM C_channels_prospectos WHERE id=:id FOR UPDATE");
                $r->execute([':id'=>$pid]);
                $row = $r->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    // Inserta en clientes
                    $stmt = $pdo->prepare("INSERT INTO clientes
                       (nombre, contacto, direccion, datos_envio, activo, es_distribuidor, distribuidor_id)
                       VALUES (:n,:c,:d,'',1,0,NULL)");
                    $stmt->execute([
                        ':n'=>$row['razon_social'],
                        ':c'=>trim(($row['contacto_nombre'] ?? '').' · '.($row['contacto_email'] ?? '').(($row['contacto_tel']??'')?' / '.$row['contacto_tel']:'')),
                        ':d'=>$row['ubicacion'] ?? ''
                    ]);
                    $clienteId = (int)$pdo->lastInsertId();
                    // Contacto (básico)
                    if (!empty($row['contacto_nombre']) || !empty($row['contacto_tel'])) {
                        $pdo->prepare("INSERT INTO contactos (cliente_id, nombre, telefono) VALUES (?,?,?)")
                            ->execute([$clienteId, $row['contacto_nombre'] ?? '', $row['contacto_tel'] ?? '']);
                    }
                    // Relación con el registrante (mayorista/distribuidor)
                    if (!empty($row['distribuidor_user_id'])) {
                        $pdo->prepare("INSERT INTO clientes_mayorista (cliente_id, mayorista_user_id, status, exclusivo, created_at, updated_at)
                                       VALUES (:cid,:uid,'activo',1,NOW(),NOW())")->execute([':cid'=>$clienteId, ':uid'=>(int)$row['distribuidor_user_id']]);
                    }
                    // borra el prospecto de la cola
                    $pdo->prepare("DELETE FROM C_channels_prospectos WHERE id=:id")->execute([':id'=>$pid]);
                }
                $pdo->commit();
            } catch (Exception $e) { $pdo->rollBack(); throw $e; }
        }
    } elseif ($action === 'delete_prospect') {
        $pid = (int)($_POST['prospecto_id'] ?? 0);
        if ($pid>0) { $pdo->prepare("DELETE FROM C_channels_prospectos WHERE id=:id")->execute([':id'=>$pid]); }
    }
}


// 2) Procesar POST: crear o actualizar cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id              = intval($_POST['id'] ?? 0);
    $nombre          = trim($_POST['nombre']);
    $contacto        = trim($_POST['contacto']);
    $direccion       = trim($_POST['direccion']);
    $datos_envio     = trim($_POST['datos_envio']);
    $activo          = isset($_POST['activo']) ? 1 : 0;
    $esDistribuidor  = isset($_POST['es_distribuidor']) ? 1 : 0;
    $distribuidor_id = !empty($_POST['distribuidor_id'])
                      ? intval($_POST['distribuidor_id'])
                      : null;

    if ($id > 0) {
        // UPDATE existente
        $stmt = $pdo->prepare("
          UPDATE clientes SET
            nombre          = ?,
            contacto        = ?,
            direccion       = ?,
            datos_envio     = ?,
            activo          = ?,
            es_distribuidor = ?,
            distribuidor_id = ?
          WHERE id = ?
        ");
        $stmt->execute([
          $nombre,
          $contacto,
          $direccion,
          $datos_envio,
          $activo,
          $esDistribuidor,
          $distribuidor_id,
          $id
        ]);
    } else {
        // INSERT nuevo
        $stmt = $pdo->prepare("
          INSERT INTO clientes
            (nombre, contacto, direccion, datos_envio, activo, es_distribuidor, distribuidor_id)
          VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
          $nombre,
          $contacto,
          $direccion,
          $datos_envio,
          $activo,
          $esDistribuidor,
          $distribuidor_id
        ]);
    }

// vista (clientes | prospectos)
$view = $_GET['view'] ?? 'clientes';


    header('Location: clientes.php');
    exit;
}

// 3) Si vienen con ?edit=ID, cargo datos para el formulario
$editData = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$eid]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4) Datos para el formulario
$dist = $pdo
  ->query("SELECT id,nombre FROM clientes WHERE es_distribuidor=1 AND activo=1 ORDER BY nombre")
  ->fetchAll(PDO::FETCH_ASSOC);

// 5) Listado de todos los clientes
$clients = $pdo
  ->query("
    SELECT c.*,
           IFNULL(d.nombre,'—') AS distribuidor_nombre
      FROM clientes c
      LEFT JOIN clientes d ON d.id = c.distribuidor_id
     ORDER BY c.nombre
  ")
  ->fetchAll(PDO::FETCH_ASSOC);

// 5B) Prospectos (para vista 'prospectos'), con registrante
$prospects = $pdo->query("
    SELECT p.*, u.nombre AS registrante_nombre, u.email AS registrante_email
      FROM C_channels_prospectos p
      LEFT JOIN C_canal_usuarios u ON u.id = p.distribuidor_user_id
     ORDER BY p.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mt-4">
  <h3>Clientes</h3>

  <?php if (in_array($_SESSION['rol'], ['admin','gerente'], true)): ?>
    <!-- Botón para desplegar el formulario -->
    <button class="btn btn-sm btn-primary mb-3"
            data-bs-toggle="collapse"
            data-bs-target="#formCliente">
      <?= $editData ? '✎ Editar Cliente' : '+ Nuevo Cliente' ?>
    </button>
  <?php endif; ?>


  <!-- Tabs de vista -->
  <div class="mb-3">
    <a class="btn btn<?= ($view==='clientes'?'':'-outline') ?>-primary btn-sm" href="clientes.php?view=clientes">Clientes</a>
    <a class="btn btn<?= ($view==='prospectos'?'':'-outline') ?>-primary btn-sm" href="clientes.php?view=prospectos">Prospectos</a>
  </div>
 
  <?php if ($view==='prospectos'): ?>
    <div class="table-responsive mb-4">
      <table class="table table-striped align-middle">
        <thead><tr>
          <th>#</th><th>Fecha</th><th>Razón social</th><th>Contacto</th><th>Ubicación</th><th>Registrado por</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach($prospects as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= htmlspecialchars($p['created_at']) ?></td>
              <td><?= htmlspecialchars($p['razon_social']) ?></td>
              <td><?= htmlspecialchars($p['contacto_nombre'].' · '.$p['contacto_email'].' / '.($p['contacto_tel']??'')) ?></td>
              <td><?= htmlspecialchars($p['ubicacion'] ?? '') ?></td>
              <td>
                <div><strong><?= htmlspecialchars($p['registrante_nombre'] ?? ('#'.$p['distribuidor_user_id'])) ?></strong></div>
                <div class="text-muted small"><?= htmlspecialchars($p['registrante_email'] ?? '') ?></div>
              </td>
              <td><span class="badge bg-secondary"><?= strtoupper($p['status']) ?></span></td>
              <td>
                <form method="post" class="d-flex gap-2">
                  <input type="hidden" name="prospecto_id" value="<?= (int)$p['id'] ?>">
                  <button name="action" value="promote_prospect" class="btn btn-success btn-sm" onclick="return confirm('Promover a cliente y borrar de prospectos?');">Promover a Cliente</button>
                  <button name="action" value="delete_prospect" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar prospecto definitivamente?');">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<!--   FORMULARIO COLAPSIBLE    -->

  <div class="collapse <?= $editData ? 'show' : '' ?>" id="formCliente">
    <div class="card card-body mb-4">
      <form method="POST">
        <!-- Si editData, incluimos el id para UPDATE -->
        <?php if ($editData): ?>
          <input type="hidden" name="id" value="<?= $editData['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <input name="nombre"
                   class="form-control"
                   required
                   value="<?= htmlspecialchars($editData['nombre'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Contacto</label>
            <input name="contacto"
                   class="form-control"
                   value="<?= htmlspecialchars($editData['contacto'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Dirección</label>
            <textarea name="direccion" class="form-control"><?= htmlspecialchars($editData['direccion'] ?? '') ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Datos de Envío</label>
            <textarea name="datos_envio" class="form-control"><?= htmlspecialchars($editData['datos_envio'] ?? '') ?></textarea>
          </div>
          <div class="col-md-3 form-check form-switch">
            <input name="activo"
                   class="form-check-input"
                   type="checkbox"
                   id="activo"
                   <?= ( ($editData? $editData['activo']:1) ? 'checked':'') ?>>
            <label class="form-check-label" for="activo">Activo</label>
          </div>
          <div class="col-md-3 form-check form-switch">
            <input name="es_distribuidor"
                   class="form-check-input"
                   type="checkbox"
                   id="es_distribuidor"
                   <?= ( $editData && $editData['es_distribuidor'] ? 'checked':'') ?>>
            <label class="form-check-label" for="es_distribuidor">Es Distribuidor</label>
          </div>
          <div class="col-md-6">
            <label class="form-label">Distribuidor Padre</label>
            <select name="distribuidor_id" class="form-select">
              <option value="">— Ninguno —</option>
              <?php foreach ($dist as $d): ?>
                <option value="<?= $d['id'] ?>"
                  <?= (isset($editData['distribuidor_id']) && $editData['distribuidor_id']==$d['id'])
                     ? 'selected':'' ?>>
                  <?= htmlspecialchars($d['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mt-3">
          <button class="btn btn-primary"><?= $editData ? 'Guardar Cambios' : 'Crear Cliente' ?></button>
          <?php if ($editData): ?>
            <a href="clientes.php" class="btn btn-outline-secondary">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabla de clientes -->
  <table class="table table-striped align-middle">
    <thead>
      <tr>
        <th>#</th>
        <th>Nombre</th>
        <th>Contacto</th>
        <th>Activo</th>
        <th>Distribuidor Padre</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= $c['id'] ?></td>
        <td><?= htmlspecialchars($c['nombre']) ?></td>
        <td><?= htmlspecialchars($c['contacto']) ?></td>
        <td><?= $c['activo'] ? 'Sí' : 'No' ?></td>
        <td><?= htmlspecialchars($c['distribuidor_nombre']) ?></td>
        <td>
          <?php if (in_array($_SESSION['rol'], ['admin','gerente'], true)): ?>
            <a href="clientes.php?edit=<?= $c['id'] ?>"
               class="btn btn-sm btn-outline-primary">
              ✎ Editar
            </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php include 'footer.php'; ?>

