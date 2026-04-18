<?php
// ordenes_produccion.php

// -----------------------------
// 1. Mostrar errores (sólo desarrollo)
// -----------------------------
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// === Mini-endpoint para el contenido del modal "Fórmula + Faltantes" ===
if (isset($_GET['modal']) && $_GET['modal'] === 'formula' && isset($_GET['op'])) {
    $opId = (int)$_GET['op'];
    // Datos base de la OP
    $st = $pdo->prepare("
      SELECT op.id, op.ficha_id, op.cantidad_a_producir AS cantidad, op.unidad,
             p.id AS producto_id, p.nombre AS producto, COALESCE(p.densidad_kg_por_l,1) AS densidad
        FROM ordenes_produccion op
        JOIN fichas_produccion f ON f.id = op.ficha_id
        JOIN productos p         ON p.id = f.producto_id
       WHERE op.id = ?
    ");
    $st->execute([$opId]);
    $op = $st->fetch(PDO::FETCH_ASSOC);
    if (!$op) { http_response_code(404); echo '<div class="p-3">OP no encontrada.</div>'; exit; }
    // gramos solicitados
    $gramos = ($op['unidad']==='kg') ? ((float)$op['cantidad']*1000.0) : (float)$op['cantidad'];
    // ingredientes de la ficha con stock actual (MP o Subproceso en gramos)
    if (!function_exists('getPresentacionIdGramos')) { require_once 'config.php'; }
    $presG = getPresentacionIdGramos($pdo);
    $stmtMP = $pdo->prepare("
      SELECT
        CASE WHEN fmp.mp_id<=100000 THEN mp.id ELSE (fmp.mp_id-100000) END AS id_norm,
        COALESCE(mp.nombre, prod.nombre) AS nombre,
        fmp.porcentaje_o_gramos          AS gramos_formula,
        COALESCE(mp.existencia, prod_ex.stock_total, 0) AS stock_actual
      FROM ficha_mp fmp
      LEFT JOIN materias_primas mp ON mp.id = fmp.mp_id
      LEFT JOIN productos prod     ON prod.id = (fmp.mp_id - 100000)
      LEFT JOIN (
        SELECT producto_id, SUM(cantidad) AS stock_total
          FROM productos_terminados
         WHERE presentacion_id = :presG
         GROUP BY producto_id
      ) prod_ex ON prod_ex.producto_id = prod.id
      WHERE fmp.ficha_id = :ficha
    ");
    $stmtMP->execute([':presG'=>$presG, ':ficha'=>$op['ficha_id']]);
    $ings = $stmtMP->fetchAll(PDO::FETCH_ASSOC);
    $totalBase = array_sum(array_map(fn($r)=> (float)$r['gramos_formula'], $ings));
    // Render compacto
    ob_start(); ?>
    <div class="p-2">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div><strong>OP #<?= (int)$op['id'] ?></strong> — <?= htmlspecialchars($op['producto']) ?></div>
        <div class="text-muted small">Lote solicitado: <?= number_format($gramos,2) ?> g</div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Insumo</th><th class="text-end">%</th><th class="text-end">Necesario (g)</th><th class="text-end">Stock (g)</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($ings as $r):
            $pct = $totalBase>0 ? ((float)$r['gramos_formula']/$totalBase)*100.0 : 0.0;
            $nec = ($pct/100.0)*$gramos;
            $stk = (float)$r['stock_actual'];
            $estado = ($stk < $nec) ? 'text-danger' : (($stk < $nec*1.1)? 'text-warning' : 'text-success');
          ?>
            <tr>
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td class="text-end"><?= number_format($pct,2) ?></td>
              <td class="text-end"><?= number_format($nec,2) ?></td>
              <td class="text-end"><?= number_format($stk,2) ?></td>
              <td class="text-end">
                <span class="<?= $estado ?>">●</span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

// ====== SUGERENCIAS MRP PARA PRODUCCIÓN ======

// Config general (puedes sobreescribir por ?min_lote=12)
$MIN_LOTE_DEF = isset($_GET['min_lote']) ? max(1, (int)$_GET['min_lote']) : 6;

// Helper: intenta obtener gramos/unidad a partir del lote más reciente (Envase + Gramos)
// === g/u (gramos por unidad) usando densidad (kg/L) × volumen_ml ===
function gramos_por_unidad(PDO $pdo, int $productoId, int $presentacionId): ?float {
    // 1) Densidad del producto
    $densidad = null;

    // Intenta densidad_kg_por_l (la que sí tienes)
    try {
        $st = $pdo->prepare("SELECT densidad_kg_por_l FROM productos WHERE id = ?");
        $st->execute([$productoId]);
        $val = $st->fetchColumn();
        if ($val !== false && $val !== null && (float)$val > 0) {
            $densidad = (float)$val; // kg/L
        }
    } catch (Throwable $e) {
        // ignorar
    }

    // Fallback opcional: si tuvieras una columna 'densidad'
    if ($densidad === null) {
        try {
            $st = $pdo->prepare("SELECT densidad FROM productos WHERE id = ?");
            $st->execute([$productoId]);
            $val = $st->fetchColumn();
            if ($val !== false && $val !== null && (float)$val > 0) {
                $densidad = (float)$val; // kg/L
            }
        } catch (Throwable $e) {
            // ignorar
        }
    }

    if ($densidad === null) return null; // sin densidad no podemos calcular g/u

    // 2) Volumen de la presentación (ml)
    $st = $pdo->prepare("SELECT nombre, volumen_ml FROM presentaciones WHERE id = ?");
    $st->execute([$presentacionId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;

    $ml = $row['volumen_ml'] !== null ? (float)$row['volumen_ml'] : 0.0;

    // Presentación "Gramos": si no hay volumen, por convenio 1 u = 1 g
    if ($ml <= 0 && stripos((string)$row['nombre'], 'gram') !== false) {
        return 1.0;
    }
    if ($ml <= 0) return null;

    // 3) g/u = kg/L × ml
    return $densidad * $ml;  // p.ej. 0.879 × 4000 = 3516 g/u
}


// Query MRP base: faltante (u) por producto/presentación
$mrpSql = "
  SELECT
    p.id      AS producto_id,
    p.nombre  AS producto,
    pr.id     AS presentacion_id,
    pr.nombre AS presentacion,

    /* PT bruto */
    COALESCE((
      SELECT SUM(pt.cantidad)
      FROM productos_terminados pt
      WHERE pt.producto_id = pp.producto_id
        AND pt.presentacion_id = pp.presentacion_id
    ),0) AS stock,

    /* Surtidos (salidas reales) */
    COALESCE((
      SELECT SUM(sv.cantidad)
      FROM surtidos_venta sv
      JOIN lineas_venta lv2 ON lv2.id = sv.linea_venta_id
      WHERE lv2.producto_id    = pp.producto_id
        AND lv2.presentacion_id = pp.presentacion_id
    ),0) AS salidas_surtidas,

    /* Stock neto */
    (
      COALESCE((
        SELECT SUM(pt.cantidad)
        FROM productos_terminados pt
        WHERE pt.producto_id = pp.producto_id
          AND pt.presentacion_id = pp.presentacion_id
      ),0)
      -
      COALESCE((
        SELECT SUM(sv.cantidad)
        FROM surtidos_venta sv
        JOIN lineas_venta lv2 ON lv2.id = sv.linea_venta_id
        WHERE lv2.producto_id    = pp.producto_id
          AND lv2.presentacion_id = pp.presentacion_id
      ),0)
    ) AS stock_neto,

    /* Demanda pendiente = OV no entregadas */
    COALESCE((
      SELECT SUM(lv.cantidad) - COALESCE(SUM(svv.cant_surtida),0)
      FROM lineas_venta lv
      JOIN ordenes_venta ov ON ov.id = lv.orden_venta_id AND ov.estado <> 'entregado'
      LEFT JOIN (
        SELECT linea_venta_id, SUM(cantidad) AS cant_surtida
        FROM surtidos_venta GROUP BY 1
      ) svv ON svv.linea_venta_id = lv.id
      WHERE lv.producto_id = pp.producto_id
        AND lv.presentacion_id = pp.presentacion_id
    ),0) AS demanda_pendiente

  FROM productos_presentaciones pp
  JOIN productos p      ON p.id  = pp.producto_id
  JOIN presentaciones pr ON pr.id = pp.presentacion_id
  WHERE p.es_para_venta = 1
  ORDER BY p.nombre, pr.nombre
";

$mrpRows = $pdo->query($mrpSql)->fetchAll(PDO::FETCH_ASSOC);

// === Producción en curso por PRODUCTO (gramos) =======================
// g_pend: OP no completadas (pendiente o en_proceso) sin rechazar
// g_aut : de las anteriores, las que YA están autorizadas
$prodCurso = [];
$st = $pdo->query("
  SELECT
    fp.producto_id AS pid,
    SUM(
      CASE
        WHEN op.estado <> 'completada' AND op.estado_autorizacion <> 'rechazada'
        THEN CASE WHEN op.unidad='kg' THEN op.cantidad_a_producir*1000 ELSE op.cantidad_a_producir END
        ELSE 0
      END
    ) AS g_pend,
    SUM(
      CASE
        WHEN op.estado <> 'completada' AND op.estado_autorizacion = 'autorizada'
        THEN CASE WHEN op.unidad='kg' THEN op.cantidad_a_producir*1000 ELSE op.cantidad_a_producir END
        ELSE 0
      END
    ) AS g_aut
  FROM ordenes_produccion op
  JOIN fichas_produccion fp ON fp.id = op.ficha_id
  GROUP BY fp.producto_id
");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $prodCurso[(int)$r['pid']] = [
    'g_pend' => (float)$r['g_pend'],
    'g_aut'  => (float)$r['g_aut'],
  ];
}


// Armar sugerencias
// Armar sugerencias (con ajuste por OP autorizadas y aviso por OP sin autorizar)
$sugerencias = [];
foreach ($mrpRows as $r) {
    $pid  = (int)$r['producto_id'];
    $prid = (int)$r['presentacion_id'];

    // Faltante bruto en unidades de la presentación
    $falt_bruto_u = max(0, (float)$r['demanda_pendiente'] - (float)$r['stock_neto']);
    if ($falt_bruto_u <= 0) continue;

    // g/u calculado por densidad × volumen_ml (o 1 si presentación "Gramos")
    $g_u = gramos_por_unidad($pdo, $pid, $prid);
    $g_u = ($g_u !== null && $g_u > 0) ? (float)$g_u : null;

    // Producción en curso a nivel PRODUCTO (en gramos)
    $g_pend_total = $prodCurso[$pid]['g_pend'] ?? 0.0;   // OP no completadas (pendiente/en_proceso), no rechazadas
    $g_aut        = $prodCurso[$pid]['g_aut']  ?? 0.0;   // de lo anterior, lo ya AUTORIZADO
    $g_pend_sin_aut = max(0, $g_pend_total - $g_aut);    // creado pero aún sin autorizar

    // Pasar de gramos a "unidades de esta presentación"
    $u_aut  = ($g_u ? $g_aut          / $g_u : 0.0);     // lo que ya cubrirán OP autorizadas
    $u_pend = ($g_u ? $g_pend_sin_aut / $g_u : 0.0);     // OP pendientes de autorizar (solo aviso UI)

    // Ajuste: lo autorizado reduce el faltante mostrado
    $falt_ajustado_u = max(0, $falt_bruto_u - $u_aut);
    if ($falt_ajustado_u <= 0) continue; // si ya lo cubren las OP autorizadas, oculta fila

    // Gramos a producir (solo lo que todavía falta hoy)
    $g_total = ($g_u ? $falt_ajustado_u * $g_u : null);

    // Sugerencias en unidades
    $sug_cubrir = (int)ceil($falt_ajustado_u);
    $sug_min    = (int)ceil(max($falt_ajustado_u, $MIN_LOTE_DEF) / $MIN_LOTE_DEF) * $MIN_LOTE_DEF;

    $sugerencias[] = [
        'producto_id'      => $pid,
        'producto'         => $r['producto'],
        'presentacion_id'  => $prid,
        'presentacion'     => $r['presentacion'],

        'faltante_u'       => $falt_ajustado_u,
        'gramos_por_u'     => $g_u,
        'gramos_total'     => ($g_total !== null ? (float)$g_total : null),

        'sug_cubrir_u'     => $sug_cubrir,
        'sug_minimo_u'     => $sug_min,

        // >>> claves que usa tu <td> Acción <<<
        'u_pend_sinautor'  => $u_pend,   // muestra chip amarillo y deshabilita botones
        'u_cubiertas_aut'  => $u_aut,    // chip azul informativo (parte cubierta)
    ];
}


// -----------------------------
// 2. Validar sesión y rol
// -----------------------------
// Sólo usuarios con rol “admin”, “gerente” o “produccion” pueden acceder
if (!isset($_SESSION['user_id']) 
    || !in_array($_SESSION['rol'], ['admin','gerente','produccion'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$nombre     = $_SESSION['nombre'];
$rol        = $_SESSION['rol']; 

// -----------------------------
// 3. Procesar creación de nueva orden
//    (cuando se envía el formulario “Generar Orden”)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_orden'])) {
    // 3.1. Capturar datos del formulario
    $ficha_id = intval($_POST['ficha_id'] ?? 0);
    $cantidad = floatval($_POST['cantidad'] ?? 0);
    $fechaHoy = date('Y-m-d');

    // Validaciones básicas
    if ($ficha_id <= 0) {
        die("Error: Debes seleccionar una ficha de producción válida.");
    }
    if ($cantidad <= 0) {
        die("Error: La cantidad a producir debe ser mayor que cero.");
    }

    // 3.2. Obtener unidad (kg/g) directamente desde la ficha seleccionada
    $stmtUni = $pdo->prepare("
      SELECT unidad_produccion 
      FROM fichas_produccion 
      WHERE id = ?
    ");
    $stmtUni->execute([$ficha_id]);
    $rowUni = $stmtUni->fetch(PDO::FETCH_ASSOC);
    if (!$rowUni) {
        die("Error: Ficha de producción no encontrada.");
    }
    $unidadProduccion = $rowUni['unidad_produccion']; // 'kg' o 'g'

    // 3.3. Insertar la nueva orden en la tabla `ordenes_produccion`
    //      - cantidad_a_producir: será la cantidad enviada por el formulario
    //      - unidad: tomamos la unidad “kg” / “g” de la ficha
    //      - usuario_creador: grabamos el ID del usuario que generó la orden
    //      - estado_autorizacion: por defecto 'pendiente'
    //      - estado: por defecto 'pendiente'
    //      - fecha: fecha de hoy
    $insertOrden = $pdo->prepare("
      INSERT INTO ordenes_produccion
        (ficha_id, cantidad_a_producir, unidad, usuario_creador, estado_autorizacion, estado, fecha)
      VALUES (?, ?, ?, ?, 'pendiente', 'pendiente', ?)
    ");
    $insertOrden->execute([
      $ficha_id,
      $cantidad,
      $unidadProduccion,
      $usuario_id,
      $fechaHoy
    ]);

    // Redireccionar para evitar reenvío del formulario
    header("Location: ordenes_produccion.php?ok=1");
    exit;
}

// Fichas disponibles para crear nueva orden (incluye densidad del producto)
$fichas = $pdo->query("
  SELECT
    fp.id AS ficha_id,
    p.nombre AS nombre_producto,
    fp.lote_minimo AS lote_minimo_gramos,
    fp.unidad_produccion,
    p.densidad_kg_por_l
  FROM fichas_produccion fp
  JOIN productos p ON fp.producto_id = p.id
  ORDER BY p.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// 5. Traer todas las órdenes registradas (para mostrar en la tabla)
// -----------------------------
$ordenesAll = $pdo->query("
  SELECT 
    op.id                    AS orden_id,
    op.ficha_id              AS ficha_id,
    p.nombre                 AS producto,
    op.cantidad_a_producir   AS cantidad,
    op.unidad                AS unidad,
    op.estado                AS estado,
    op.estado_autorizacion   AS autorizacion,
    op.fecha_inicio          AS inicio,
    op.fecha                 AS fecha_creacion,
    op.cancelada             AS cancelada,
    op.motivo_cancelacion    AS motivo_cancelacion,
    op.cancelado_en          AS fecha_cancelacion
    
  FROM ordenes_produccion op
  JOIN fichas_produccion fp ON op.ficha_id = fp.id
  JOIN productos p           ON fp.producto_id = p.id
  ORDER BY op.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// --- 0) Cancelar OP (sólo admin o produccion), motivo obligatorio ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['accion']) && $_POST['accion'] === 'cancelar_op'
) {
    if (!in_array($rol, ['admin','produccion'])) {
        http_response_code(403);
        die('No autorizado.');
    }
    $opId   = (int)($_POST['orden_id'] ?? 0);
    $motivo = trim($_POST['motivo_cancelacion'] ?? '');
    if ($opId <= 0 || $motivo === '') {
        http_response_code(400);
        die('Falta motivo de cancelación.');
    }

   // Verificar que exista y no esté completada ni cancelada
    $ver = $pdo->prepare("
        SELECT estado, cancelada 
          FROM ordenes_produccion 
         WHERE id = ?
        LIMIT 1
   ");
    $ver->execute([$opId]);
    $row = $ver->fetch(PDO::FETCH_ASSOC);
    if (!$row) die('Orden no encontrada.');
    if ((int)$row['cancelada'] === 1) die('La orden ya está cancelada.');
    if ($row['estado'] === 'completada') die('No se puede cancelar una orden completada.');

    // Actualizar columnas de cancelación
    $upd = $pdo->prepare("
        UPDATE ordenes_produccion
           SET cancelada = 1,
               motivo_cancelacion = ?,
               cancelado_por = ?,
               cancelado_en  = NOW()
         WHERE id = ?
         LIMIT 1
    ");
    $upd->execute([$motivo, (int)$usuario_id, $opId]);
    header('Location: ordenes_produccion.php?cancel_ok=1');
    exit;
}

?>

<?php include 'header.php'; ?>
<div class="container mt-4">
  <h3 class="text-danger mb-3">Órdenes de Producción</h3>
  <?php if (isset($_GET['cancel_ok'])): ?><div class="alert alert-warning">La orden fue cancelada.</div><?php endif; ?>

  <!-- Mensaje de éxito tras crear orden -->
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">
      La orden se generó correctamente.
    </div>
  <?php endif; ?>

  <!-- 6. Formulario para crear nueva orden -->
  <div class="card mb-4 p-3">
    <h5>Crear nueva orden</h5>
    <form method="POST">
      <div class="row g-3 align-items-end">
        <!-- 6.1 Select de Fichas -->
        <div class="col-md-6">
          <label>Producto / Ficha</label>
          
        <select id="ficha_id" name="ficha_id" class="form-select" required>
          <option value="">Selecciona ficha</option>
          <?php foreach ($fichas as $f): ?>
            <?php
              $densTxt = (isset($f['densidad_kg_por_l']) && $f['densidad_kg_por_l'] !== null && $f['densidad_kg_por_l'] !== '')
                ? number_format((float)$f['densidad_kg_por_l'], 3)
                : null;
            ?>
              <option value="<?= (int)$f['ficha_id'] ?>">
                <?= htmlspecialchars($f['nombre_producto']) ?>
              — Lote mínimo <?= number_format((float)$f['lote_minimo_gramos'], 2) ?> g
              <?= $densTxt ? ' — Densidad ' . $densTxt : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
        </div>

        <!-- 6.2 Cantidad a producir -->
        <div class="col-md-4">
          <label>Cantidad a producir</label>
          <input 
            type="number" 
            step="0.01" 
            name="cantidad" 
            class="form-control" 
            placeholder="Ej. 25" 
            required
          >
        </div>

        <div class="col-md-2 text-end">
          <button type="submit" name="generar_orden" class="btn btn-primary px-4">
            Generar Orden
          </button>
        </div>
      </div>
    </form>
  </div>

<?php if (!empty($sugerencias)): ?>
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Sugerencias MRP</h5>
      <div class="text-muted small">Mínimo de lote: <?= (int)$MIN_LOTE_DEF ?> pzas (cámbialo con <code>?min_lote=12</code>)</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Presentación</th>
            <th class="text-end">Faltante (u)</th>
            <th class="text-end">g / u</th>
            <th class="text-end">Gramos a producir</th>
            <th class="text-end">Sugerir (cubrir)</th>
            <th class="text-end">Sugerir (mín. lote)</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($sugerencias as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['producto']) ?></td>
            <td><?= htmlspecialchars($s['presentacion']) ?></td>
            <td class="text-end"><?= number_format($s['faltante_u'], 2) ?></td>
            <td class="text-end"><?= $s['gramos_por_u'] !== null ? number_format($s['gramos_por_u'], 2) : '—' ?></td>
            <td class="text-end"><?= $s['gramos_total']   !== null ? number_format($s['gramos_total'], 2)   : '—' ?></td>
            <td class="text-end"><span class="badge bg-primary"><?= (int)$s['sug_cubrir_u'] ?></span></td>
            <td class="text-end"><span class="badge bg-success"><?= (int)$s['sug_minimo_u'] ?></span></td>
            
<td class="text-end">
  <?php if (($s['u_pend_sinautor'] ?? 0) > 0): ?>
    <span class="badge bg-warning me-2" title="OP creada, aún sin autorizar">
      OP sin autorizar ~<?= (int)ceil($s['u_pend_sinautor']) ?> u
    </span>
  <?php endif; ?>

  <?php if (($s['u_cubiertas_aut'] ?? 0) > 0): ?>
    <span class="badge bg-info me-2" title="Parte ya está cubierta por OP autorizada">
      Cubierto: ~<?= (int)ceil($s['u_cubiertas_aut']) ?> u
    </span>
  <?php endif; ?>

  <!-- Botones (si hay OP sin autorizar, los dejamos en 'warning' y deshabilitados para evitar duplicar) -->
  <form method="post" action="ordenes_produccion.php" class="d-inline">
    <input type="hidden" name="prefill_producto_id" value="<?= (int)$s['producto_id'] ?>">
    <input type="hidden" name="prefill_presentacion_id" value="<?= (int)$s['presentacion_id'] ?>">
    <input type="hidden" name="prefill_cantidad" value="<?= (int)$s['sug_minimo_u'] ?>">
    <button class="btn btn-sm <?= ($s['u_pend_sinautor'] ?? 0) > 0 ? 'btn-warning' : 'btn-outline-secondary' ?>"
            <?= ($s['u_pend_sinautor'] ?? 0) > 0 ? 'disabled' : '' ?>>
      Usar mínimo
    </button>
  </form>

  <form method="post" action="ordenes_produccion.php" class="d-inline ms-1">
    <input type="hidden" name="prefill_producto_id" value="<?= (int)$s['producto_id'] ?>">
    <input type="hidden" name="prefill_presentacion_id" value="<?= (int)$s['presentacion_id'] ?>">
    <input type="hidden" name="prefill_cantidad" value="<?= (int)$s['sug_cubrir_u'] ?>">
    <button class="btn btn-sm <?= ($s['u_pend_sinautor'] ?? 0) > 0 ? 'btn-warning' : 'btn-outline-primary' ?>"
            <?= ($s['u_pend_sinautor'] ?? 0) > 0 ? 'disabled' : '' ?>>
      Cubrir pedidos
    </button>
  </form>
</td>

          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
  <div class="alert alert-info">No hay faltantes MRP en este momento.</div>
<?php endif; ?>



  <!-- 7. Tabla con todas las órdenes registradas -->
  <h5>Órdenes registradas</h5>
  <table class="table table-striped">
        <thead><tr><th>ID</th><th>Producto</th><th class="text-end">Cantidad</th><th>Unidad</th><th>Estado</th><th>Autorización</th><th>Inicio</th><th>Fecha creación</th><th>Acciones</th></tr></thead>
    <tbody>
      <?php foreach ($ordenesAll as $ord): ?>
        <tr>
          <td>
            #<?= $ord['orden_id'] ?>
            <button type="button"
                    class="btn btn-link btn-sm p-0 ms-1 align-baseline js-ver-formula"
                    data-op="<?= (int)$ord['orden_id'] ?>">
              (-OP-)
            </button>
          </td>
          <td><?= htmlspecialchars($ord['producto']) ?></td>
          <td><?= number_format($ord['cantidad'], 2) ?></td>
          <td><?= htmlspecialchars($ord['unidad'] ?? '') ?></td>
          <td>
            <?php if (!empty($ord['cancelada'])): ?>
              <span class="badge bg-secondary">Cancelada</span>
              <?php if (!empty($ord['motivo_cancelacion'])): ?>
                <a href="#" class="ms-2" data-bs-toggle="tooltip"
                   title="<?= htmlspecialchars($ord['motivo_cancelacion']) ?>">ℹ️</a>
              <?php endif; ?>
            <?php else: ?>
              <?= htmlspecialchars(ucfirst($ord['estado'] ?? '')) ?>
            <?php endif; ?>
          </td>          
            <td><?= htmlspecialchars(ucfirst($ord['autorizacion'] ?? '')) ?></td>
          <td>
            <?= $ord['inicio'] 
                ? date('Y-m-d', strtotime($ord['inicio'])) 
                : '—' ?>
          </td>
          <td>
            <?= !empty($ord['inicio'])
                 ? date('Y-m-d', strtotime($ord['inicio']))
                : '—' ?>
                
          <td class="text-end">
            <?php if (in_array($rol ?? '', ['admin','produccion'])): ?>
              <?php
                $estaCancelada = !empty($ord['cancelada']);
                $estaCompleta  = isset($ord['estado']) && $ord['estado'] === 'completada';
                $disabled = ($estaCancelada || $estaCompleta) ? 'disabled' : '';
                $modalId  = 'modalCancelar'.(int)$ord['orden_id'];
              ?>
              <button class="btn btn-sm btn-outline-danger"
                      data-bs-toggle="modal"
                      data-bs-target="#<?= $modalId ?>"
                      <?= $disabled ?>>
                Cancelar
              </button>
              <!-- Modal -->
              <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                  <form method="post" class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title">Cancelar OP #<?= (int)$ord['orden_id'] ?></h5>
                    </div>
                    <div class="modal-body">
                      <input type="hidden" name="accion" value="cancelar_op">
                      <input type="hidden" name="orden_id" value="<?= (int)$ord['orden_id'] ?>">
                      <label class="form-label">Motivo (obligatorio)</label>
                      <textarea name="motivo_cancelacion" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                      <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal reutilizable -->
<div class="modal fade" id="modalFormula" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Fórmula y faltantes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalFormulaBody">
        <div class="text-muted">Cargando…</div>
      </div>
    </div>
  </div>
  </div>
<?php include 'footer.php'; ?>
 <script>
   document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
     new bootstrap.Tooltip(el);
   });

  // Abrir modal y cargar contenido compacto
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.js-ver-formula');
    if (!btn) return;
    const op = btn.getAttribute('data-op');
    const body = document.getElementById('modalFormulaBody');
    body.innerHTML = '<div class="text-muted">Cargando…</div>';
    const modal = new bootstrap.Modal(document.getElementById('modalFormula'));
    modal.show();
    fetch('ordenes_produccion.php?modal=formula&op=' + encodeURIComponent(op))
      .then(r => r.text())
      .then(html => body.innerHTML = html)
      .catch(() => body.innerHTML = '<div class="text-danger">Error al cargar.</div>');
  }, false);
 </script>
