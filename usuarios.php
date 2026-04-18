<?php
require_once 'config.php';

// Proteger acceso solo para admin
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// CSRF simple
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];


// Crear nuevo usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    $pass = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $ver_precios = isset($_POST['ver_precios']) ? 1 : 0;
    $ver_formulas = isset($_POST['ver_formulas']) ? 1 : 0;
    $ver_reportes = isset($_POST['ver_reportes']) ? 1 : 0;
    $nomina_diaria_mxn     = isset($_POST['nomina_diaria_mxn']) ? (float)$_POST['nomina_diaria_mxn'] : null;
    $jornada_horas         = isset($_POST['jornada_horas']) ? (float)$_POST['jornada_horas'] : 8.00;
    $tipo_usuario          = $_POST['tipo_usuario'] ?? 'operativo';
    $incluye_indirectos    = isset($_POST['incluye_en_indirectos']) ? 1 : 0;

   // INSERT único (corregido: antes había doble inserción)
    $stmt = $pdo->prepare("INSERT INTO usuarios
        (nombre, correo, contrasena, rol, ver_precios, ver_formulas, ver_reportes,
         nomina_diaria_mxn, jornada_horas, tipo_usuario, incluye_en_indirectos, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([
        $nombre, $correo, $pass, $rol, $ver_precios, $ver_formulas, $ver_reportes,
        $nomina_diaria_mxn, $jornada_horas, $tipo_usuario, $incluye_indirectos
    ]);    header("Location: usuarios.php");
    exit;
}

// Acciones de administración por fila (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('CSRF inválido');
    }
    $uid = (int)($_POST['usuario_id'] ?? 0);
    switch ($_POST['action']) {
        case 'toggle_activo':
            $pdo->prepare("UPDATE usuarios SET activo = IF(activo=1,0,1) WHERE id = ?")->execute([$uid]);
            break;
        case 'reset_pass':
            $nueva = trim($_POST['nueva_contrasena'] ?? '');
            if (strlen($nueva) < 8) { $_SESSION['flash_error'] = 'La contraseña debe tener al menos 8 caracteres.'; break; }
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?")->execute([$hash, $uid]);
            break;
            
        case 'edit_user':
            // Normalizar flags
            $ver_precios      = isset($_POST['ver_precios']) ? 1 : 0;
            $ver_formulas     = isset($_POST['ver_formulas']) ? 1 : 0;
            $ver_reportes     = isset($_POST['ver_reportes']) ? 1 : 0;
            $incluye_indirect = isset($_POST['incluye_en_indirectos']) ? 1 : 0;
            // Casts seguros
            $nombre           = trim($_POST['nombre'] ?? '');
            $correo           = trim($_POST['correo'] ?? '');
            $rol              = trim($_POST['rol'] ?? 'operativo');
            $nomina           = ($_POST['nomina_diaria_mxn'] ?? '') === '' ? null : (float)$_POST['nomina_diaria_mxn'];
            $jornada          = ($_POST['jornada_horas'] ?? '') === '' ? 8.0  : (float)$_POST['jornada_horas'];
            $tipo_usuario     = $_POST['tipo_usuario'] ?? 'operativo';
            $stmt = $pdo->prepare("
                UPDATE usuarios
                   SET nombre=?, correo=?, rol=?, ver_precios=?, ver_formulas=?, ver_reportes=?,
                       nomina_diaria_mxn=?, jornada_horas=?, tipo_usuario=?, incluye_en_indirectos=?
                 WHERE id=?");
            $stmt->execute([
                $nombre, $correo, $rol, $ver_precios, $ver_formulas, $ver_reportes,
                $nomina, $jornada, $tipo_usuario, $incluye_indirect, $uid
            ]);
            break;
            
        case 'delete_user':
            // Hard delete; si hay FK, puedes cambiarlo a soft delete con deleted_at
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$uid]);
            break;
    }
    header("Location: usuarios.php");
    exit;
}


// Obtener lista de usuarios
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY activo DESC, nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>
<div class="container mt-4">
    <h2>Gestión de Usuarios</h2>

    <form method="POST" class="card p-3 mb-4">
        <h5>Crear nuevo usuario</h5>
        <div class="row">
            <div class="col-md-4">
                <input name="nombre" class="form-control" placeholder="Nombre completo" required>
            </div>
            <div class="col-md-4">
                <input type="email" name="correo" class="form-control" placeholder="Correo" required>
            </div>
            <div class="col-md-2">
                <input type="password" name="contrasena" class="form-control" placeholder="Contraseña" required>
            </div>
            <div class="row g-2">
  <div class="col-md-3">
    <label class="form-label">Nómina diaria (MXN)</label>
    <input type="number" step="0.01" name="nomina_diaria_mxn" class="form-control" value="<?= htmlspecialchars($usuario['nomina_diaria_mxn'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Jornada (horas)</label>
    <input type="number" step="0.25" name="jornada_horas" class="form-control" value="<?= htmlspecialchars($usuario['jornada_horas'] ?? '8.00') ?>">
  </div>
  <div class="col-md-3">
    <label class="form-label">Tipo de usuario</label>
    <select name="tipo_usuario" class="form-select">
      <option value="operativo"     <?= (isset($usuario['tipo_usuario']) && $usuario['tipo_usuario']=='operativo')?'selected':'' ?>>Operativo</option>
      <option value="administrativo"<?= (isset($usuario['tipo_usuario']) && $usuario['tipo_usuario']=='administrativo')?'selected':'' ?>>Administrativo</option>
    </select>
  </div>
  <div class="col-md-3 form-check" style="margin-top: 32px;">
    <input type="checkbox" class="form-check-input" id="incluye_ind" name="incluye_en_indirectos" value="1"
           <?= (!isset($usuario) || !empty($usuario['incluye_en_indirectos']))?'checked':'' ?>>
    <label class="form-check-label" for="incluye_ind">Incluir en indirectos</label>
  </div>
</div>
            <div class="col-md-2">
                <select name="rol" class="form-select" required>
                    <option value="">Rol</option>
                    <option value="admin">Admin</option>
                    <option value="gerente">Gerente</option>
                    <option value="operaciones">Operaciones</option>
                    <option value="produccion">Producción</option>
                    <option value="logistica">Logística</option>
                </select>
            </div>
        </div>

        <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="ver_precios" id="ver_precios">
            <label class="form-check-label" for="ver_precios">Puede ver precios de venta</label>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ver_formulas" id="ver_formulas">
            <label class="form-check-label" for="ver_formulas">Puede ver fórmulas confidenciales</label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="ver_reportes" id="ver_reportes">
            <label class="form-check-label" for="ver_reportes">Puede ver reportes financieros</label>
        </div>

        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
        <button type="submit" name="crear_usuario" class="btn btn-primary">Crear Usuario</button>
    </form>

    <?php if (!empty($_SESSION['flash_error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>


    <h5>Usuarios registrados</h5>
    <table class="table table-striped align-middle">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Correo</th>
                <th>Rol</th>
                <th>Ver precios</th>
                <th>Ver fórmulas</th>
                <th>Ver reportes</th>
                <th>Estado</th>
                <th style="width:380px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr class="<?= $u['activo'] ? '' : 'table-secondary' ?>">
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['correo']) ?></td>
                    <td><?= htmlspecialchars($u['rol']) ?></td>
                    <td><?= $u['ver_precios'] ? '✔' : '' ?></td>
                    <td><?= $u['ver_formulas'] ? '✔' : '' ?></td>
                    <td><?= $u['ver_reportes'] ? '✔' : '' ?></td>
                    <td><?= $u['activo'] ? 'Activo' : 'Deshabilitado' ?></td>
                    <td>
                      <!-- Cambiar contraseña (compacto) -->
                      <form method="POST" class="d-inline-flex align-items-center gap-2 mb-1" onsubmit="return confirm('¿Confirmas esta acción?');" style="vertical-align:middle;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="reset_pass">
                        <input type="password" name="nueva_contrasena" class="form-control form-control-sm" placeholder="Nueva contraseña" minlength="8" required style="max-width:160px;">
                        <button class="btn btn-warning btn-sm px-2" type="submit" title="Cambiar contraseña">
                          <i class="bi bi-key"></i> Cambiar
                        </button>
                      </form>
                      <!-- Editar (modal) -->
                      <button class="btn btn-outline-primary btn-sm ms-2 px-2" data-bs-toggle="modal" data-bs-target="#modalEdit<?= (int)$u['id'] ?>">
                        <i class="bi bi-pencil-square"></i> Editar
                      </button>
                      <!-- Toggle activo -->
                      <form method="POST" class="d-inline ms-2" onsubmit="return confirm('¿Deseas <?= $u['activo'] ? 'deshabilitar' : 'habilitar' ?> a este usuario?');" style="vertical-align:middle;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="toggle_activo">
                        <button class="btn btn-sm px-2 <?= $u['activo'] ? 'btn-outline-secondary' : 'btn-success' ?>" type="submit">
                          <?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?><span class="visually-hidden"> usuario</span>
                        </button>
                      </form>
                      <!-- Eliminar -->
                      <form method="POST" class="d-inline ms-2" onsubmit="return confirm('Esta acción eliminará al usuario de forma permanente. ¿Continuar?');" style="vertical-align:middle;">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <button class="btn btn-danger btn-sm px-2" type="submit"><i class="bi bi-trash"></i> Eliminar</button>
                      </form>
                    </td>

                </tr>

                <!-- Modal Editar Usuario -->
                <div class="modal fade" id="modalEdit<?= (int)$u['id'] ?>" tabindex="-1" aria-labelledby="lblEdit<?= (int)$u['id'] ?>" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="lblEdit<?= (int)$u['id'] ?>">Editar usuario: <?= htmlspecialchars($u['nombre']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                      </div>
                      <form method="POST" class="modal-body">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($CSRF) ?>">
                        <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="edit_user">

                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input name="nombre" class="form-control form-control-sm" value="<?= htmlspecialchars($u['nombre']) ?>" required>
                          </div>
                          <div class="col-md-6">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control form-control-sm" value="<?= htmlspecialchars($u['correo']) ?>" required>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Rol</label>
                            <select name="rol" class="form-select form-select-sm" required>
                              <?php
                                $roles = ['admin'=>'Admin','gerente'=>'Gerente','operaciones'=>'Operaciones','produccion'=>'Producción','logistica'=>'Logística'];
                                foreach ($roles as $key=>$label):
                              ?>
                                <option value="<?= $key ?>" <?= ($u['rol']===$key?'selected':'') ?>><?= $label ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Nómina diaria (MXN)</label>
                            <input type="number" step="0.01" name="nomina_diaria_mxn" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($u['nomina_diaria_mxn'] ?? '')) ?>">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Jornada (horas)</label>
                            <input type="number" step="0.25" name="jornada_horas" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($u['jornada_horas'] ?? '8.00')) ?>">
                          </div>
                          <div class="col-md-4">
                            <label class="form-label">Tipo de usuario</label>
                            <select name="tipo_usuario" class="form-select form-select-sm">
                              <option value="operativo"     <?= ($u['tipo_usuario'] ?? '')==='operativo'      ? 'selected':'' ?>>Operativo</option>
                              <option value="administrativo"<?= ($u['tipo_usuario'] ?? '')==='administrativo'  ? 'selected':'' ?>>Administrativo</option>
                            </select>
                          </div>
                          <div class="col-md-8 d-flex align-items-center gap-4">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="ver_precios" id="vp<?= (int)$u['id'] ?>" <?= $u['ver_precios'] ? 'checked':'' ?>>
                              <label class="form-check-label" for="vp<?= (int)$u['id'] ?>">Puede ver precios</label>
                            </div>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="ver_formulas" id="vf<?= (int)$u['id'] ?>" <?= $u['ver_formulas'] ? 'checked':'' ?>>
                              <label class="form-check-label" for="vf<?= (int)$u['id'] ?>">Puede ver fórmulas</label>
                            </div>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="ver_reportes" id="vr<?= (int)$u['id'] ?>" <?= $u['ver_reportes'] ? 'checked':'' ?>>
                              <label class="form-check-label" for="vr<?= (int)$u['id'] ?>">Puede ver reportes</label>
                            </div>
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="incluye_en_indirectos" id="ii<?= (int)$u['id'] ?>" <?= !empty($u['incluye_en_indirectos']) ? 'checked':'' ?>>
                              <label class="form-check-label" for="ii<?= (int)$u['id'] ?>">Incluir en indirectos</label>
                            </div>
                          </div>
                        </div>
                        <div class="mt-3 text-end">
                          <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                          <button type="submit" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-save"></i> Guardar cambios
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>
