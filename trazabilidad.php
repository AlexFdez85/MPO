<?php
// trazabilidad.php — Trazabilidad completa por lote (MP → Subprocesos → PT)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

if (!function_exists('getPresentacionIdGramos')) {
  function getPresentacionIdGramos(PDO $pdo): ?int {
    static $id = null;
    if ($id !== null) return $id;
    $q = $pdo->query("SELECT id FROM presentaciones WHERE LOWER(slug)='gramos' OR LOWER(nombre)='gramos' LIMIT 1");
    $id = $q->fetchColumn();
    return $id ? (int)$id : null;
  }
}

$lote = isset($_GET['lote']) ? trim($_GET['lote']) : '';
if ($lote === '') { http_response_code(400); die('Lote no especificado.'); }

$presGramos = getPresentacionIdGramos($pdo);

/* ===== 1) PT del lote (positivos y negativos) ===== */
$sqlPT = "
  SELECT
    pt.id,
    pt.cantidad,
    pt.presentacion_id,
    pt.fecha,
    pt.orden_id AS op_id,
    p.nombre    AS producto,
    pr.nombre   AS presentacion,
    COALESCE(u.nombre, '-') AS usuario
  FROM productos_terminados pt
  JOIN productos p       ON p.id  = pt.producto_id
  JOIN presentaciones pr ON pr.id = pt.presentacion_id
  LEFT JOIN ordenes_produccion op ON op.id = pt.orden_id
  LEFT JOIN usuarios u            ON u.id = op.usuario_creador
  WHERE pt.lote_produccion = ?
  ORDER BY pt.id ASC
";
$stPT = $pdo->prepare($sqlPT);
$stPT->execute([$lote]);
$ptRows = $stPT->fetchAll(PDO::FETCH_ASSOC);

/* IDs de PT (+) del lote final —los usamos como “produccion_final_id” */
$ptFinalIds = array_map(
  function ($r) { return (int)$r['id']; },
  array_values(array_filter($ptRows, function ($r) { return (float)$r['cantidad'] > 0; }))
);

/* ===== 2) Consumos directos de MP del lote final ===== */
$consumosDirectos = [];
if (!empty($ptFinalIds)) {
  $in = implode(',', array_fill(0, count($ptFinalIds), '?'));
  $sql = "
    SELECT
      pc.produccion_id,
      pc.mp_id,
      mp.nombre                   AS mp_nombre,
      pc.cantidad_consumida       AS cantidad,
      rcl.id                      AS lote_recepcion_id,
      rcl.lote                    AS lote_recepcion,
      rcl.fecha_ingreso           AS fecha_ingreso,
      lc.orden_compra_id          AS oc_id,
      oc.fecha_emision            AS fecha_oc,
      oc.fecha_pago               AS fecha_pago,
      prov.nombre                 AS proveedor
    FROM produccion_consumos pc
    JOIN materias_primas mp            ON mp.id = pc.mp_id
    LEFT JOIN recepciones_compra_lineas rcl ON rcl.id = pc.lote_recepcion
    LEFT JOIN lineas_compra lc             ON lc.id  = rcl.linea_id
    LEFT JOIN ordenes_compra oc            ON oc.id  = lc.orden_compra_id
    LEFT JOIN proveedores prov             ON prov.id= oc.proveedor_id
    WHERE pc.produccion_id IN ($in)
    ORDER BY mp.nombre, rcl.fecha_ingreso, rcl.id
  ";
  $st = $pdo->prepare($sql);
  $st->execute($ptFinalIds);
  $consumosDirectos = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ===== 3) Subprocesos consumidos (por lotes origen) =====
   Requiere produccion_consumos_sp: produce la lista de PT(+)
   de subproceso de los que se tomó material para este lote final. */
$maps = [];
$ptOrigenIds = [];
if (!empty($ptFinalIds)) {
  try {
    $in = implode(',', array_fill(0, count($ptFinalIds), '?'));
    $sql = "
      SELECT
        spc.produccion_final_id,
        spc.subproducto_id,
        spc.pt_origen_id,
        spc.cantidad_g,
        p.nombre              AS subproceso,
        pt.fecha              AS fecha_pt_origen,
        pt.lote_produccion    AS lote_origen,
        op.id                 AS op_origen_id,
        op.hora_inicio,
        op.hora_fin,
        COALESCE(u.nombre,'-') AS usuario
      FROM produccion_consumos_sp spc
      JOIN productos_terminados pt ON pt.id = spc.pt_origen_id
      JOIN productos p             ON p.id  = pt.producto_id
      LEFT JOIN ordenes_produccion op ON op.id = pt.orden_id
      LEFT JOIN usuarios u            ON u.id = op.usuario_creador
      WHERE spc.produccion_final_id IN ($in)
      ORDER BY pt.fecha ASC, spc.pt_origen_id ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute($ptFinalIds);
    $maps = $st->fetchAll(PDO::FETCH_ASSOC);
    $ptOrigenIds = array_values(array_unique(array_map(fn($r)=> (int)$r['pt_origen_id'], $maps)));
  } catch (Throwable $e) {
    // Si la tabla aún no existe, simplemente no habrá subprocesos detallados.
    $maps = [];
    $ptOrigenIds = [];
  }
}

/* ===== 4) MP usadas para fabricar esos subprocesos (indirectos) ===== */
$consumosSub = [];
if (!empty($ptOrigenIds)) {
  $in = implode(',', array_fill(0, count($ptOrigenIds), '?'));
  $sql = "
    SELECT
      pc.produccion_id,              -- PT (+) del subproceso (lote origen)
      pc.mp_id,
      mp.nombre             AS mp_nombre,
      pc.cantidad_consumida AS cantidad,
      rcl.id                AS lote_recepcion_id,
      rcl.lote              AS lote_recepcion,
      rcl.fecha_ingreso     AS fecha_ingreso,
      lc.orden_compra_id    AS oc_id,
      oc.fecha_emision      AS fecha_oc,
      oc.fecha_pago         AS fecha_pago,
      prov.nombre           AS proveedor
    FROM produccion_consumos pc
    JOIN materias_primas mp            ON mp.id = pc.mp_id
    LEFT JOIN recepciones_compra_lineas rcl ON rcl.id = pc.lote_recepcion
    LEFT JOIN lineas_compra lc             ON lc.id  = rcl.linea_id
    LEFT JOIN ordenes_compra oc            ON oc.id  = lc.orden_compra_id
    LEFT JOIN proveedores prov             ON prov.id= oc.proveedor_id
    WHERE pc.produccion_id IN ($in)
    ORDER BY mp.nombre, rcl.fecha_ingreso, rcl.id
  ";
  $st = $pdo->prepare($sql);
  $st->execute($ptOrigenIds);
  $consumosSub = $st->fetchAll(PDO::FETCH_ASSOC);
}

// 4) Surtidos de Venta (cliente, distribuidor, producto/presentación y entrega real)
$stmtSale = $pdo->prepare("
  SELECT
   sv.id                         AS surtido_id,
    ov.id                         AS ov_id,
    c.nombre                      AS cliente,
    d.nombre                      AS distribuidor,
    p.nombre                      AS producto,
    pr.nombre                     AS presentacion,
    lv.cantidad                   AS cantidad_pedida,
    sv.cantidad                   AS cantidad_surtida,
    sv.fecha_surtido,
    ev.fecha_entrega              AS fecha_entrega_real
  FROM surtidos_venta sv
  JOIN ordenes_venta ov         ON ov.id = sv.orden_venta_id
  JOIN lineas_venta  lv         ON lv.id = sv.linea_venta_id
  LEFT JOIN productos p         ON p.id = lv.producto_id
  LEFT JOIN presentaciones pr   ON pr.id = lv.presentacion_id
  LEFT JOIN clientes c          ON c.id = ov.cliente_id
  LEFT JOIN clientes d          ON d.id = ov.distribuidor_id
  LEFT JOIN entregas_venta ev   ON ev.orden_venta_id = ov.id
  WHERE sv.lote_produccion = ?
  ORDER BY sv.fecha_surtido ASC, sv.id ASC
");
 $stmtSale->execute([$lote]);
 $sales = $stmtSale->fetchAll(PDO::FETCH_ASSOC);

// ids de OP del lote
$opIds = array_values(array_unique(array_map(fn($r)=>(int)$r['op_id'], $ptRows)));

$packRows = [];
if (!empty($opIds)) {
  $in = implode(',', array_fill(0, count($opIds), '?'));
  $sql = "
    SELECT
      pr.id AS request_id,
      pr.orden_id AS op_id,
      pr.estado,
      pr.autorizado_en,
      ic.nombre AS insumo,
      CASE
        WHEN COALESCE(pri.aprobado,1) = 1
          THEN COALESCE(pri.cantidad_autorizada, pri.cantidad_solicitada, pri.cantidad, 0)
        ELSE 0
      END AS cantidad
    FROM packaging_requests pr
    JOIN packaging_request_items pri ON pri.request_id = pr.id
    JOIN insumos_comerciales ic ON ic.id = pri.insumo_comercial_id
    WHERE pr.orden_id IN ($in)
      AND pr.estado = 'autorizada'
    ORDER BY pr.id, ic.nombre
  ";
  $st = $pdo->prepare($sql);
  $st->execute($opIds);
  $packRows = $st->fetchAll(PDO::FETCH_ASSOC);
}


/* ===== Helpers presentación ===== */
function fmtDuracion(?string $ini, ?string $fin): string {
  if (!$ini || !$fin) return '—';
  $t1 = strtotime($ini); $t2 = strtotime($fin);
  if (!$t1 || !$t2 || $t2 < $t1) return '—';
  $mins = (int) round(($t2 - $t1)/60);
  $h = intdiv($mins, 60); $m = $mins % 60;
  return sprintf('%dh %02dm', $h, $m);
}

// Avance de la OV = total surtido / total pedido (cap 100)
function calcAvanceOV(PDO $pdo, int $ovId): int {
  // Total pedido (tabla real de líneas)
  $q1 = $pdo->prepare("
    SELECT COALESCE(SUM(lv.cantidad),0)
    FROM lineas_venta lv
    WHERE lv.orden_venta_id = ?
  ");
  $q1->execute([$ovId]);
  $pedido = (float)$q1->fetchColumn();

  // Total surtido (la cantidad está en surtidos_venta)
  $q2 = $pdo->prepare("
    SELECT COALESCE(SUM(sv.cantidad),0)
    FROM surtidos_venta sv
    WHERE sv.orden_venta_id = ?
  ");
  $q2->execute([$ovId]);
  $surtido = (float)$q2->fetchColumn();

  if ($pedido <= 0) return 0;
  $pct = (int)round(100 * $surtido / $pedido);
  return max(0, min(100, $pct));
}
include 'header.php';
?>
<div class="container mt-4">
  <h3 class="mb-4">Trazabilidad del lote: <span class="text-primary"><?= htmlspecialchars($lote) ?></span></h3>

  <!-- A) Recepciones de compra usadas -->
  <h5 class="mt-3">Recepciones de Compra usadas</h5>
  <?php
    // Mezclamos MP directas + MP de los subprocesos
    $rowsRC = array_merge($consumosDirectos, $consumosSub);
  ?>
  <?php if (empty($rowsRC)): ?>
    <div class="alert alert-secondary">
      No se encontraron recepciones asociadas a este lote (puede tratarse de producción anterior a la habilitación de FIFO/consumos, o aún no se ha mapeado el origen de subprocesos).
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th>Materia Prima</th>
            <th>Lote recepción</th>
            <th>Fecha ingreso</th>
            <th>Proveedor</th>
            <th>OC</th>
            <th>Fecha OC</th>
            <th>Fecha pago</th>
            <th class="text-end">Usado (g)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rowsRC as $c): ?>
          <tr>
            <td><?= htmlspecialchars($c['mp_nombre']) ?></td>
            <td><?= htmlspecialchars($c['lote_recepcion'] ?: ('#'.$c['lote_recepcion_id'])) ?></td>
            <td><?= htmlspecialchars($c['fecha_ingreso'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['proveedor'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['oc_id'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['fecha_oc'] ?: '—') ?></td>
            <td><?= htmlspecialchars($c['fecha_pago'] ?: '—') ?></td>
            <td class="text-end"><?= number_format((float)$c['cantidad'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- B) Subprocesos consumidos (por lote origen) -->
  <h5 class="mt-4">Subprocesos consumidos</h5>
  <?php if (empty($maps)): ?>
    <div class="alert alert-secondary">
      No se encontraron subprocesos “mapeados” para este lote. 
      <?php if (!empty($ptFinalIds)) : ?>
        <small class="text-muted">Para ver aquí el detalle por lote origen, habilita el mapeo en <code>produccion_consumos_sp</code> (ver instrucciones).</small>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th># PT origen</th>
            <th>Subproceso</th>
            <th>Lote origen</th>
            <th class="text-end">Cantidad usada (g)</th>
            <th>Fecha prod.</th>
            <th>OP</th>
            <th>Duración</th>
            <th>Usuario</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($maps as $s): ?>
            <tr>
              <td><?= (int)$s['pt_origen_id'] ?></td>
              <td><?= htmlspecialchars($s['subproceso']) ?></td>
              <td><?= htmlspecialchars($s['lote_origen']) ?></td>
              <td class="text-end"><?= number_format((float)$s['cantidad_g'], 2) ?></td>
              <td><?= htmlspecialchars($s['fecha_pt_origen'] ?: '—') ?></td>
              <td><?= htmlspecialchars($s['op_origen_id'] ?: '—') ?></td>
              <td><?= fmtDuracion($s['hora_inicio'] ?? null, $s['hora_fin'] ?? null) ?></td>
              <td><?= htmlspecialchars($s['usuario'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- C) Producción del lote (PT +/-) -->
  <h5 class="mt-4">Producción</h5>
  <?php if (empty($ptRows)): ?>
    <div class="alert alert-secondary">No se encontraron registros de producción para este lote.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-bordered align-middle">
        <thead class="table-light">
          <tr>
            <th># PT</th>
            <th>Producto / Presentación</th>
            <th class="text-end">Cant.</th>
            <th>Fecha Prod.</th>
            <th>OP</th>
            <th>Usuario</th>
          </tr>
        </thead>
        <tbody>
          <?php
            $totalG = 0.0;
            foreach ($ptRows as $r):
              $cant = (float)$r['cantidad'];
              if ($presGramos && (int)$r['presentacion_id'] === $presGramos) $totalG += $cant;
          ?>
            <tr class="<?= $cant < 0 ? 'table-warning' : '' ?>">
              <td><?= (int)$r['id'] ?></td>
              <td><?= htmlspecialchars($r['producto']) ?> — <em><?= htmlspecialchars($r['presentacion']) ?></em></td>
              <td class="text-end"><?= number_format($cant, 2) ?></td>
              <td><?= htmlspecialchars($r['fecha']) ?></td>
              <td><?= htmlspecialchars($r['op_id'] ?: '—') ?></td>
              <td><?= htmlspecialchars($r['usuario'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($presGramos): ?>
        <tfoot>
          <tr>
            <th colspan="2" class="text-end">Total neto (g):</th>
            <th class="text-end"><?= number_format($totalG, 2) ?></th>
            <th colspan="3"></th>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  <?php endif; ?>
</div>

  <!-- NUEVO: Empaque y etiquetas usados (por OP del lote) -->
<?php if (!empty($packRows)): ?>
  <h5 class="mt-4">Empaque y etiquetas usados</h5>
  <div class="table-responsive">
    <table class="table table-sm table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>Request</th>
          <th>OP</th>
          <th>Estado</th>
          <th>Autorizado</th>
          <th>Insumo</th>
          <th class="text-end">Cantidad</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($packRows as $r): ?>
        <tr>
          <td><?= (int)$r['request_id'] ?></td>
          <td><?= (int)$r['op_id'] ?></td>
          <td>
            <?php if ($r['estado']==='pendiente'): ?>
              <span class="badge bg-warning text-dark">En espera</span>
            <?php elseif ($r['estado']==='autorizada'): ?>
              <span class="badge bg-success">Revisada</span>
            <?php else: ?>
              <span class="badge bg-secondary"><?= htmlspecialchars($r['estado']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($r['autorizado_en'] ?? '—') ?></td>
          <td><?= htmlspecialchars($r['insumo']) ?></td>
          <td class="text-end"><?= number_format((float)$r['cantidad'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="alert alert-secondary">No hay solicitudes de empaque asociadas a este lote.</div>
<?php endif; ?>


   <!-- Ventas -->
   <h5 class="mt-4">Surtidos de Venta</h5>
   <?php if (empty($sales)): ?>
     <div class="alert alert-secondary">No se encontraron ventas para este lote.</div>
   <?php else: ?>
    <table class="table table-striped table-sm">
       <thead>
         <tr>

          <th># Surt.</th>
          <th>OV</th>
          <th>Avance</th>
          <th>Cliente</th>
          <th>Distribuidor</th>
          <th>Producto / Presentación</th>
          <th class="text-end">Pedida</th>
          <th class="text-end">Surtida</th>
          <th>Fecha surtido</th>
          <th>Entrega</th>
         </tr>
       </thead>
       <tbody>

        <?php foreach ($sales as $s):
              // Calcular % de avance de la OV (total surtido / total pedido, con tope 100)
              $avance = calcAvanceOV($pdo, (int)$s['ov_id']);
              $badgeClass = ($avance >= 100)
                ? 'bg-success'
                : (($avance > 0) ? 'bg-info' : 'bg-secondary');
        ?>
          <tr>
            <td><?= (int)$s['surtido_id'] ?></td>
            <td><a href="ordenes_venta.php?id=<?= (int)$s['ov_id'] ?>">OV <?= (int)$s['ov_id'] ?></a></td>
            <td><span class="badge <?= $badgeClass ?>"><?= $avance ?>%</span></td>
            <td><?= htmlspecialchars($s['cliente'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['distribuidor'] ?? '—') ?></td>
            <td><?= htmlspecialchars(($s['producto'] ?? '—').' — '.($s['presentacion'] ?? '')) ?></td>
            <td class="text-end"><?= number_format((float)$s['cantidad_pedida'], 2) ?></td>
            <td class="text-end"><?= number_format((float)$s['cantidad_surtida'], 2) ?></td>
            <td><?= htmlspecialchars($s['fecha_surtido'] ?? '—') ?></td>
            <td><?= htmlspecialchars($s['fecha_entrega_real'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
       </tbody>
     </table>
   <?php endif; ?>
   
<?php include 'footer.php'; ?>
