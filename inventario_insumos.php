<?php
// inventario_insumos.php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'config.php';

// Sólo logística, admin o gerente pueden acceder aquí
if (!in_array($_SESSION['rol'], ['logistica','admin','gerente'])) {
  header('Location: dashboard.php');
  exit;
}

$rol    = $_SESSION['rol'];
$userId = $_SESSION['user_id'];
$flash  = '';

// 1) Procesar nuevo movimiento de insumo (solo admin/gerente)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($rol, ['admin','gerente'])
    && isset($_POST['registrar_insumo'])
) {
    $insumoId = intval($_POST['insumo_id']);
    $tipo     = $_POST['tipo'];     // 'entrada' | 'salida'
    $cant     = floatval($_POST['cantidad']);
    $com      = trim($_POST['comentario']);

    // CORRECCIÓN: cerrar correctamente la cadena SQL con una sola comilla
    $stmt = $pdo->prepare(
      "INSERT INTO movimientos_insumos
         (insumo_id, tipo, cantidad, comentario, usuario_id, creado_en)
       VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$insumoId, $tipo, $cant, $com, $userId]);

    $flash = 'Movimiento registrado.';
}

// 2) Procesar propuesta de ajuste (solo logistica)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $rol === 'logistica'
    && isset($_POST['proponer_ajuste_insumo'])
) {
    $insumoId = intval($_POST['insumo_id_aj']);
    $cant     = floatval($_POST['cantidad_aj']);
    $com      = trim($_POST['comentario_aj']);

    $stmt = $pdo->prepare(
      "INSERT INTO ajustes_insumos
         (insumo_id, cantidad, comentario, usuario_id, estado, creado_en)
       VALUES (?, ?, ?, ?, 'pendiente', NOW())"
    );
    $stmt->execute([$insumoId, $cant, $com, $userId]);

    $flash = 'Ajuste propuesto y queda pendiente de autorizacion.';
}

// 3) Autorizar o rechazar ajustes (solo admin/gerente)
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && in_array($rol, ['admin','gerente'])
    && isset($_POST['accion_ajuste'])
) {
    $ajId   = intval($_POST['ajuste_id']);
    $estado = $_POST['accion_ajuste'] === 'aprobar' ? 'autorizada' : 'rechazada';

    $stmt = $pdo->prepare(
      "UPDATE ajustes_insumos
         SET estado = ?, autorizador_id = ?, autorizado_en = NOW()
       WHERE id = ?"
    );
    $stmt->execute([$estado, $userId, $ajId]);

    $flash = $estado === 'autorizada' ? 'Ajuste aprobado.' : 'Ajuste rechazado.';
}

// 4) Traer lista de insumos comerciales
$insumos = $pdo->query(
  "SELECT id, tipo, nombre, stock, unidad, proveedor
     FROM insumos_comerciales
    ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

// 5) Traer ajustes pendientes para autorizacion (solo admin/gerente)
$ajPend = [];
if (in_array($rol, ['admin','gerente'])) {
    $ajPend = $pdo->prepare(
      "SELECT a.id, a.insumo_id, i.nombre AS insumo_nombre,
              a.cantidad, a.comentario, u.nombre AS proponente
         FROM ajustes_insumos a
         JOIN insumos_comerciales i ON i.id = a.insumo_id
         JOIN usuarios u ON u.id = a.usuario_id
        WHERE a.estado = 'pendiente'
        ORDER BY a.creado_en"
    );
    $ajPend->execute();
    $ajPend = $ajPend->fetchAll(PDO::FETCH_ASSOC);
}

// --- Comienza HTML ---
include 'header.php';
?>
<div class="container mt-4">
  <?php if ($flash): ?>
    <div class="alert alert-info"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (in_array($rol, ['admin','gerente'])): ?>
  <!-- Form: Registrar Movimiento -->
  <div class="card mb-4 p-3">
    <h5>Realizar ajuste de inventario:</h5>
    <form method="POST" class="row g-3 align-items-end">
      <input type="hidden" name="registrar_insumo" value="1">
      <div class="col-md-3">
        <label class="form-label">Ítem</label>
        <select name="insumo_id" class="form-select" required>
          <option value="">-- Selecciona insumo --</option>
          <?php foreach ($insumos as $i): ?>
            <option value="<?= $i['id'] ?>">
              <?= htmlspecialchars($i['nombre']) ?> (<?= $i['unidad'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select" required>
          <option value="entrada">Entrada</option>
          <option value="salida">Salida</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Cantidad</label>
        <input type="number" step="0.01" name="cantidad"
               class="form-control" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Comentario <small class="text-muted">(opcional)</small></label>
        <input type="text" name="comentario" class="form-control">
      </div>
      <div class="col-md-2 text-end">
        <button class="btn btn-danger">Registrar</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if ($rol === 'logistica'): ?>
  <!-- Form: Proponer Ajuste -->
  <div class="card mb-4 p-3">
    <h5>Proponer Ajuste de Inventario: La cantidad sera sumada al stock actual.</h5>
    <form method="POST" class="row g-3 align-items-end">
      <input type="hidden" name="proponer_ajuste_insumo" value="1">
      <div class="col-md-3">
        <label class="form-label">Ítem</label>
        <select name="insumo_id_aj" class="form-select" required>
          <option value="">-- Selecciona insumo --</option>
          <?php foreach ($insumos as $i): ?>
            <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Cantidad</label>
        <input type="number" step="0.01" name="cantidad_aj"
               class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Comentario <small class="text-muted">(obligatorio)</small></label>
        <input type="text" name="comentario_aj" class="form-control" required>
      </div>
      <div class="col-md-3 text-end">
        <button class="btn btn-warning">Proponer Ajuste</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <?php if (in_array($rol, ['admin','gerente'])): ?>
  <!-- Tabla: Ajustes Pendientes de Autorizacion -->
  <div class="card mb-4 p-3">
    <h5>Ajustes Pendientes de Autorizacion</h5>
    <?php if (empty($ajPend)): ?>
      <div class="alert alert-secondary">No hay ajustes pendientes.</div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr>
            <th>Insumo</th>
            <th class="text-end">Cantidad</th>
            <th>Comentario</th>
            <th>Propuesto por</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ajPend as $a): ?>
            <tr>
              <td><?= htmlspecialchars($a['insumo_nombre']) ?></td>
              <td class="text-end"><?= number_format($a['cantidad'],2,',','.') ?></td>
              <td><?= htmlspecialchars($a['comentario']) ?></td>
              <td><?= htmlspecialchars($a['proponente']) ?></td>
              <td>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="ajuste_id" value="<?= $a['id'] ?>">
                  <button name="accion_ajuste" value="aprobar" class="btn btn-sm btn-success">Aprobar</button>
                </form>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="ajuste_id" value="<?= $a['id'] ?>">
                  <button name="accion_ajuste" value="rechazar" class="btn btn-sm btn-danger">Rechazar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Tabla: Inventario de Insumos Comerciales -->
  <div class="card mb-4 p-3">
    <h5>Inventario de Insumos Comerciales</h5>
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Tipo</th>
          <th class="text-end">Stock</th>
          <th>Unidad</th>
          <th>Proveedor</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($insumos as $i): ?>
          <tr>
            <td><?= htmlspecialchars($i['nombre']) ?></td>
            <td><?= htmlspecialchars($i['tipo']) ?></td>
            <td class="text-end"><?= number_format($i['stock'],2,',','.') ?></td>
            <td><?= htmlspecialchars($i['unidad']) ?></td>
            <td><?= htmlspecialchars($i['proveedor']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php'; ?>
