 <?php
// Forzar UTF-8 SOLO en esta página (antes de cualquier salida)
ini_set('default_charset', 'UTF-8');

//terminados.php

header('Content-Type: text/html; charset=UTF-8');

require_once 'config.php';
// ===== AJAX: lotes por producto/presentación (FIFO) =====
if (isset($_GET['action']) && $_GET['action'] === 'lotes_por_pp') {
  header('Content-Type: application/json; charset=UTF-8');
  $pid  = (int)($_GET['producto_id'] ?? 0);
  $prid = (int)($_GET['presentacion_id'] ?? 0);

  // Sumamos stock por lote y le juntamos reservas activas por lote
  $sql = "
    SELECT
      pt.lote_produccion                    AS lote,
      DATE_FORMAT(MIN(pt.fecha),'%Y-%m-%d') AS fecha,
      /* STOCK NETO POR LOTE = PT − SURTIDOS */
      (SUM(pt.cantidad) - COALESCE(sv.salida,0)) AS stock,
      COALESCE(rv.apartado,0)               AS apartado
    FROM productos_terminados pt
    /* Surtidos por lote+producto+presentación */
    LEFT JOIN (
      SELECT lv.producto_id, lv.presentacion_id, sv.lote_produccion, SUM(sv.cantidad) AS salida
      FROM surtidos_venta sv
      JOIN lineas_venta lv ON lv.id = sv.linea_venta_id
      GROUP BY lv.producto_id, lv.presentacion_id, sv.lote_produccion
    ) sv
      ON sv.producto_id = pt.producto_id
     AND sv.presentacion_id = pt.presentacion_id
     AND sv.lote_produccion = pt.lote_produccion
    /* Reservas activas por lote */
    LEFT JOIN (
      SELECT lote_codigo, SUM(cantidad) AS apartado
      FROM reservas_venta
      WHERE estado='activa'
      GROUP BY lote_codigo
    ) rv ON rv.lote_codigo = pt.lote_produccion
    WHERE pt.producto_id = ? AND pt.presentacion_id = ?
    GROUP BY pt.lote_produccion, sv.salida, rv.apartado
    ORDER BY MIN(pt.fecha) ASC
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$pid, $prid]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as &$r) {
    $stock = (float)($r['stock'] ?? 0);
    $apart = (float)($r['apartado'] ?? 0);
    $r['disponible'] = max(0, $stock - $apart);
  }
  echo json_encode($rows);
  exit;
}


// Helper de escape en UTF-8 (evita problemas al imprimir)
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if (isset($_GET['action']) && $_GET['action'] === 'buscar_lotes') {
  header('Content-Type: application/json; charset=UTF-8');
  try {
    $q = trim($_GET['q'] ?? '');
    if ($q === '' || mb_strlen($q) < 2) { echo json_encode([]); exit; }

    $qlen = (int) mb_strlen($q, 'UTF-8');
    $like = '%'.$q.'%';

    // NOTA: 'sv.fecha' NO existe -> usamos NULL AS fecha en ese branch
     $sql = "
      SELECT
        x.lote,
        MIN(x.fecha)                           AS fecha,
        x.producto_id,
        x.presentacion_id,
        MAX(x.producto)                        AS producto,
        MAX(x.presentacion)                    AS presentacion,
        /* Stock neto del lote PARA ESTE producto/presentación = PT − SV */
        COALESCE((
          SELECT SUM(pt2.cantidad)
          FROM productos_terminados pt2
          WHERE pt2.lote_produccion = x.lote
            AND pt2.producto_id     = x.producto_id
            AND pt2.presentacion_id = x.presentacion_id
        ),0)
        -
        COALESCE((
          SELECT SUM(sv2.cantidad)
          FROM surtidos_venta sv2
          JOIN lineas_venta lv2 ON lv2.id = sv2.linea_venta_id
          WHERE sv2.lote_produccion = x.lote
            AND lv2.producto_id     = x.producto_id
            AND lv2.presentacion_id = x.presentacion_id
        ),0)                                   AS stock_lote,
        /* Surtido total histórico PARA ESTE producto/presentación */
        COALESCE((
          SELECT SUM(sv3.cantidad)
          FROM surtidos_venta sv3
          JOIN lineas_venta lv3 ON lv3.id = sv3.linea_venta_id
          WHERE sv3.lote_produccion = x.lote
            AND lv3.producto_id     = x.producto_id
            AND lv3.presentacion_id = x.presentacion_id
        ),0)                                   AS surtido_total,
        /* Apartado: si tu tabla guarda producto/presentación, fíltalos; si no, deja solo lote */
        COALESCE((
          SELECT SUM(rv.cantidad)
          FROM reservas_venta rv
          WHERE rv.lote_codigo = x.lote
            AND rv.estado='activa'
            /* descomenta si existen estas columnas:
            AND rv.producto_id = x.producto_id
            AND rv.presentacion_id = x.presentacion_id
            */
        ),0)                                   AS apartado,       
        /* Prioriza coincidencia por sufijo (los últimos N chars) */
        CASE WHEN RIGHT(x.lote, ?) = ? THEN 0 ELSE 1 END AS prio
      FROM (
        
        /* Ramas que alimentan x: SOLO productos de venta */
  SELECT pt.lote_produccion AS lote, MIN(pt.fecha) AS fecha,
         pt.producto_id, pt.presentacion_id,
         p.nombre AS producto, pr.nombre AS presentacion
  FROM productos_terminados pt
  JOIN productos p       ON p.id = pt.producto_id
  JOIN presentaciones pr ON pr.id = pt.presentacion_id
  WHERE p.es_para_venta = 1
    AND LOWER(pr.nombre) <> 'gramos'
        GROUP BY pt.lote_produccion, pt.producto_id, pt.presentacion_id

        UNION

        
  SELECT sv.lote_produccion AS lote, NULL AS fecha,
         lv.producto_id, lv.presentacion_id,
         p.nombre, pr.nombre
  FROM surtidos_venta sv
  JOIN lineas_venta   lv  ON lv.id = sv.linea_venta_id
  JOIN productos      p   ON p.id  = lv.producto_id
  JOIN presentaciones pr  ON pr.id = lv.presentacion_id
  WHERE p.es_para_venta = 1
    AND LOWER(pr.nombre) <> 'gramos'
        GROUP BY sv.lote_produccion, lv.producto_id, lv.presentacion_id
      ) x
      WHERE x.lote LIKE ?
      GROUP BY x.lote, x.producto_id, x.presentacion_id
      ORDER BY prio ASC, fecha DESC
      LIMIT 100
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$qlen, $q, $like]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

// Consulta: STOCK, APARTADO y DISPONIBLE por producto/presentación
$sql = "
  SELECT
    p.id      AS producto_id,
    p.nombre  AS producto,
    pr.id     AS presentacion_id,
    pr.nombre AS presentacion,

    /* PT bruto (Stock físico antes de restar surtidos) */
    COALESCE((
      SELECT SUM(pt.cantidad)
      FROM productos_terminados pt
      WHERE pt.producto_id = pp.producto_id
        AND pt.presentacion_id = pp.presentacion_id
    ),0) AS stock,

    /* Surtidos confirmados (salidas reales) acumulados */
    COALESCE((
      SELECT SUM(sv.cantidad)
      FROM surtidos_venta sv
      JOIN lineas_venta lv2 ON lv2.id = sv.linea_venta_id
      WHERE lv2.producto_id    = pp.producto_id
        AND lv2.presentacion_id = pp.presentacion_id
    ),0) AS salidas_surtidas,

    /* Stock neto = PT − Surtidos (lo usamos para Disponible y MRP) */
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

    /* Apartado = reservas activas (se usa solo para Disponible de mostrador) */
    COALESCE((
      SELECT SUM(rv.cantidad)
      FROM reservas_venta rv
      WHERE rv.producto_id    = pp.producto_id
        AND rv.presentacion_id = pp.presentacion_id
        AND rv.estado = 'activa'
    ),0) AS apartado,

    /* Demanda pendiente = ventas abiertas aún no surtidas */
    COALESCE(dp.demanda_pendiente, 0) AS demanda_pendiente,

    /* Faltante (MRP) = max(0, demanda_pendiente − stock_neto) */
    GREATEST(
      0,
      COALESCE(dp.demanda_pendiente,0)
      -
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
      )
    ) AS faltante_mrp

  FROM productos_presentaciones pp
  JOIN productos      p  ON p.id  = pp.producto_id
  JOIN presentaciones pr ON pr.id = pp.presentacion_id

  /* Demanda pendiente por producto/presentación (solo OV no entregadas) */
  LEFT JOIN (
    SELECT
      lv.producto_id,
      lv.presentacion_id,
      SUM(lv.cantidad) - COALESCE(SUM(sv.cant_surtida),0) AS demanda_pendiente
    FROM lineas_venta lv
    JOIN ordenes_venta ov
      ON ov.id = lv.orden_venta_id
     AND ov.estado <> 'entregado'
    LEFT JOIN (
      SELECT linea_venta_id, SUM(cantidad) AS cant_surtida
      FROM surtidos_venta
      GROUP BY linea_venta_id
    ) sv ON sv.linea_venta_id = lv.id
    GROUP BY lv.producto_id, lv.presentacion_id
  ) dp ON dp.producto_id = pp.producto_id
      AND dp.presentacion_id = pp.presentacion_id

  WHERE p.es_para_venta = 1
  ORDER BY p.nombre, pr.volumen_ml, pr.nombre
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Prepara estructura agrupada por producto (totales + hijos)
$grouped = [];
foreach ($rows as $r) {
    $prod = $r['producto'];
    $disp = max(0, (float)($r['stock_neto'] ?? $r['stock']) - (float)$r['apartado']);
    if (!isset($grouped[$prod])) {
        $grouped[$prod] = [
            'stock' => 0.0,
            'apartado' => 0.0,
            'disponible' => 0.0,
            'faltante_mrp' => 0.0,
            'children' => []
        ];
    }
    $grouped[$prod]['stock']       += (float)($r['stock_neto'] ?? $r['stock']);
    $grouped[$prod]['apartado']    += (float)$r['apartado'];
    $grouped[$prod]['disponible']  += $disp;
    $grouped[$prod]['faltante_mrp']+= (float)($r['faltante_mrp'] ?? 0);
    $grouped[$prod]['children'][] = [
    'producto_id'  => (int)$r['producto_id'],
    'presentacion_id' => (int)$r['presentacion_id'],
        'presentacion' => $r['presentacion'],
        'stock'        => (float)($r['stock_neto'] ?? $r['stock']),
        'apartado'     => (float)$r['apartado'],
        'disponible'   => $disp,
        'faltante_mrp' => (float)($r['faltante_mrp'] ?? 0),
        'producto'     => $prod
    ];
}
?>
<?php include 'header.php'; ?>
<style>
  .inventory-wrap{max-width: 1320px;}
  .inventory-card{padding:1.25rem;border-radius:1rem;box-shadow:0 8px 20px rgba(0,0,0,.06);}
  .inventory-controls .form-control,
  .inventory-controls .form-select{height:44px}
  .table-inv tbody tr>td{padding-top:.9rem;padding-bottom:.9rem}
</style>

<div class="row g-3 align-items-center mb-4 inventory-controls">
  <div class="col-lg-6 col-md-7">
    <input id="invSearch" type="text" class="form-control form-control-lg" placeholder="Buscar producto o presentacion...">
  </div>
  <div class="col-lg-3 col-md-5">
    <select id="groupToggle" class="form-select">
      <option value="flat">Sin agrupar</option>
      <option value="group">Agrupar por producto</option>
    </select>
  </div>
  <div class="col-lg-3 col-md-12 d-flex gap-4">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="onlyAvailable" checked>
      <label class="form-check-label" for="onlyAvailable">Solo disponibles &gt; 0</label>
    </div>
    <div class="form-check">
      <input class="form-check-input" type="checkbox" id="lowStockOnly">
      <label class="form-check-label" for="lowStockOnly">Solo bajo stock (&lt; 5)</label>
    </div>
  </div>

  <div class="col-12">
    <div class="input-group" style="max-width: 600px;">
      <input id="searchLoteInput" type="text" class="form-control form-control-lg" placeholder="Buscar por numero de lote (min 2 caracteres)">
      <button id="searchLoteBtn" class="btn btn-primary" type="button">Buscar lotes</button>
    </div>
    <small class="text-muted">Incluye lotes ya entregados. Elige un resultado para abrir la trazabilidad.</small>
  </div>
</div>
  <!-- Tabla SIN agrupar -->
  <div class="inventory-card">
<table id="tablaFlat" class="table table-striped table-hover align-middle table-inv">

                                    <thead>
                                      <tr>
                                        <th>Producto</th>
                                        <th>Presentacion</th>
                                        <th>Stock fisico</th>
                                        <th>Apartado</th>
                                        <th>Disponible</th>
                                        <th>Faltante (MRP)</th>
                                        <th style="width:1%">Lotes</th>
                                      </tr>
                                    </thead>
    <tbody>
      <?php foreach ($rows as $r):
            $disp = max(0, (float)($r['stock_neto'] ?? $r['stock']) - (float)$r['apartado']);
            $badge = $disp <= 0 ? 'badge bg-danger' : ($disp < 5 ? 'badge bg-warning' : 'badge bg-success');
      ?>
<tr class="flat-row"
          data-producto="<?= e($r['producto']) ?>"
          data-presentacion="<?= e($r['presentacion']) ?>"
          data-disponible="<?= number_format($disp, 3, '.', '') ?>"
          data-pid="<?= (int)$r['producto_id'] ?>"
          data-prid="<?= (int)$r['presentacion_id'] ?>">
        <td class="text-truncate" style="max-width:280px"><?= htmlspecialchars($r['producto']) ?></td>
        <td><?= htmlspecialchars($r['presentacion']) ?></td>
        <td><?= number_format(($r['stock_neto'] ?? $r['stock']), 2) ?></td>
        <td><?= number_format($r['apartado'], 2) ?></td>
        <td><span class="<?= $badge ?>"><?= number_format($disp, 2) ?></span></td>
        <td><?= number_format($r['faltante_mrp'], 2) ?></td>
        <td>
  <button
    type="button"
    class="btn btn-sm btn-outline-secondary ver-lotes"
    data-pid="<?= (int)$r['producto_id'] ?>"
    data-prid="<?= (int)$r['presentacion_id'] ?>"
  >Ver lotes</button>
</td>

      </tr>
      <tr class="flat-lotes-row" style="display:none;">
      <td colspan="7">
        <div class="p-2 border rounded lotes-container"></div>
      </td>
    </tr>
          <?php endforeach; ?>
    </tbody>
  </table>
</div>

  <!-- Tabla AGRUPADA por producto -->
  <div class="inventory-card" style="display:none" id="cardGrouped">
<table id="tablaGrouped" class="table table-striped table-hover align-middle table-inv" style="display:none">

    <thead>
      <tr>
        <th>Producto</th>
        <th>Presentacion</th>
        <th>Stock fisico</th>
        <th>Apartado</th>
        <th>Disponible</th>
        <th>Faltante (MRP)</th>
        <th style="width:1%">Lotes</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($grouped as $prod => $g):
            $badgeG = $g['disponible'] <= 0 ? 'badge bg-danger' : ($g['disponible'] < 5 ? 'badge bg-warning' : 'badge bg-success');
      ?>
        <!-- Fila cabecera del grupo -->
        <tr class="group-header table-secondary"
            data-producto="<?= htmlspecialchars($prod) ?>"
            data-disponible="<?= number_format($g['disponible'], 3, '.', '') ?>"
            style="cursor:pointer">
          <td colspan="2"><strong><?= htmlspecialchars($prod) ?></strong> <small class="text-muted">(click para expandir/ocultar)</small></td>
          <td><strong><?= number_format($g['stock'], 2) ?></strong></td> <!-- ya es neto por el acumulado -->
          <td><strong><?= number_format($g['apartado'], 2) ?></strong></td>
          <td><span class="<?= $badgeG ?>"><?= number_format($g['disponible'], 2) ?></span></td>
          <td><strong><?= number_format($g['faltante_mrp'], 2) ?></strong></td>
        </tr>
        <?php foreach ($g['children'] as $ch):
              $badgeC = $ch['disponible'] <= 0 ? 'badge bg-danger' : ($ch['disponible'] < 5 ? 'badge bg-warning' : 'badge bg-success');
        ?>
        <tr class="group-child"
            data-producto="<?= htmlspecialchars($ch['producto']) ?>"
            data-presentacion="<?= htmlspecialchars($ch['presentacion']) ?>"
            data-disponible="<?= number_format($ch['disponible'], 3, '.', '') ?>">
          <td class="ps-4"><?= htmlspecialchars($ch['producto']) ?></td>
          <td><?= htmlspecialchars($ch['presentacion']) ?></td>
          <td><?= number_format($ch['stock'], 2) ?></td>
          <td><?= number_format($ch['apartado'], 2) ?></td>
          <td><span class="<?= $badgeC ?>"><?= number_format($ch['disponible'], 2) ?></span></td>
          <td><?= number_format($ch['faltante_mrp'], 2) ?></td>
            <td>
  <button type="button"
          class="btn btn-sm btn-outline-secondary ver-lotes"
          data-pid="<?= (int)$ch['producto_id'] ?>"
          data-prid="<?= (int)$ch['presentacion_id'] ?>">Ver lotes</button>
</td>

        </tr>
        
        <tr class="group-lotes-row" style="display:none;">
  <td colspan="7">
    <div class="p-2 border rounded lotes-container"></div>
  </td>
</tr>

        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
    
  <!-- Tabla AGRUPADA por producto -->
  <table id="tablaGrouped" class="table table-striped align-middle" style="display:none">
    <thead>
      <tr>
        <th>Producto</th>
        <th>Presentacion</th>
        <th>Stock fisico</th>
        <th>Apartado</th>
        <th>Disponible</th>
        <th>Faltante (MRP)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($grouped as $prod => $g):
            $badgeG = $g['disponible'] <= 0 ? 'badge bg-danger' : ($g['disponible'] < 5 ? 'badge bg-warning' : 'badge bg-success');
      ?>
        <!-- Fila cabecera del grupo -->
        <tr class="group-header table-secondary"
            data-producto="<?= htmlspecialchars($prod) ?>"
            data-disponible="<?= number_format($g['disponible'], 3, '.', '') ?>"
            style="cursor:pointer">
          <td colspan="2"><strong><?= htmlspecialchars($prod) ?></strong> <small class="text-muted">(click para expandir/ocultar)</small></td>
          <td><strong><?= number_format($g['stock'], 2) ?></strong></td>
          <td><strong><?= number_format($g['apartado'], 2) ?></strong></td>
          <td><span class="<?= $badgeG ?>"><?= number_format($g['disponible'], 2) ?></span></td>
          <td><strong><?= number_format($g['faltante_mrp'], 2) ?></strong></td>
        </tr>
        <?php foreach ($g['children'] as $ch):
              $badgeC = $ch['disponible'] <= 0 ? 'badge bg-danger' : ($ch['disponible'] < 5 ? 'badge bg-warning' : 'badge bg-success');
        ?>
        <tr class="group-child"
            data-producto="<?= htmlspecialchars($ch['producto']) ?>"
            data-presentacion="<?= htmlspecialchars($ch['presentacion']) ?>"
            data-disponible="<?= number_format($ch['disponible'], 3, '.', '') ?>">
          <td class="ps-4"><?= htmlspecialchars($ch['producto']) ?></td>
          <td><?= htmlspecialchars($ch['presentacion']) ?></td>
          <td><?= number_format($ch['stock'], 2) ?></td>
          <td><?= number_format($ch['apartado'], 2) ?></td>
          <td><span class="<?= $badgeC ?>"><?= number_format($ch['disponible'], 2) ?></span></td>
          <td><?= number_format($ch['faltante_mrp'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
   </div> 
  </table>
</div>
<?php include 'footer.php'; ?>

<div class="modal fade" id="modalLotes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Resultados de lotes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body" id="modalLotesBody">
        <div class="text-muted">Escribe un número de lote y pulsa “Buscar lotes”.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const q        = document.getElementById('invSearch');
  const onlyDisp = document.getElementById('onlyAvailable');
  const lowOnly  = document.getElementById('lowStockOnly');
  const modeSel  = document.getElementById('groupToggle');
  const flat     = document.getElementById('tablaFlat');
  const grouped  = document.getElementById('tablaGrouped');

  function normalize(s){ return (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,''); }

  function applyFilters(){
    const term = normalize(q.value);
    const onlyAvailable = onlyDisp.checked;
    const lowStockOnly  = lowOnly.checked;
    const useGrouped = (modeSel.value === 'group');

flat.style.display    = useGrouped ? 'none' : '';
grouped.style.display = useGrouped ? '' : 'none';
const cardG = document.getElementById('cardGrouped');
if (cardG) cardG.style.display = useGrouped ? '' : 'none';


    if(!useGrouped){
      // Tabla plana
      document.querySelectorAll('#tablaFlat tbody tr.flat-row').forEach(tr=>{
        const prod = normalize(tr.dataset.producto);
        const pres = normalize(tr.dataset.presentacion)
        const disp = parseFloat(tr.dataset.disponible || '0');

        let visible = true;
        if(term && !(prod.includes(term) || pres.includes(term))) visible = false;
        if(onlyAvailable && !(disp > 0)) visible = false;
        if(lowStockOnly && !(disp > 0 && disp < 5)) visible = false;
        tr.style.display = visible ? '' : 'none';
      });
    } else {
      // Tabla agrupada: decidir visibilidad de hijos y encabezados
      const groups = {};
      document.querySelectorAll('#tablaGrouped tbody tr.group-child').forEach(tr=>{
        const prod = normalize(tr.dataset.producto);
        const pres = normalize(tr.dataset.presentacion);
        const disp = parseFloat(tr.dataset.disponible || '0');

        let visible = true;
        if(term && !(prod.includes(term) || pres.includes(term))) visible = false;
        if(onlyAvailable && !(disp > 0)) visible = false;
        if(lowStockOnly && !(disp > 0 && disp < 5)) visible = false;
        tr.style.display = visible ? '' : 'none';
        const key = tr.dataset.producto;
        if(!groups[key]) groups[key] = {any:false};
        if(visible) groups[key].any = true;
      });
      document.querySelectorAll('#tablaGrouped tbody tr.group-header').forEach(tr=>{
        const key = tr.dataset.producto;
        // Si ningún hijo visible, oculta el header
        tr.style.display = (groups[key] && groups[key].any) ? '' : 'none';
      });
    }
  }

  // Toggle expand/collapse de grupos
  document.querySelectorAll('#tablaGrouped tbody tr.group-header').forEach(header=>{
    header.addEventListener('click', ()=>{
      const prod = header.dataset.producto;
      const rows = document.querySelectorAll('#tablaGrouped tbody tr.group-child[data-producto="'+CSS.escape(prod)+'"]');
      const anyVisible = Array.from(rows).some(r=>r.style.display !== 'none');
      rows.forEach(r=>{
        // alternar solo si no está oculto por filtros (usa un flag)
        if(r.dataset.hiddenByToggle === '1'){ r.dataset.hiddenByToggle = '0'; r.style.display = ''; }
        else { r.dataset.hiddenByToggle = '1'; r.style.display = 'none'; }
      });
    });
  });

  q.addEventListener('input', applyFilters);
  onlyDisp.addEventListener('change', applyFilters);
  lowOnly.addEventListener('change', applyFilters);
  modeSel.addEventListener('change', applyFilters);
applyFilters(); // inicial

// ---------- Buscador de lotes en modal (mejorado) ----------
function openLoteSearch(q){
  const modalEl = document.getElementById('modalLotes');
  const body    = document.getElementById('modalLotesBody');
  const modal   = new bootstrap.Modal(modalEl);

  if (!q || q.length < 2) {
    body.innerHTML = '<div class="p-3 text-muted">Escribe al menos 2 caracteres.</div>';
    modal.show();
    return;
  }

  body.innerHTML = '<div class="p-3 text-muted">Buscando…</div>';

  fetch('terminados.php?action=buscar_lotes&q=' + encodeURIComponent(q))
    .then(r => r.ok ? r.json()
                    : r.text().then(t => { console.error('HTTP', r.status, t); throw new Error('HTTP '+r.status); }))
    .then(list => {
      if (!Array.isArray(list) || list.length === 0) {
        body.innerHTML = '<div class="p-3">Sin resultados. Intenta con más dígitos.</div>';
        return;
      }

      const esc  = s => String(s).replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
      const mark = (text, frag) => {
        if (!frag) return String(text ?? '');
        try { return String(text ?? '').replace(new RegExp(esc(frag),'gi'), m=>`<mark>${m}</mark>`); }
        catch { return String(text ?? ''); }
      };

      let html = '<div class="table-responsive"><table class="table table-hover align-middle mb-0">';
      html += '<thead><tr><th>Lote</th><th>Producto</th><th>Presentación</th><th>Fecha</th><th class="text-end">Disp.</th><th>Estado</th></tr></thead><tbody>';

      list.forEach(it => {
        const url = 'trazabilidad.php?lote='+encodeURIComponent(it.lote)
          + (it.producto_id ? '&producto_id='+encodeURIComponent(it.producto_id) : '')
          + (it.presentacion_id ? '&presentacion_id='+encodeURIComponent(it.presentacion_id) : '');

        const stock = parseFloat(it.stock_lote || 0);
        const apart = parseFloat(it.apartado   || 0);
        const surt  = parseFloat(it.surtido_total || 0);
        const disp  = Math.max(0, stock - apart);

        let estadoHtml;
        if (stock <= 0) {
          estadoHtml = '<span class="badge bg-secondary">Entregado</span>';
        } else if (apart > 0) {
          estadoHtml = '<span class="badge bg-warning text-dark">En bodega</span>';
        } else if (surt > 0) {
          estadoHtml = '<span class="badge bg-info">Parcialmente entregado</span>';
        } else {
          estadoHtml = '<span class="badge bg-success">En almacén</span>';
        }

        html += `<tr role="button" onclick="location.href='${url}'" title="Abrir trazabilidad">
                   <td>${mark(it.lote, q)}</td>
                   <td>${it.producto ?? ''}</td>
                   <td>${it.presentacion ?? ''}</td>
                   <td>${it.fecha ?? ''}</td>
                   <td class="text-end">${disp.toFixed(2)}</td>
                   <td>${estadoHtml}</td>
                 </tr>`;
      });

      html += '</tbody></table></div>';
      body.innerHTML = html;
    })
    .catch(err => {
      console.error(err);
      body.innerHTML = '<div class="p-3 text-danger">Error al buscar.</div>';
    });

  modal.show();
}

// ---------- Botón "Ver lotes" por fila ----------
document.querySelectorAll('.ver-lotes').forEach(btn => {
  btn.addEventListener('click', () => toggleLots(btn));
});

// ---------- Autoabrir modal al escribir 3+ caracteres ----------
let loteTimer = null;
const loteInput = document.getElementById('searchLoteInput');
if (loteInput) {
  loteInput.addEventListener('input', () => {
    clearTimeout(loteTimer);
    loteTimer = setTimeout(() => {
      const q = (loteInput.value || '').trim();
      if (q.length >= 3) openLoteSearch(q);
    }, 400);
  });
}

// ---------- Click en “Buscar lotes” ----------
const searchBtn = document.getElementById('searchLoteBtn');
if (searchBtn) {
  searchBtn.addEventListener('click', () => {
    const q = (document.getElementById('searchLoteInput').value || '').trim();
    if (q.length >= 2) openLoteSearch(q);
  });
}

function renderLotes(box, pid, prid, rows){
  let html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0">';
  html += '<thead><tr><th>Lote</th><th>Fecha</th><th class="text-end">Stock fisico</th><th class="text-end">Apartado</th><th class="text-end">Disponible</th></tr></thead><tbody>';

  if (!Array.isArray(rows) || rows.length === 0){
    html += '<tr><td colspan="5" class="text-center text-muted">Sin lotes para este producto/presentación.</td></tr>';
  } else {
    rows.forEach(r=>{
      const stock = parseFloat(r.stock ?? r.disponible ?? 0);
      const apart = parseFloat(r.apartado ?? 0);
      const disp  = Math.max(0, stock - apart);
      const url   = 'trazabilidad.php?lote=' + encodeURIComponent(r.lote)
                  + '&producto_id=' + encodeURIComponent(pid)
                  + '&presentacion_id=' + encodeURIComponent(prid);

      html += `<tr>
        <td><a href="${url}" title="Ver trazabilidad">${r.lote}</a></td>
        <td>${r.fecha ?? ''}</td>
        <td class="text-end">${stock.toFixed(2)}</td>
        <td class="text-end">${apart.toFixed(2)}</td>
        <td class="text-end">${disp.toFixed(2)}</td>
      </tr>`;
    });
  }
  html += '</tbody></table></div>';
  box.innerHTML = html;
}

// === Toggle subtabla de lotes (fila inferior) ===
function toggleLots(btn){
  const tr   = btn.closest('tr');
  const pid  = btn.dataset.pid  || (tr && tr.dataset ? tr.dataset.pid  : null);
  const prid = btn.dataset.prid || (tr && tr.dataset ? tr.dataset.prid : null);

  // Buscar el siguiente <tr> que sea contenedor de lotes (por si no es el inmediato)
  let nxt = tr ? tr.nextElementSibling : null;
  while (nxt && !nxt.classList.contains('flat-lotes-row') && !nxt.classList.contains('group-lotes-row')) {
    nxt = nxt.nextElementSibling;
  }
  if (!pid || !prid || !nxt) {
    console.warn('Faltan datos para lotes', { pid, prid, nxt });
    return;
  }

  const box = nxt.querySelector('.lotes-container');

  if (nxt.style.display === 'none' || nxt.style.display === '') {
    // Cargar por AJAX la primera vez
    if (!box.dataset.loaded) {
      fetch(`terminados.php?action=lotes_por_pp&producto_id=${encodeURIComponent(pid)}&presentacion_id=${encodeURIComponent(prid)}`)
        .then(r => r.ok ? r.json() : r.text().then(t => { console.error('HTTP', r.status, t); throw new Error('HTTP '+r.status); }))
        .then(rows => {
          renderLotes(box, pid, prid, rows);
          box.dataset.loaded = '1';
        })
        .catch(err => {
          console.error(err);
          box.innerHTML = '<div class="text-danger">Error al cargar lotes.</div>';
        });
    }
    nxt.style.display = '';
  } else {
    nxt.style.display = 'none';
  }
}

})(); // <- CIERRA LA IIFE UNA SOLA VEZ
</script>