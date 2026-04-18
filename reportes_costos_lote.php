<?php
// reportes_costos_lote.php
// Reporte de costo por LOTE -> costo unitario por pieza (promedio) y desglose de componentes.
// Requiere: config.php (PDO $pdo, sesi贸n iniciada). Compatible con PHP 8.x.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// 2) Validar sesión y rol
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente'])) {
    header('Location: dashboard.php');
    exit;
}

// ---- Helpers ----
function q(PDO $pdo, string $sql, array $params = []) {
  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st;
}

/**
 * Costo estimado de MP por receta cuando no hay produccion_consumos.
 * Escala ficha_mp a la cantidad solicitada de la OP y usa el último costo conocido por MP
 * (recepción -> OC -> estimado -> unitario).
 */
function costo_mp_por_ficha(PDO $pdo, int $orden_id): float {
  // ficha y cantidad de la OP
  $op = q($pdo, "SELECT ficha_id, cantidad_a_producir FROM ordenes_produccion WHERE id = ?", [$orden_id])->fetch(PDO::FETCH_ASSOC);
  if (!$op || !$op['ficha_id']) return 0.0;

  $fp = q($pdo, "SELECT lote_minimo FROM fichas_produccion WHERE id = ?", [$op['ficha_id']])->fetch(PDO::FETCH_ASSOC);
  if (!$fp || !$fp['lote_minimo'] || $fp['lote_minimo'] <= 0) return 0.0;

  $factor = ((float)$op['cantidad_a_producir'] > 0 ? (float)$op['cantidad_a_producir'] : (float)$fp['lote_minimo']) / (float)$fp['lote_minimo'];

  // receta base
  $rows = q($pdo, "SELECT mp_id, porcentaje_o_gramos FROM ficha_mp WHERE ficha_id = ?", [$op['ficha_id']])->fetchAll(PDO::FETCH_ASSOC);
  if (!$rows) return 0.0;

  $total = 0.0;
  foreach ($rows as $r) {
    $mp_id = (int)$r['mp_id'];
    $g    = (float)$r['porcentaje_o_gramos'] * $factor;  // gramos de esta MP para la OP

    // último costo conocido de esta MP (MXN por gr)
    $pu = q($pdo, "
      SELECT COALESCE(
               -- Recepción con costo (preferente)
         (SELECT COALESCE(r.costo_unitario_mxn,
                          NULLIF(r.precio_unitario_neto,0) * COALESCE(NULLIF(r.tipo_cambio,0),1),
                          lc.precio_unitario * COALESCE(NULLIF(lc.tipo_cambio,0),1))
                FROM recepciones_compra_lineas r
                LEFT JOIN lineas_compra lc ON lc.id = r.linea_id
                WHERE lc.mp_id = ?
                ORDER BY r.fecha_ingreso DESC, r.id DESC
                LIMIT 1),
               -- Línea de compra más reciente (por si la recepción no tiene costo)
               (SELECT lc.precio_unitario * COALESCE(NULLIF(lc.tipo_cambio,0),1)
                FROM lineas_compra lc
                WHERE lc.mp_id = ?
                ORDER BY lc.id DESC
                LIMIT 1),
               -- Catálogo de MP
               (SELECT NULLIF(m.precio_estimado,0)  FROM materias_primas m WHERE m.id=?),
               (SELECT NULLIF(m.precio_unitario,0) FROM materias_primas m WHERE m.id=?),
               0
             ) AS pu
    ", [$mp_id, $mp_id, $mp_id, $mp_id])->fetchColumn();

    $total += $g * (float)$pu;
  }
  return (float)$total;
}

/**
 * Desglose de costo de MP por OP (por mp_id) y total:
 * Elige el mejor precio MXN:
 *   1) r.costo_unitario_mxn
 *   2) r.precio_unitario_neto * (tipo_cambio>0 ? tipo_cambio : 1)
 *   3) lc.precio_unitario * (tipo_cambio>0 ? tipo_cambio : 1)
 *   4) lc2.precio_unitario * (tipo_cambio>0 ? tipo_cambio : 1)
 *   5) última línea OC por mp_id
 *   6) mp.precio_estimado / mp.precio_unitario
 */
function mp_cost_breakdown(PDO $pdo, int $orden_id): array {
  $rows = q($pdo, "
    SELECT
      pc.mp_id,
      m.nombre AS mp_nombre,
      SUM(pc.cantidad_consumida) AS gramos,
      COALESCE(
        r.costo_unitario_mxn,
        NULLIF(r.precio_unitario_neto,0) * COALESCE(NULLIF(r.tipo_cambio,0),1),
        lc.precio_unitario  * COALESCE(NULLIF(lc.tipo_cambio,0),1),
        lc2.precio_unitario * COALESCE(NULLIF(lc2.tipo_cambio,0),1),
        (SELECT lc3.precio_unitario * COALESCE(NULLIF(lc3.tipo_cambio,0),1)
           FROM lineas_compra lc3
          WHERE lc3.mp_id = pc.mp_id
          ORDER BY lc3.id DESC
          LIMIT 1),
        NULLIF(m.precio_estimado,0),
        NULLIF(m.precio_unitario,0),
        0
      ) AS unit_mxn,
      CASE
        WHEN r.costo_unitario_mxn IS NOT NULL AND r.costo_unitario_mxn > 0 THEN 'recepción.costo_mxn'
        WHEN r.precio_unitario_neto IS NOT NULL AND r.precio_unitario_neto > 0 THEN 'recepción.precio*TC'
        WHEN lc.id  IS NOT NULL THEN 'OC(linea_id)'
        WHEN lc2.id IS NOT NULL THEN 'OC(OC+MP)'
        WHEN EXISTS(SELECT 1 FROM lineas_compra lc3 WHERE lc3.mp_id = pc.mp_id LIMIT 1) THEN 'OC(última por MP)'
        WHEN m.precio_estimado IS NOT NULL AND m.precio_estimado > 0 THEN 'estimado'
        WHEN m.precio_unitario IS NOT NULL AND m.precio_unitario > 0 THEN 'cat.unitario'
        ELSE '0'
      END AS fuente
    FROM produccion_consumos pc
    LEFT JOIN recepciones_compra_lineas r ON r.id = pc.lote_recepcion
    LEFT JOIN lineas_compra lc  ON lc.id = r.linea_id
    LEFT JOIN lineas_compra lc2 ON lc2.orden_compra_id = r.orden_compra_id AND lc2.mp_id = pc.mp_id
    JOIN materias_primas m ON m.id = pc.mp_id
    WHERE pc.produccion_id = ?
    GROUP BY pc.mp_id, m.nombre
  ", [$orden_id])->fetchAll(PDO::FETCH_ASSOC);

  $total = 0.0;
  foreach ($rows as &$r) {
    $r['gramos']   = (float)$r['gramos'];
   $r['unit_mxn'] = (float)$r['unit_mxn'];
    $r['subtotal'] = $r['gramos'] * $r['unit_mxn'];
    $total        += $r['subtotal'];
  }
  return ['rows' => $rows, 'total' => $total];
}


function val($v, $dec=2) {
  if ($v === null) return '0.00';
  return number_format((float)$v, $dec, '.', ',');
}

// Filtros
$fecha_desde   = $_GET['desde'] ?? '';
$fecha_hasta   = $_GET['hasta'] ?? '';
$producto_id   = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;
$lote_like     = trim($_GET['lote'] ?? '');
$incluir_mo    = isset($_GET['mo']) ? (int)$_GET['mo'] : 1;
$incluir_ind   = isset($_GET['ind']) ? (int)$_GET['ind'] : 1;
// Mostrar solo lotes ya envasados (con piezas > 0)
$solo_envasado = isset($_GET['envasado']) ? 1 : 1; // por default activado

$where = "op.estado = 'completada'";
$params = [];
if ($fecha_desde !== '') { $where .= " AND op.fecha >= ?"; $params[] = $fecha_desde; }
if ($fecha_hasta !== '') { $where .= " AND op.fecha <= ?"; $params[] = $fecha_hasta; }
if ($producto_id > 0) {
  // Coincide si la OP tiene producto_id o si lo trae productos_terminados
  $where .= " AND (op.producto_id = ? OR EXISTS (
                    SELECT 1 FROM productos_terminados ptf
                    WHERE ptf.orden_id = op.id AND ptf.producto_id = ?
                  ))";
  $params[] = $producto_id;
  $params[] = $producto_id;
}
if ($lote_like !== '')   { $where .= " AND op.lote LIKE ?"; $params[] = "%{$lote_like}%"; }
if ($solo_envasado)      { $where .= " AND EXISTS (SELECT 1 FROM productos_terminados pt WHERE pt.orden_id = op.id AND pt.cantidad > 0)"; }
// Productos para filtro
$productos = q($pdo, "SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// Lotes
$lotes = q($pdo, "
  SELECT
    op.id AS orden_id,
    COALESCE(NULLIF(op.lote,''), CONCAT('L', DATE_FORMAT(op.fecha,'%Y%m%d'), '-', op.id)) AS lote,
    op.fecha,
    MAX(p.nombre) AS producto,
    MAX(p.densidad_kg_por_l) AS densidad
  FROM ordenes_produccion op
  LEFT JOIN productos_terminados pt ON pt.orden_id = op.id
  LEFT JOIN productos p             ON p.id       = pt.producto_id
  WHERE $where
  GROUP BY op.id, op.lote, op.fecha
  ORDER BY op.fecha DESC, op.id DESC
", $params)->fetchAll(PDO::FETCH_ASSOC);


// Exportar CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=costos_lote.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Fecha','Lote','Producto','Piezas','Costo MP','Insumos','Mano Obra','Indirectos','Costo Total','Costo por Pieza']);
  foreach ($lotes as $L) {
    $oid = (int)$L['orden_id'];

   /* Costo MP con trazabilidad y fallback a receta si no hubo consumos */
    $bd = mp_cost_breakdown($pdo, $oid);
    $costo_mp = (float)$bd['total'];
    if ($costo_mp <= 0.0000001) {
      $costo_mp = costo_mp_por_ficha($pdo, $oid);
    }

    $faltantes = q($pdo, "
      SELECT SUM(pc.cantidad_consumida * COALESCE(mp.precio_estimado, mp.precio_unitario, 0)) AS total
      FROM produccion_consumos pc
      JOIN materias_primas mp ON mp.id = pc.mp_id
      WHERE pc.produccion_id = ? AND pc.lote_recepcion IS NULL
    ", [$oid])->fetchColumn();

    $costo_mp = (float)$costo_mp + (float)$faltantes;

if (!$costo_mp || (float)$costo_mp <= 0.0000001) {
  $costo_mp = costo_mp_por_ficha($pdo, $oid);
}
    $costo_insumos = q($pdo, "
      SELECT SUM(ri.cantidad * COALESCE(ic.precio_unitario,0)) AS total
      FROM packaging_requests pr
      JOIN packaging_request_items ri ON ri.request_id = pr.id AND COALESCE(ri.aprobado,1)=1
      JOIN insumos_comerciales ic ON ic.id = ri.insumo_comercial_id
      WHERE pr.orden_id = ?
    ", [$oid])->fetchColumn();

    $mano_obra = $incluir_mo ? q($pdo, "
      SELECT SUM(COALESCE(c.costo_prorrateado,0)) FROM costeo_mano_obra c WHERE c.orden_id = ?
    ", [$oid])->fetchColumn() : 0;

    $indirectos = $incluir_ind ? q($pdo, "
      SELECT COALESCE(MAX(indirectos),0) FROM costos_lote WHERE orden_id = ?
    ", [$oid])->fetchColumn() : 0;

        // Piezas totales (para mostrar en la columna "Piezas")
        $piezas = q($pdo, "
          SELECT COALESCE(SUM(cantidad),0)
          FROM productos_terminados
          WHERE orden_id = ?
        ", [$oid])->fetchColumn();
        
        // Solo envases (presentaciones con volumen > 0) => base de división del costo unitario
        $piezas_env = q($pdo, "
          SELECT COALESCE(SUM(pt.cantidad),0)
          FROM productos_terminados pt
          LEFT JOIN presentaciones pr ON pr.id = pt.presentacion_id
          WHERE pt.orden_id = ? AND COALESCE(pr.volumen_ml,0) > 0
        ", [$oid])->fetchColumn();


    // Piezas totales (para mostrar en CSV)
    $piezas = q($pdo, "
      SELECT COALESCE(SUM(cantidad),0)
      FROM productos_terminados
      WHERE orden_id = ?
    ", [$oid])->fetchColumn();

    // Solo envases (presentaciones con volumen > 0): base para Costo / Pieza
    $piezas_env = q($pdo, "
      SELECT COALESCE(SUM(pt.cantidad),0)
      FROM productos_terminados pt
      LEFT JOIN presentaciones pr ON pr.id = pt.presentacion_id
      WHERE pt.orden_id = ? AND COALESCE(pr.volumen_ml,0) > 0
    ", [$oid])->fetchColumn();

    $total = (float)$costo_mp + (float)$costo_insumos + (float)$mano_obra + (float)$indirectos;
    $cpp = ($piezas_env > 0) ? $total / (float)$piezas_env : 0;


    fputcsv($out, [
      $L['fecha'], $L['lote'], $L['producto'],
      (int)$piezas, number_format((float)$costo_mp,2,'.',''), number_format((float)$costo_insumos,2,'.',''),
      number_format((float)$mano_obra,2,'.',''), number_format((float)$indirectos,2,'.',''),
      number_format((float)$total,2,'.',''), number_format((float)$cpp,4,'.','')
    ]);
  }
  exit;
}
include 'header.php';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte: Costo por Lote (costo unitario por pieza)</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body{ padding:16px; }
  .table thead th{ position:sticky; top:0; background:#fff; }
  .badge-slug{ font-family:monospace; }
</style>
</head>
<body>
  <h1 class="h3 mb-3">Reporte: Costo por Lote <small class="text-muted">(unitario por pieza)</small></h1>

  <form class="row g-2 mb-3" method="get">
    <div class="col-auto">
      <label class="form-label">Desde</label>
      <input type="date" name="desde" value="<?= htmlspecialchars($fecha_desde) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <label class="form-label">Hasta</label>
      <input type="date" name="hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
      <label class="form-label">Producto</label>
      <select name="producto_id" class="form-select form-select-sm">
        <option value="0">Todos</option>
        <?php foreach ($productos as $pr): ?>
          <option value="<?= (int)$pr['id'] ?>" <?= $producto_id==(int)$pr['id']?'selected':'' ?>>
            <?= htmlspecialchars($pr['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label">Lote</label>
      <input type="text" name="lote" value="<?= htmlspecialchars($lote_like) ?>" class="form-control form-control-sm" placeholder="opcional">
    </div>
    <div class="col-auto form-check mt-4">
      <input class="form-check-input" type="checkbox" name="envasado" value="1" id="envasado" <?= $solo_envasado ? 'checked' : '' ?>>
      <label class="form-check-label" for="envasado">Solo lotes envasados</label>
    </div>
    <div class="col-auto form-check mt-4">
      <input class="form-check-input" type="checkbox" name="mo" value="1" id="mo" <?= $incluir_mo ? 'checked' : '' ?>>
      <label class="form-check-label" for="mo">Incluir Mano de Obra</label>
    </div>
    <div class="col-auto form-check mt-4">
      <input class="form-check-input" type="checkbox" name="ind" value="1" id="ind" <?= $incluir_ind ? 'checked' : '' ?>>
      <label class="form-check-label" for="ind">Incluir Indirectos</label>
    </div>
    <div class="col-auto mt-4">
      <button class="btn btn-sm btn-primary">Filtrar</button>
      <a class="btn btn-sm btn-outline-secondary" href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>">Exportar CSV</a>
    </div>
  </form>

  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Lote</th>
          <th>Producto</th>
          <th class="text-end">Piezas</th>
          <th class="text-end">Costo MP</th>
          <th class="text-end">Insumos</th>
          <th class="text-end">Mano Obra</th>
          <th class="text-end">Indirectos</th>
          <th class="text-end">Total Lote</th>
          <th class="text-end">Costo / Pieza</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lotes as $L): 
          $oid = (int)$L['orden_id'];
          
    /* Costo MP consolidado con trázabilidad de precio */
    $bd = mp_cost_breakdown($pdo, $oid);
    $costo_mp = (float)$bd['total'];
    /* Si no hubo consumos, costear por receta */
    if ($costo_mp <= 0.0000001) {
      $costo_mp = costo_mp_por_ficha($pdo, $oid);
     $bd = ['rows'=>[], 'total'=>$costo_mp]; // para consistencia
    }

          $costo_insumos = q($pdo, "
            SELECT SUM(ri.cantidad * COALESCE(ic.precio_unitario,0)) AS total
            FROM packaging_requests pr
            JOIN packaging_request_items ri ON ri.request_id = pr.id AND COALESCE(ri.aprobado,1)=1
            JOIN insumos_comerciales ic ON ic.id = ri.insumo_comercial_id
            WHERE pr.orden_id = ?
          ", [$oid])->fetchColumn();

          $mano_obra = $incluir_mo ? q($pdo, "
            SELECT SUM(COALESCE(c.costo_prorrateado,0)) FROM costeo_mano_obra c WHERE c.orden_id = ?
          ", [$oid])->fetchColumn() : 0;

          $indirectos = $incluir_ind ? q($pdo, "
            SELECT COALESCE(MAX(indirectos),0) FROM costos_lote WHERE orden_id = ?
          ", [$oid])->fetchColumn() : 0;

          // Piezas totales (columna "Piezas")
          $piezas = q($pdo, "
            SELECT COALESCE(SUM(cantidad),0)
            FROM productos_terminados
            WHERE orden_id = ?
          ", [$oid])->fetchColumn();

          // Piezas envasadas (presentaciones con volumen > 0) -> base para Costo / Pieza
          $piezas_env = q($pdo, "
            SELECT COALESCE(SUM(pt.cantidad),0)
            FROM productos_terminados pt
            LEFT JOIN presentaciones pr ON pr.id = pt.presentacion_id
            WHERE pt.orden_id = ? AND COALESCE(pr.volumen_ml,0) > 0
          ", [$oid])->fetchColumn();

          $total = (float)$costo_mp + (float)$costo_insumos + (float)$mano_obra + (float)$indirectos;
          $cpp   = ($piezas_env > 0) ? $total / (float)$piezas_env : 0;

          // Detalle por presentación con volumen_ml para distinguir envases
          $det = q($pdo, "
            SELECT pr.id, pr.nombre AS presentacion, COALESCE(pr.volumen_ml,0) AS volumen_ml,
                   SUM(pt.cantidad) AS piezas
            FROM productos_terminados pt
            LEFT JOIN presentaciones pr ON pr.id = pt.presentacion_id
            WHERE pt.orden_id = ?
            GROUP BY pr.id, pr.nombre, pr.volumen_ml
            ORDER BY pr.volumen_ml, pr.nombre
          ", [$oid])->fetchAll(PDO::FETCH_ASSOC);

          // === Repartos (sin doble contabilidad de gramos) ===
          // Densidad del producto: kg/L → g/ml (1 L = 1000 ml, por lo tanto g/ml = densidad_kg_por_l)
          $DENSIDAD = max((float)($L['densidad'] ?? 0), 0.0001);

          $grams_from_pt   = 0.0; // “Gramos” (presentación sin volumen) → ya son gramos reales
          $grams_from_env  = 0.0; // gramos calculados desde envases (ml * densidad)
          $total_piezas_envase = 0;

          foreach ($det as $d) {
            $vol = (float)$d['volumen_ml'];   // ml (0 para “Gramos”)
            $pcs = (float)$d['piezas'];
            if ($vol > 0) {
              $total_piezas_envase += $pcs;
              $grams_from_env += $pcs * $vol * $DENSIDAD;     // ml * (g/ml)
            } else {
              $grams_from_pt  += $pcs;                         // aquí “piezas” ya son gramos
            }
          }

          // Total de gramos: si hay fila “Gramos”, usamos esa; si no, los calculados desde envases.
          $total_grams = ($grams_from_pt > 0) ? $grams_from_pt : $grams_from_env;

          // Costos por gramo para MP / MO / Indirectos (sin incluir empaque)
          $mp_por_g   = $total_grams > 0 ? ((float)$costo_mp) / $total_grams : 0.0;
          $mo_por_g   = ($total_grams > 0 && $incluir_mo)  ? ((float)$mano_obra) / $total_grams : 0.0;
          $ind_por_g  = ($total_grams > 0 && $incluir_ind) ? ((float)$indirectos) / $total_grams : 0.0;
          $costo_gr   = $mp_por_g + $mo_por_g + $ind_por_g;

          // Empaque por pieza (solo para envases)
          $pack_por_pieza_env = $total_piezas_envase > 0 ? ((float)$costo_insumos) / $total_piezas_envase : 0.0;
          ?>
          <tr>
            <td><?= htmlspecialchars($L['fecha']) ?></td>
            <td><span class="badge bg-light text-dark border badge-slug"><?= htmlspecialchars($L['lote']) ?></span></td>
            <td><?= htmlspecialchars($L['producto']) ?></td>
            <td class="text-end"><?= (int)$piezas ?></td>
            <td class="text-end">$<?= val($costo_mp) ?></td>
            <td class="text-end">$<?= val($costo_insumos) ?></td>
            <td class="text-end">$<?= val($mano_obra) ?></td>
            <td class="text-end">$<?= val($indirectos) ?></td>
            <td class="text-end fw-semibold">$<?= val($total) ?></td>
            <td class="text-end fw-bold">$<?= val($cpp,4) ?></td>
            <td>
              <?php if ($det): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#d<?= $oid ?>">Detalle</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php if ($det): ?>
            <tr class="collapse" id="d<?= $oid ?>">
              <td colspan="11">
                <div class="p-2 bg-light rounded border">
                  <div class="small text-muted mb-1">Piezas por presentacion (costo unitario promedio del lote aplicado a cada pieza):</div>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <thead><tr><th>Presentacion</th><th class="text-end">Piezas</th><th class="text-end">Costo / Pieza</th></tr></thead>
                      <tbody>
                        <?php foreach ($det as $d):
                          $pcs = (int)$d['piezas'];
                          $vol = (float)$d['volumen_ml'];
                          // gramos de esta presentación
                          $g_pres = ($vol > 0) ? ($pcs * $vol * $DENSIDAD) : ($pcs * 1.0);
                          // costo por pieza: costo por gramo * gramos por pieza + (empaque si aplica)
                          $costo_por_pieza = ($vol > 0 ? ($vol * $DENSIDAD) : 1.0) * $costo_gr
                                             + ($vol > 0 ? $pack_por_pieza_env : 0.0);
                        ?>
                          <tr>
                            <td><?= htmlspecialchars($d['presentacion'] ?? 'N/D') ?></td>
                           <td class="text-end"><?= $pcs ?></td>
                            <td class="text-end">$<?= val($costo_por_pieza,4) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <?php if (!empty($bd['rows'])): ?>
                <hr class="my-3">
                <div class="small text-muted mb-1">Traza de Materias Primas (gramos, precio unitario MXN y origen):</div>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th>MP</th>
                        <th class="text-end">Gramos</th>
                        <th class="text-end">Unit (MXN/g)</th>
                        <th>Origen</th>
                        <th class="text-end">Subtotal</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($bd['rows'] as $r): ?>
                        <tr>
                          <td><?= htmlspecialchars($r['mp_nombre']) ?></td>
                          <td class="text-end"><?= number_format($r['gramos'], 2, '.', ',') ?></td>
                          <td class="text-end">$<?= number_format($r['unit_mxn'], 5, '.', ',') ?></td>
                          <td class="text-muted"><?= htmlspecialchars($r['fuente']) ?></td>
                          <td class="text-end">$<?= number_format($r['subtotal'], 2, '.', ',') ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php include 'footer.php'; ?>
