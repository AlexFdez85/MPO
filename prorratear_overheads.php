<?php
require_once 'config.php';
ini_set('display_errors',1); error_reporting(E_ALL);

function d($s){ return new DateTime($s); }
function days_between($a,$b){ return (int)d($a)->diff(d($b))->format('%a') + 1; }
function q(PDO $pdo,$sql,$p=[]){ $st=$pdo->prepare($sql); $st->execute($p); return $st; }

$periodo = $_GET['periodo'] ?? 'dia'; // dia|semana|mes
$desde   = $_GET['desde']   ?? date('Y-m-d');
$hasta   = $_GET['hasta']   ?? $desde;
$base    = $_GET['base']    ?? 'minutos'; // minutos|piezas|valor_mp
$inc_mo_ociosa = isset($_GET['incluir_mo_ociosa']) ? (bool)$_GET['incluir_mo_ociosa'] : false;

if ($periodo === 'semana') {
  $dt = d($desde); $dow = (int)$dt->format('N'); $desde = $dt->modify('-'.($dow-1).' days')->format('Y-m-d');
  $hasta = d($desde)->modify('+6 days')->format('Y-m-d');
} elseif ($periodo === 'mes') {
  $desde = d($desde)->modify('first day of this month')->format('Y-m-d');
  $hasta = d($desde)->modify('last day of this month')->format('Y-m-d');
}
$dias = days_between($desde,$hasta);

/* Órdenes en el periodo */
$ordenes = q($pdo,"SELECT id FROM ordenes_produccion WHERE fecha BETWEEN ? AND ?",[$desde,$hasta])->fetchAll(PDO::FETCH_COLUMN);
if (!$ordenes) exit("No hay órdenes entre $desde y $hasta\n");

/* Base de reparto */
$base_val = []; $total_base = 0.0;

if ($base === 'minutos') {
  $rows = q($pdo,"
    SELECT pm.orden_id, SUM(pm.min_prod + pm.min_setup) AS mins
    FROM produccion_mo pm
    WHERE pm.fecha BETWEEN ? AND ?
    GROUP BY pm.orden_id",[$desde,$hasta])->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) $base_val[$r['orden_id']] = (float)$r['mins'];
} elseif ($base === 'piezas') {
  $rows = q($pdo,"
    SELECT orden_id, SUM(cantidad) AS piezas
    FROM productos_terminados
    WHERE fecha BETWEEN ? AND ?
    GROUP BY orden_id",[$desde,$hasta])->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) $base_val[$r['orden_id']] = (float)$r['piezas'];
} else { // valor_mp
  $ids = implode(',', array_map('intval',$ordenes));
  $rows = q($pdo,"
    SELECT mpc.orden_id,
           (COALESCE(mpc.costo_mp_lote,0)+COALESCE(mpc.costo_mp_fallback,0)) AS valor
    FROM vw_costeo_mp mpc
    WHERE mpc.orden_id IN ($ids)")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) $base_val[$r['orden_id']] = (float)$r['valor'];
}
foreach ($ordenes as $oid) if (!isset($base_val[$oid])) $base_val[$oid]=0.0;
$total_base = array_sum($base_val);
if ($total_base <= 0) { foreach ($ordenes as $oid) $base_val[$oid]=1.0; $total_base=count($ordenes); }

/* Pools */
$pool_fuentes = [];

/* Nómina administrativa del periodo (diaria * días) */
$nom_admin_dia = q($pdo,"SELECT COALESCE(SUM(nomina_diaria_mxn),0) FROM usuarios WHERE tipo_usuario='administrativo' AND incluye_en_indirectos=1")->fetchColumn();
$pool_fuentes['ADMIN_NOMINA'] = round((float)$nom_admin_dia * $dias, 2);

/* Gastos fijos del periodo (prorrateo por vigencia y periodicidad) */
$gastos = q($pdo,"
  SELECT periodicidad, monto_mxn, vigente_desde, vigente_hasta
  FROM gastos_fijos
  WHERE activo=1
    AND (vigente_hasta IS NULL OR vigente_hasta >= ?)
    AND vigente_desde <= ?",[$desde,$hasta])->fetchAll(PDO::FETCH_ASSOC);

$acum_gastos = 0.0;
foreach ($gastos as $g) {
  $vig_ini = max(strtotime($g['vigente_desde']), strtotime($desde));
  $vig_fin = min(strtotime($g['vigente_hasta'] ?? $hasta), strtotime($hasta));
  if ($vig_fin < $vig_ini) continue;
  $dias_vig = floor(($vig_fin - $vig_ini)/86400) + 1;
  $m = (float)$g['monto_mxn'];
  switch ($g['periodicidad']) {
    case 'diario':  $acum_gastos += $m * $dias_vig; break;
    case 'semanal': $acum_gastos += $m * ($dias_vig/7.0); break;
    case 'mensual': $acum_gastos += $m * ($dias_vig/30.4375); break;
    case 'anual':   $acum_gastos += $m * ($dias_vig/365.0); break;
  }
}
$pool_fuentes['GASTOS_FIJOS'] = round($acum_gastos, 2);

/* (Opcional) MO ociosa operativa: nómina operativa - MO directa capturada */
if ($inc_mo_ociosa) {
  $nom_oper_dia = q($pdo,"SELECT COALESCE(SUM(nomina_diaria_mxn),0) FROM usuarios WHERE tipo_usuario='operativo' AND incluye_en_indirectos=1")->fetchColumn();
  $nom_oper_periodo = (float)$nom_oper_dia * $dias;
  $mo_directa = q($pdo,"
    SELECT COALESCE(SUM( ((pm.min_prod+pm.min_setup)/60.0) *
      COALESCE(pm.costo_hora_aplicado,(SELECT u.nomina_diaria_mxn/NULLIF(u.jornada_horas,0) FROM usuarios u WHERE u.id=pm.user_id))
    ),0)
    FROM produccion_mo pm
    WHERE pm.fecha BETWEEN ? AND ?",[$desde,$hasta])->fetchColumn();
  $ociosa = max(0.0, $nom_oper_periodo - (float)$mo_directa);
  $pool_fuentes['MO_OCIOSA'] = round($ociosa, 2);
}

/* Persistir pools y asignaciones */
$pdo->beginTransaction();
try {
  $insPool = $pdo->prepare("
    INSERT INTO costos_indirectos_periodo (periodo_inicio, periodo_fin, fuente, monto_mxn)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE monto_mxn=VALUES(monto_mxn)
  ");
  $insAsig = $pdo->prepare("
    INSERT INTO costos_indirectos_asignados (periodo_inicio, periodo_fin, orden_id, fuente, monto_mxn)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE monto_mxn=VALUES(monto_mxn)
  ");

  foreach ($pool_fuentes as $fuente=>$monto_total) {
    $insPool->execute([$desde,$hasta,$fuente,$monto_total]);
    if ($monto_total<=0) continue;
    foreach ($ordenes as $oid) {
      $prop = $base_val[$oid] / $total_base;
      $monto = round($monto_total * $prop, 2);
      $insAsig->execute([$desde,$hasta,$oid,$fuente,$monto]);
    }
  }

  $pdo->commit();
  echo "OK: pools del $desde al $hasta asignados con base '$base'.\n";
  foreach ($pool_fuentes as $k=>$v) echo "- $k: $v MXN\n";
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo "Error: ".$e->getMessage()."\n";
}
