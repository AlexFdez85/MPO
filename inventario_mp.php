
<?php
// inventario_mp.php

// -------------------------------------
// 1) Mostrar errores (solo desarrollo)
// -------------------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

function crear_lote_ajuste_mp(PDO $pdo, int $mpId, float $cantidad, int $userId, string $comentario = ''): void
{
    if ($cantidad <= 0) {
        return;
    }

    // 1) Proveedor técnico para ajustes
    $provNombre = 'AJUSTE INV MP';
    $stmt = $pdo->prepare("SELECT id FROM proveedores WHERE nombre = ? LIMIT 1");
    $stmt->execute([$provNombre]);
    $provId = (int)$stmt->fetchColumn();

    if (!$provId) {
        $pdo->prepare("INSERT INTO proveedores (nombre, activo) VALUES (?,1)")
            ->execute([$provNombre]);
        $provId = (int)$pdo->lastInsertId();
    }

    // 2) OC técnica cerrada (reutilizamos una del día si existe)
    $hoy = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT id
        FROM ordenes_compra
        WHERE proveedor_id = ?
          AND solicitante_id = ?
          AND estado = 'cerrada'
          AND fecha_emision = ?
          AND moneda = 'MXN'
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$provId, $userId, $hoy]);
    $ocId = (int)$stmt->fetchColumn();

    if (!$ocId) {
        $pdo->prepare("
            INSERT INTO ordenes_compra
                (proveedor_id, solicitante_id, fecha_emision, estado, moneda, tipo_cambio)
            VALUES (?, ?, ?, 'cerrada', 'MXN', 1.000000)
        ")->execute([$provId, $userId, $hoy]);
        $ocId = (int)$pdo->lastInsertId();
    }

    // 3) Línea de compra MP (costo 0; si quieres luego lo ajustas manualmente)
    $nota = 'AJUSTE INV MP';
    if ($comentario !== '') {
        $nota .= ' - ' . mb_substr($comentario, 0, 80);
    }

    $pdo->prepare("
        INSERT INTO lineas_compra
            (orden_compra_id, mp_id, ic_id, cantidad, precio_unitario, subtotal,
             moneda, tipo_cambio, descuento_pct, notas_linea)
        VALUES (?, ?, NULL, ?, 0, 0, 'MXN', 1.000000, 0, ?)
    ")->execute([$ocId, $mpId, $cantidad, $nota]);
    $lineaId = (int)$pdo->lastInsertId();

    // 4) Recepción como lote disponible
    // Usamos sólo columnas obligatorias / razonables.
    $pdo->prepare("
        INSERT INTO recepciones_compra_lineas
            (orden_compra_id, linea_id,
             cantidad_recibida, cantidad_disponible,
             fecha_ingreso, recepcionador_id, comentario)
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?)
    ")->execute([$ocId, $lineaId, $cantidad, $cantidad, $userId, $nota]);
}

/**
 * Descuenta cantidad de MP desde los lotes (FIFO) para SALIDAS manuales.
 * Mantiene alineados suma de lotes y existencia global.
 */
function consumir_lotes_mp(PDO $pdo, int $mpId, float $cantidad): void
{
    if ($cantidad <= 0) {
        return;
    }

    // Tomar lotes disponibles de más viejo a más nuevo
    $stmt = $pdo->prepare("
        SELECT rcl.id, rcl.cantidad_disponible
        FROM recepciones_compra_lineas rcl
        JOIN lineas_compra lc ON lc.id = rcl.linea_id
        WHERE lc.mp_id = ?
          AND rcl.cantidad_disponible > 0
        ORDER BY rcl.fecha_ingreso ASC, rcl.id ASC
    ");
    $stmt->execute([$mpId]);

    $restante = $cantidad;

    while ($restante > 0 && ($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
        $disp = (float)$row['cantidad_disponible'];
        if ($disp <= 0) {
            continue;
        }

        $tomar = ($disp >= $restante) ? $restante : $restante - max($restante - $disp, 0);
        // versión clara:
        $tomar = min($disp, $restante);

        $nuevoDisp = $disp - $tomar;
        $restante  -= $tomar;

        $upd = $pdo->prepare("
            UPDATE recepciones_compra_lineas
            SET cantidad_disponible = ?
            WHERE id = ?
        ");
        $upd->execute([$nuevoDisp, $row['id']]);
    }

    // Si no hubo lotes suficientes, revertimos usando excepción
    if ($restante > 0.00001) {
        throw new RuntimeException(
            'No hay lotes suficientes para cubrir la salida de ajuste (faltan '
            . $restante . ' g).'
        );
    }
}

// -------------------------------------
// 2) Validar sesión y rol base
// -------------------------------------
if (!isset($_SESSION['user_id'])
    || !in_array($_SESSION['rol'], ['produccion','logistica','gerente','admin'])
) {
    header('Location: login.php');
    exit;
}

$usuario_id  = $_SESSION['user_id'];
$rol         = $_SESSION['rol'];
$nombre      = $_SESSION['nombre'];
// Sólo admin y gerente pueden registrar movimientos directos
$canRegistro = in_array($rol, ['admin','gerente']);

// -------------------------------------
// 3) Procesar “Registrar Movimiento”
// -------------------------------------
if ($canRegistro
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['registrar_movimiento'])
) {
    // separar tipo y id: valor viene como MP-<id> o SP-<id>
    if (!preg_match('/^(MP|SP)-(\d+)$/', $_POST['item_id'], $m)) {
        $error = "Ítem inválido.";
    } else {
        $tipoItem  = $m[1];  // 'MP' o 'SP'
        $item_id   = intval($m[2]);
        $movType   = $_POST['tipo'];       // 'entrada' o 'salida'
        $cantidad  = floatval($_POST['cantidad']);
        $comentario= trim($_POST['comentario']);
        $fecha_mov = date('Y-m-d H:i:s');

          if ($tipoItem === 'MP') {
            // materia prima
            $stmt = $pdo->prepare("SELECT existencia FROM materias_primas WHERE id = ?");
            $stmt->execute([$item_id]);
            $stockActual = (float) $stmt->fetchColumn();

            if ($movType === 'salida' && $cantidad > $stockActual) {
                $error = "No hay suficiente stock de materia prima para esta salida.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1) Registrar movimiento clásico (histórico)
                    $movStmt = $pdo->prepare(
                        "INSERT INTO movimientos_mp (mp_id, tipo, cantidad, fecha, usuario_id, comentario)
                         VALUES (?, ?, ?, ?, ?, ?)"
                    );
                    $movStmt->execute([
                        $item_id,
                        $movType,
                        $cantidad,
                        $fecha_mov,
                        $usuario_id,
                        $comentario
                    ]);

                // 2) Actualizar existencia global
                $nuevo = ($movType === 'entrada')
                    ? $stockActual + $cantidad
                    : $stockActual - $cantidad;

                $pdo->prepare(
                    "UPDATE materias_primas SET existencia = ? WHERE id = ?"
                )->execute([$nuevo, $item_id]);

                // 3a) ENTRADA:
                //     Sólo creamos lote técnico cuando el comentario incluye
                //     la marca "AJUSTE INV / CREAR LOTE" (caso recomendado
                //     en el bloque de diagnóstico para MP sin lotes).
                if ($movType === 'entrada'
                    && $cantidad > 0
                    && stripos($comentario, 'AJUSTE INV / CREAR LOTE') !== false
                ) {
                    crear_lote_ajuste_mp($pdo, $item_id, $cantidad, $usuario_id, $comentario);
                }

                // 3b) SALIDA:
                //     Descontar de lotes en FIFO para mantener trazabilidad
                //     alineada con la existencia global.
                if ($movType === 'salida' && $cantidad > 0) {
                    consumir_lotes_mp($pdo, $item_id, $cantidad);
                }

                    $pdo->commit();
                    header("Location: inventario_mp.php?ok_mov=1");
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Error al registrar movimiento: " . $e->getMessage();
                }
            }
        } else {

            // subproceso → se registra como producción en gramos
            $pres = $pdo->prepare("SELECT id FROM presentaciones WHERE nombre = ?");
            $pres->execute(['Gramos']);
            $idGramos = $pres->fetchColumn();
            if (!$idGramos) {
                $error = "Falta la presentación 'Gramos' en la base de datos.";
            } else {
                $pdo->prepare(
                  "INSERT INTO productos_terminados
                   (orden_id, producto_id, presentacion_id, cantidad, lote_produccion, fecha)
                   VALUES (NULL, ?, ?, ?, NULL, ?)"
                )->execute([$item_id, $idGramos, $cantidad, date('Y-m-d')]);
                header("Location: inventario_mp.php?ok_mov=1");
                exit;
            }
        }
    }
}

// -------------------------------------
// 4) Proponer Ajuste de Inventario
// -------------------------------------
if ($rol !== 'admin'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['proponer_ajuste'])
) {
    $aj_id       = intval($_POST['item_id']);
    $aj_cantidad = floatval($_POST['cantidad_aj']);
    $aj_coment   = trim($_POST['comentario_aj']);
    if ($aj_id <= 0 || $aj_cantidad == 0 || $aj_coment === '') {
        $error = "Todos los campos del ajuste son obligatorios.";
    } else {
        $pdo->prepare(
          "INSERT INTO ajustes_mp (mp_id, cantidad, comentario, solicitante_id, fecha_solicitud, estado)
           VALUES (?, ?, ?, ?, NOW(), 'pendiente')"
        )->execute([$aj_id, $aj_cantidad, $aj_coment, $usuario_id]);
        header("Location: inventario_mp.php?ok_ajuste=1");
        exit;
    }
}

// -------------------------------------
// 5) Ajustes pendientes (solo admin)
// -------------------------------------
$ajustesPendientes = [];
if ($rol === 'admin') {
    $ajustesPendientes = $pdo->query(
      "SELECT a.id, mp.nombre AS mp_nombre, a.cantidad, a.comentario, u.nombre AS solicitante
       FROM ajustes_mp a
       JOIN materias_primas mp ON a.mp_id = mp.id
       JOIN usuarios u ON a.solicitante_id = u.id
       WHERE a.estado = 'pendiente'"
    )->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------------
// 6) Cargar MP y Subprocesos
// -------------------------------------
// 6.a Materias primas
$mpRows = $pdo->query(
  "SELECT id, nombre, unidad, existencia FROM materias_primas ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);
// 6.b Subprocesos (stock en gramos)
$presGrStmt = $pdo->prepare("SELECT id FROM presentaciones WHERE nombre = ?");
$presGrStmt->execute(['Gramos']);
$idPresGr = $presGrStmt->fetchColumn() ?: 0;
$subStmt = $pdo->prepare(
  "SELECT p.id AS id, p.nombre AS nombre, fp.unidad_produccion AS unidad,
          COALESCE(SUM(pt.cantidad),0) AS existencia
     FROM fichas_produccion fp
     JOIN productos p ON fp.producto_id = p.id
     LEFT JOIN productos_terminados pt
       ON pt.producto_id = p.id AND pt.presentacion_id = ?
    WHERE p.es_subproducto = 1
    GROUP BY p.id, p.nombre, fp.unidad_produccion"
);
$subStmt->execute([$idPresGr]);
$subRows = $subStmt->fetchAll(PDO::FETCH_ASSOC);
// unir y ordenar
$items = array_merge(
    array_map(fn($r)=>array_merge($r,['tipo'=>'MP']), $mpRows),
    array_map(fn($r)=>array_merge($r,['tipo'=>'Subproceso']), $subRows)
);
usort($items, fn($a,$b)=>strcasecmp($a['nombre'],$b['nombre']));

$diagMpSinLote     = [];
$diagMpDescuadre   = [];
$diagDiagError     = null;

// Tolerancia para diferencias por redondeo
$tolerancia = 0.1;

try {
    // 1) Existencia > 0 pero sin lotes disponibles
    $sql1 = "
        SELECT
            mp.id,
            mp.nombre,
            mp.existencia,
            COALESCE(SUM(rcl.cantidad_disponible), 0) AS lotes_disponibles
        FROM materias_primas mp
        LEFT JOIN lineas_compra lc
               ON lc.mp_id = mp.id
        LEFT JOIN recepciones_compra_lineas rcl
               ON rcl.linea_id = lc.id
        GROUP BY mp.id, mp.nombre, mp.existencia
        HAVING mp.existencia > 0
           AND lotes_disponibles <= 0
        ORDER BY mp.nombre
    ";
    $st1 = $pdo->query($sql1);
    $diagMpSinLote = $st1 ? $st1->fetchAll(PDO::FETCH_ASSOC) : [];

    // 2) Descuadres: suma(lotes) != existencia (más allá de la tolerancia)
    $sql2 = "
        SELECT
            mp.id,
            mp.nombre,
            mp.existencia,
            COALESCE(SUM(rcl.cantidad_disponible), 0) AS lotes_disponibles,
            COALESCE(SUM(rcl.cantidad_disponible), 0) - mp.existencia AS diferencia
        FROM materias_primas mp
        LEFT JOIN lineas_compra lc
               ON lc.mp_id = mp.id
        LEFT JOIN recepciones_compra_lineas rcl
               ON rcl.linea_id = lc.id
        GROUP BY mp.id, mp.nombre, mp.existencia
        HAVING ABS(diferencia) > :tol
        ORDER BY mp.nombre
    ";
    $st2 = $pdo->prepare($sql2);
    $st2->execute([':tol' => $tolerancia]);
    $diagMpDescuadre = $st2->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    $diagDiagError = $e->getMessage();
}


include 'header.php';

?>

<div class="container mt-4">
  <h3 class="text-danger mb-3">Inventario de MP & Subprocesos</h3>

  <!-- Mensajes -->
  <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php elseif (isset($_GET['ok_mov'])): ?>
    <div class="alert alert-success">Movimiento registrado correctamente.</div>
  <?php elseif (isset($_GET['ok_ajuste'])): ?>
    <div class="alert alert-success">Ajuste propuesto correctamente.</div>
  <?php endif; ?>

  <!-- Formulario Registrar Movimiento -->
  <?php if ($canRegistro): ?>
  <div class="card mb-4 p-3">
    <h5>Registrar Movimiento</h5>
    <form method="POST">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label>Ítem</label>
          <select name="item_id" class="form-select" required>
            <optgroup label="Materias Primas">
              <?php foreach($items as $it): if($it['tipo']!=='MP') continue; ?>
                <?php $ex=($it['unidad']==='kg'? $it['existencia']*1000: $it['existencia']); ?>
                <option value="MP-<?= $it['id'] ?>">
                  <?= htmlspecialchars($it['nombre']) ?> (<?= number_format($ex,2) ?> g)
                </option>
              <?php endforeach; ?>
            </optgroup>
            <optgroup label="Subprocesos">
              <?php foreach($items as $it): if($it['tipo']!=='Subproceso') continue; ?>
                <?php $ex=($it['unidad']==='kg'? $it['existencia']*1000: $it['existencia']); ?>
                <option value="SP-<?= $it['id'] ?>">
                  <?= htmlspecialchars($it['nombre']) ?> (<?= number_format($ex,2) ?> g)
                </option>
              <?php endforeach; ?>
            </optgroup>
          </select>
        </div>
        <div class="col-md-2">
          <label>Movimiento</label>
          <select name="tipo" class="form-select" required>
            <option value="entrada">Entrada</option>
            <option value="salida">Salida</option>
          </select>
        </div>
        <div class="col-md-2">
          <label>Cantidad (g)</label>
          <input type="number" name="cantidad" step="0.01" class="form-control" value="0" required>
        </div>
        <div class="col-md-3">
          <label>Comentario</label>
          <input type="text" name="comentario" class="form-control">
        </div>
        <div class="col-md-1 text-end">
          <button type="submit" name="registrar_movimiento" class="btn btn-primary">Registrar</button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Formulario Proponer Ajuste -->
  <?php if ($rol!=='admin'): ?>
  <div class="card mb-4 p-3">
    <h5>Proponer Ajuste de Inventario</h5>
    <form method="POST">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label>Ítem</label>
          <select name="item_id" class="form-select" required>
            <?php foreach($items as $it): ?>
            <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label>Cantidad (g)</label>
          <input type="number" name="cantidad_aj" step="0.01" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label>Comentario (obligatorio)</label>
          <input type="text" name="comentario_aj" class="form-control" required>
        </div>
        <div class="col-md-1 text-end">
          <button type="submit" name="proponer_ajuste" class="btn btn-warning">Enviar</button>
        </div>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Ajustes Pendientes (admin) -->
  <?php if ($rol==='admin'): ?>
  <div class="card mb-4 p-3">
    <h5>Ajustes Pendientes de Autorización</h5>
    <?php if(empty($ajustesPendientes)): ?>
      <div class="alert alert-secondary">No hay ajustes pendientes.</div>
    <?php else: ?>
      <table class="table table-striped">
        <thead><tr><th>#</th><th>MP</th><th>Cant. (g)</th><th>Solicita</th><th>Comentario</th></tr></thead>
        <tbody>
        <?php foreach($ajustesPendientes as $aj): ?>
        <tr>
          <td><?= $aj['id'] ?></td>
          <td><?= htmlspecialchars($aj['mp_nombre']) ?></td>
          <td><?= number_format($aj['cantidad'],2) ?></td>
          <td><?= htmlspecialchars($aj['solicitante']) ?></td>
          <td><?= htmlspecialchars($aj['comentario']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  
 <div class="card mb-4">
  <div class="card-header">Diagnóstico para Producción</div>
  <div class="card-body">
    <?php if ($diagDiagError): ?>
      <div class="alert alert-warning mb-3">
        No se pudo verificar completamente la trazabilidad de lotes:<br>
        <code><?= htmlspecialchars($diagDiagError) ?></code>
      </div>
    <?php endif; ?>

    <?php if (empty($diagMpSinLote) && empty($diagMpDescuadre) && !$diagDiagError): ?>
      <div class="alert alert-success mb-0">
        ✅ Todas las materias primas con existencia tienen lotes disponibles
        y la suma de lotes coincide con la existencia global (dentro de la tolerancia).
        El inventario está listo para ser utilizado por producción.
      </div>
    <?php else: ?>

      <?php if (!empty($diagMpSinLote)): ?>
        <div class="alert alert-danger">
          ⚠️ Las siguientes materias primas tienen existencia &gt; 0 pero
          <strong>no tienen lotes disponibles</strong>.
          Ejecutar producción con ellas podría fallar.
        </div>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Materia Prima</th>
                <th>Existencia (g)</th>
                <th>Lotes disponibles</th>
                <th>Acción sugerida</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($diagMpSinLote as $row): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td class="text-end"><?= number_format($row['existencia'], 2, '.', ',') ?></td>
                <td class="text-end"><?= number_format($row['lotes_disponibles'], 2, '.', ',') ?></td>
                <td>
                  Crear entrada con lote desde esta página
                  (Movimiento: <strong>Entrada</strong>,
                  comentario: <code>AJUSTE INV / CREAR LOTE</code>).
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($diagMpDescuadre)): ?>
        <div class="alert alert-warning">
          ⚠️ Las siguientes materias primas tienen
          <strong>descuadre entre la existencia global y la suma de lotes</strong>.
          Producción usará los lotes; ajusta para evitar residuos o stocks fantasmas.
        </div>
        <div class="table-responsive mb-0">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>Materia Prima</th>
                <th>Existencia (g)</th>
                <th>Suma lotes (g)</th>
                <th>Diferencia (lotes - existencia)</th>
                <th>Acción sugerida</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($diagMpDescuadre as $row): ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td class="text-end"><?= number_format($row['existencia'], 2, '.', ',') ?></td>
                <td class="text-end"><?= number_format($row['lotes_disponibles'], 2, '.', ',') ?></td>
                <td class="text-end">
                  <?= number_format($row['diferencia'], 4, '.', ',') ?>
                </td>
<td>
  <!-- diferencia = suma de lotes - existencia global -->
  Si diferencia &gt; 0:
  registrar una <strong>entrada</strong> (subir existencia hasta la suma de lotes;
  usar movimiento normal, <strong>sin</strong> la marca de crear lote).<br>
  Si diferencia &lt; 0:
  crear lote para la diferencia usando una
  <strong>entrada con comentario</strong>
  <code>AJUSTE INV / CREAR LOTE</code>
  o revisar / corregir los lotes manualmente.
</td>

              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

  
  <?php endif; ?>



  <!-- Inventario Completo -->
  <div class="card mb-5 p-3">
    <h5>Lista de Materias Primas y Subprocesos</h5>
    <table class="table table-striped">
      <thead><tr><th>Nombre</th><th>Unidad</th><th>Existencia (g)</th><th>Tipo</th></tr></thead>
      <tbody>
      <?php foreach($items as $m):
        $u = $m['unidad']?:'g';
        $ex=($u==='kg'? $m['existencia']*1000: $m['existencia']);
      ?>
        <tr>
          <td><?= htmlspecialchars($m['nombre']) ?></td>
          <td><?= htmlspecialchars($u) ?></td>
          <td><?= number_format($ex,2) ?></td>
          <td><?= $m['tipo']==='MP'? 'MP':'Subproceso' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'footer.php'; ?>
```




