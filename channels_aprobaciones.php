<?php
// mpo/channels_aprobaciones.php
// Requiere que MPO ya tenga sesión de admin/gerente
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php'; // $pdo

// Valida rol MPO (ajusta según tu sesión: $_SESSION['user'], etc.)
// Autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'gerente'], true)) {
    header('Location: dashboard.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];
$rol        = $_SESSION['rol'];

// ==== Utilidades de similitud / normalización ====
function norm_text($s){
  $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
  $s = preg_replace('~[^a-z0-9 ]+~i',' ',strtolower($s));
  $s = preg_replace('~\s+~',' ',trim($s));
  return $s;
}
function only_digits($s){ return preg_replace('~\D+~','',$s ?? ''); }

// Busca coincidencias en clientes, contactos y en otras tablas de prospectos
function posibles_similitudes(PDO $pdo, array $h){
    $out = [];

    $razon = trim($h['razon_social'] ?? '');
    $contacto_tel = only_digits($h['contacto_tel'] ?? ($h['telefono'] ?? ''));
    $domicilio = trim(($h['ubicacion'] ?? $h['domicilio'] ?? ''));
    $rfc = trim($h['rfc'] ?? '');
    $contacto_email = trim($h['contacto_email'] ?? ($h['email'] ?? ''));

    // 1) En clientes por nombre (aprox) y dirección
    if ($razon !== '') {
        $stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE SOUNDEX(nombre)=SOUNDEX(:n) OR nombre LIKE :like LIMIT 5");
        $stmt->execute([':n'=>$razon, ':like'=>'%'.$razon.'%']);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $out[] = 'Cliente: #'.$row['id'].' '.htmlspecialchars($row['nombre']);
        }
    }
    if ($domicilio !== '') {
        $stmt = $pdo->prepare("SELECT id, nombre FROM clientes WHERE direccion LIKE :dir LIMIT 5");
        $stmt->execute([':dir'=>'%'.$domicilio.'%']);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $out[] = 'Cliente(dir): #'.$row['id'].' '.htmlspecialchars($row['nombre']);
        }
    }
    if ($contacto_tel !== '') {
        $stmt = $pdo->prepare("SELECT c.id, c.nombre, ct.telefono FROM clientes c JOIN contactos ct ON ct.cliente_id=c.id");
        $stmt->execute();
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            if (only_digits($row['telefono']) === $contacto_tel) {
                $out[] = 'Cliente(tel): #'.$row['id'].' '.htmlspecialchars($row['nombre']).' ('.$row['telefono'].')';
            }
        }
    }

    // 2) En otros prospectos (C_channels_prospectos)
    if ($razon !== '' || $rfc !== '' || $contacto_email !== '' || $contacto_tel !== '') {
        $sql = "SELECT id, razon_social, rfc, contacto_email, contacto_tel FROM C_channels_prospectos WHERE 1=1";
        $params = [];
        if ($rfc !== '') { $sql .= " AND rfc = :rfc"; $params[':rfc']=$rfc; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $match = false;
            if ($rfc !== '' && $row['rfc'] === $rfc) $match = true;
            if (!$match && $contacto_email !== '' && strtolower($row['contacto_email'])===strtolower($contacto_email)) $match = true;
            if (!$match && $contacto_tel !== '' && only_digits($row['contacto_tel'])===$contacto_tel) $match = true;
            if (!$match && $razon !== '' && similar_text(norm_text($row['razon_social']), norm_text($razon)) >= 80) $match = true;
            if ($match){ $out[] = 'Prospecto canal: #'.$row['id'].' '.htmlspecialchars($row['razon_social']); }
        }
    }

    // 3) En registro de distribuidores (C_distribuidores_registro)
    if ($razon !== '' || $rfc !== '' || $contacto_email !== '' || $domicilio !== '') {
        $sql = "SELECT id, razon_social, rfc, email, domicilio FROM C_distribuidores_registro WHERE 1=1";
        $stmt = $pdo->query($sql);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $match = false;
            if ($rfc !== '' && $row['rfc'] === $rfc) $match = true;
            if (!$match && $contacto_email !== '' && strtolower($row['email'])===strtolower($contacto_email)) $match = true;
            if (!$match && $domicilio !== '' && norm_text($row['domicilio'])===norm_text($domicilio)) $match = true;
            if (!$match && $razon !== '' && similar_text(norm_text($row['razon_social']), norm_text($razon)) >= 80) $match = true;
            if ($match){ $out[] = 'Registro distribuidor: #'.$row['id'].' '.htmlspecialchars($row['razon_social']); }
        }
    }

    return array_values(array_unique($out));
}

// ==== Acciones ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id    = (int)($_POST['id'] ?? 0);
  $act   = $_POST['action'] ?? '';
  $nota  = trim($_POST['nota'] ?? '');
  $tipo  = $_POST['tipo'] ?? 'canal'; // canal | prospecto

  if ($tipo === 'canal') {
      if ($act === 'eliminar' && $id) {
          $pdo->prepare("DELETE FROM C_distribuidores_registro WHERE id=:id")->execute([':id'=>$id]);
      } elseif ($act === 'aprobar' && $id) {
          // Aprobar y promover a 'clientes' como DISTRIBUIDOR
          $pdo->beginTransaction();
          try {
              $r = $pdo->prepare("SELECT * FROM C_distribuidores_registro WHERE id=:id FOR UPDATE");
              $r->execute([':id'=>$id]);
              $row = $r->fetch(PDO::FETCH_ASSOC);
              if ($row) {
                  $direccion = trim(($row['domicilio'] ?? '') . 
                                    (isset($row['ciudad']) ? ', '.$row['ciudad'] : '') .
                                    (isset($row['estado']) ? ', '.$row['estado'] : '') .
                                    (isset($row['cp']) ? ' C.P. '.$row['cp'] : ''));
                  // Inserta en clientes como distribuidor
                  $ins = $pdo->prepare("INSERT INTO clientes
                        (nombre, contacto, direccion, datos_envio, activo, es_distribuidor, distribuidor_id)
                        VALUES (:n, :c, :d, '', 1, 1, NULL)");
                  $ins->execute([
                      ':n' => $row['razon_social'],
                      ':c' => trim(($row['representante'] ?? '') . ' · ' . ($row['email'] ?? '') . 
                                   (($row['telefono']??'') ? ' / '.$row['telefono'] : '')),
                      ':d' => $direccion
                  ]);
                  $clienteId = (int)$pdo->lastInsertId();
                  // Contacto básico
                  if (!empty($row['representante']) || !empty($row['telefono'])) {
                      $pdo->prepare("INSERT INTO contactos (cliente_id, nombre, telefono) VALUES (?,?,?)")
                          ->execute([$clienteId, $row['representante'] ?? '', $row['telefono'] ?? '']);
                  }
                  // Marca como aprobado y elimina de la cola
                  $pdo->prepare("UPDATE C_distribuidores_registro
                                 SET status='aprobado', rechazo_motivo=:nota, approved_by=:uid,
                                     approved_at=NOW(), updated_at=NOW()
                                 WHERE id=:id")
                      ->execute([':nota'=>$nota, ':uid'=>(int)$usuario_id, ':id'=>$id]);
                  $pdo->prepare("DELETE FROM C_distribuidores_registro WHERE id=:id")->execute([':id'=>$id]);
              }
              $pdo->commit();
          } catch (Exception $e) {
              $pdo->rollBack();
              throw $e;
          }
      } else {
          $map = ['aprobar' => 'aprobado', 'rechazar' => 'rechazado', 'revision' => 'en_revision'];
          if ($id && isset($map[$act])) {
              $pdo->prepare("UPDATE C_distribuidores_registro
                             SET status=:st, rechazo_motivo=:nota, approved_by=:uid,
                                 approved_at = IF(:st='aprobado', NOW(), approved_at),
                                 updated_at = NOW()
                             WHERE id=:id")
                  ->execute([
                    ':st'  => $map[$act],
                    ':nota'=> $nota,
                    ':uid' => (int)($usuario_id ?? 0),
                    ':id'  => $id
                  ]);
          }
      }
  } elseif ($tipo === 'prospecto') {
      if ($act === 'eliminar' && $id) {
          $pdo->prepare("DELETE FROM C_channels_prospectos WHERE id=:id")->execute([':id'=>$id]);
          
      } elseif ($act === 'aprobar' && $id) {
          // Aprobar y promover a 'clientes' como CLIENTE de un mayorista
          $pdo->beginTransaction();
          try {
              $r = $pdo->prepare("SELECT * FROM C_channels_prospectos WHERE id=:id FOR UPDATE");
              $r->execute([':id'=>$id]);
              $row = $r->fetch(PDO::FETCH_ASSOC);
              if ($row) {
                  $ins = $pdo->prepare("INSERT INTO clientes
                        (nombre, contacto, direccion, datos_envio, activo, es_distribuidor, distribuidor_id)
                        VALUES (:n, :c, :d, '', 1, 0, NULL)");
                  $ins->execute([
                      ':n' => $row['razon_social'],
                      ':c' => trim(($row['contacto_nombre'] ?? '') . ' · ' . ($row['contacto_email'] ?? '') .
                                   (($row['contacto_tel']??'') ? ' / '.$row['contacto_tel'] : '')),
                      ':d' => $row['ubicacion'] ?? ''
                  ]);
                  $clienteId = (int)$pdo->lastInsertId();
                  // Contacto
                  if (!empty($row['contacto_nombre']) || !empty($row['contacto_tel'])) {
                      $pdo->prepare("INSERT INTO contactos (cliente_id, nombre, telefono) VALUES (?,?,?)")
                          ->execute([$clienteId, $row['contacto_nombre'] ?? '', $row['contacto_tel'] ?? '']);
                  }
                  // Relación con mayorista/distribuidor que lo registró
                  if (!empty($row['distribuidor_user_id'])) {
                      $pdo->prepare("INSERT INTO clientes_mayorista
                                (cliente_id, mayorista_user_id, status, exclusivo, created_at, updated_at)
                                VALUES (:cid, :uid, 'activo', 1, NOW(), NOW())")
                          ->execute([
                              ':cid' => $clienteId,
                              ':uid' => (int)$row['distribuidor_user_id']
                          ]);
                  }
                  // Marca aprobado y elimina de la cola
                  $pdo->prepare("UPDATE C_channels_prospectos
                                 SET status='aprobado', rechazo_motivo=:nota, validated_by=:uid,
                                     validated_at=NOW(), updated_at=NOW()
                                 WHERE id=:id")
                      ->execute([':nota'=>$nota, ':uid'=>(int)$usuario_id, ':id'=>$id]);
                  $pdo->prepare("DELETE FROM C_channels_prospectos WHERE id=:id")->execute([':id'=>$id]);
              }
              $pdo->commit();
          } catch (Exception $e) {
              $pdo->rollBack();
             throw $e;
          }
      } else {
          $map = ['aprobar' => 'aprobado', 'rechazar' => 'rechazado', 'pendiente'=>'pendiente', 'disputa'=>'en_disputa'];
          if ($id && isset($map[$act])) {
              $pdo->prepare("UPDATE C_channels_prospectos
                             SET status=:st, rechazo_motivo=:nota, validated_by=:uid,
                                 validated_at = IF(:st='aprobado', NOW(), validated_at),
                                 updated_at = NOW()
                             WHERE id=:id")
                  ->execute([
                    ':st'  => $map[$act],
                    ':nota'=> $nota,
                    ':uid' => (int)($usuario_id ?? 0),
                    ':id'  => $id
                  ]);
          }
      }
  }
  header('Location: channels_aprobaciones.php?tipo='.urlencode($tipo)); exit;
}

// ==== Listado según tipo ====
$tipo = $_GET['tipo'] ?? 'canal'; // 'canal' (web) o 'prospecto' (mayorista)
$st   = $_GET['status'] ?? 'pendiente';

if ($tipo === 'canal') {
    $stmt = $pdo->prepare("SELECT r.*, cu.nombre AS mayorista_nombre, cu.email AS mayorista_email
                           FROM C_distribuidores_registro r
                           LEFT JOIN C_canal_usuarios cu ON cu.id = r.mayorista_user_id
                           WHERE r.status = :st
                           ORDER BY r.created_at DESC");
    $stmt->execute([':st'=>$st]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else { // prospecto
    $stmt = $pdo->prepare("SELECT p.*, cu.nombre AS distribuidor_nombre, cu.email AS distribuidor_email
                           FROM C_channels_prospectos p
                           JOIN C_canal_usuarios cu ON cu.id = p.distribuidor_user_id
                           WHERE p.status = :st
                           ORDER BY p.created_at DESC");
    $stmt->execute([':st'=>$st]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include 'header.php'; ?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><title>Aprobaciones – Canales y Prospectos</title>
<link rel="stylesheet" href="/mpo/assets/css/bootstrap.min.css">
</head><body class="p-3">
<h1 class="h4 mb-3">Aprobaciones / Prospectos</h1>

<!-- Filtros -->
<div class="mb-3 d-flex gap-2 align-items-center">
  <div class="btn-group" role="group" aria-label="Tipo">
    <a class="btn btn<?= ($tipo==='canal'?'':'-outline') ?>-primary btn-sm" href="?tipo=canal&status=<?= urlencode($st) ?>">Solicitudes de Distribuidor (WEB)</a>
    <a class="btn btn<?= ($tipo==='prospecto'?'':'-outline') ?>-primary btn-sm" href="?tipo=prospecto&status=<?= urlencode($st) ?>">Prospectos de Mayoristas</a>
  </div>
  <div class="ms-2">
    <a class="btn btn-outline-secondary btn-sm" href="?tipo=<?= urlencode($tipo) ?>&status=pendiente">Pendientes</a>
    <a class="btn btn-outline-secondary btn-sm" href="?tipo=<?= urlencode($tipo) ?>&status=en_revision">En revisión</a>
    <a class="btn btn-outline-secondary btn-sm" href="?tipo=<?= urlencode($tipo) ?>&status=aprobado">Aprobados</a>
    <a class="btn btn-outline-secondary btn-sm" href="?tipo=<?= urlencode($tipo) ?>&status=rechazado">Rechazados</a>
    <?php if($tipo==='prospecto'): ?><a class="btn btn-outline-secondary btn-sm" href="?tipo=prospecto&status=en_disputa">En disputa</a><?php endif; ?>
  </div>
</div>

<div class="table-responsive">
<table class="table table-striped align-middle">
  <?php if ($tipo==='canal'): ?>
  <thead>
    <tr>
      <th>Fecha</th><th>Empresa</th><th>Contacto</th><th>Zona</th><th>Volumen</th>
      <th>Fuente</th><th>Registrado por</th><th>Similitudes</th><th>Estado</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rows as $r): ?>
      <?php $sim = posibles_similitudes($pdo, $r); ?>
      <tr>
        <td><?= htmlspecialchars($r['created_at']) ?></td>
        <td><?= htmlspecialchars($r['razon_social']) ?><br><small class="text-muted"><?= htmlspecialchars($r['domicilio'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($r['representante']) ?> · <?= htmlspecialchars($r['email']) ?><br><small><?= htmlspecialchars($r['telefono'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($r['zonas_interes']) ?></td>
        <td>$<?= number_format((float)$r['volumen_estimado'],2) ?></td>
        <td><span class="badge bg-info">WEB (<?= htmlspecialchars($r['origen']) ?>)</span></td>
        <td>
          <?php if(!empty($r['mayorista_user_id'])): ?>
            <div><strong><?= htmlspecialchars($r['mayorista_nombre'] ?? ('#'.$r['mayorista_user_id'])) ?></strong></div>
            <div class="text-muted small"><?= htmlspecialchars($r['mayorista_email'] ?? '') ?></div>
          <?php else: ?>
            <span class="text-muted">Público</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if($sim): ?>
            <ul class="small mb-0">
              <?php foreach($sim as $s): ?><li><?= $s ?></li><?php endforeach; ?>
            </ul>
          <?php else: ?>
            <span class="text-muted small">Sin coincidencias</span>
          <?php endif; ?>
        </td>
        <td><span class="badge bg-secondary"><?= strtoupper($r['status']) ?></span></td>
        <td>
          <form method="post" class="d-flex gap-2">
            <input type="hidden" name="tipo" value="canal">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input class="form-control form-control-sm" name="nota" placeholder="Nota (opcional)">
            <button name="action" value="revision" class="btn btn-outline-secondary btn-sm">En revisión</button>
            <button name="action" value="aprobar"  class="btn btn-success btn-sm">Aprobar</button>
            <button name="action" value="rechazar" class="btn btn-warning btn-sm">Rechazar</button>
            <button name="action" value="eliminar" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar definitivamente?');">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <?php else: // tabla de prospectos de mayoristas ?>
  <thead>
    <tr>
      <th>Fecha</th><th>Empresa</th><th>Contacto</th><th>Línea interés</th><th>Valor estimado</th>
      <th>Fuente</th><th>Registrado por</th><th>Similitudes</th><th>Estado</th><th></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach($rows as $r): ?>
      <?php $sim = posibles_similitudes($pdo, $r); ?>
      <tr>
        <td><?= htmlspecialchars($r['created_at']) ?></td>
        <td><?= htmlspecialchars($r['razon_social']) ?><br><small class="text-muted"><?= htmlspecialchars($r['ubicacion'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($r['contacto_nombre']) ?> · <?= htmlspecialchars($r['contacto_email']) ?><br><small><?= htmlspecialchars($r['contacto_tel'] ?? '') ?></small></td>
        <td><?= htmlspecialchars($r['linea_interes'] ?? '') ?></td>
        <td>$<?= number_format((float)$r['valor_estimado'],2) ?></td>
        <td><span class="badge bg-primary">MAYORISTA</span></td>
        <td>
          <div><strong><?= htmlspecialchars($r['distribuidor_nombre'] ?? ('#'.$r['distribuidor_user_id'])) ?></strong></div>
          <div class="text-muted small"><?= htmlspecialchars($r['distribuidor_email'] ?? '') ?></div>
        </td>
        <td>
          <?php if($sim): ?>
            <ul class="small mb-0">
              <?php foreach($sim as $s): ?><li><?= $s ?></li><?php endforeach; ?>
            </ul>
          <?php else: ?>
            <span class="text-muted small">Sin coincidencias</span>
          <?php endif; ?>
        </td>
        <td><span class="badge bg-secondary"><?= strtoupper($r['status']) ?></span></td>
        <td>
          <form method="post" class="d-flex gap-2">
            <input type="hidden" name="tipo" value="prospecto">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input class="form-control form-control-sm" name="nota" placeholder="Nota (opcional)">
            <button name="action" value="pendiente" class="btn btn-outline-secondary btn-sm">Pendiente</button>
            <button name="action" value="aprobar"  class="btn btn-success btn-sm">Aprobar</button>
            <button name="action" value="rechazar" class="btn btn-warning btn-sm">Rechazar</button>
            <button name="action" value="disputa" class="btn btn-outline-danger btn-sm">Marcar disputa</button>
            <button name="action" value="eliminar" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar definitivamente?');">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <?php endif; ?>
</table>
</div>

<script src="/mpo/assets/js/bootstrap.bundle.min.js"></script>
</body></html>
