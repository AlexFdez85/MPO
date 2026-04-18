<?php
// ejecutar_produccion.php

// DEBUG temporal (deja esto mientras depuras)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== FIFO / Trazabilidad por lotes ===== Activa registro en produccion_consumos con el lote de recepción usado.
define('REGISTRAR_CONSUMOS', true);

// Consumir MP por FIFO desde recepciones_compra_lineas (cantidad_disponible),
// dejando traza en movimientos_mp y produccion_consumos (uno por lote).

function consumirFIFO_MP(PDO $pdo, int $mpId, float $cantidad, int $usuarioId, int $ordenId, string $loteProd, int $produccionId): void {
    // 1) Lock de la MP y validación de existencia global
    $stmt = $pdo->prepare("SELECT existencia FROM materias_primas WHERE id=? FOR UPDATE");
    $stmt->execute([$mpId]);
    $exist = (float)$stmt->fetchColumn();
    if ($exist < $cantidad) throw new Exception("Stock insuficiente para MP #{$mpId} (requiere {$cantidad} g, hay {$exist} g).");

    // 2) Seleccionar lotes con disponible (FIFO) y bloquearlos
    $q = $pdo->prepare("
      SELECT rcl.id, rcl.cantidad_disponible
        FROM recepciones_compra_lineas rcl
        JOIN lineas_compra lc ON lc.id = rcl.linea_id
       WHERE lc.mp_id = ?
         AND rcl.cantidad_disponible > 0
       ORDER BY rcl.fecha_ingreso ASC, rcl.id ASC
       FOR UPDATE
    ");
    $q->execute([$mpId]);

    $rest = $cantidad;
    while ($rest > 0 && ($lot = $q->fetch(PDO::FETCH_ASSOC))) {
        $dispo = (float)$lot['cantidad_disponible'];
        if ($dispo <= 0) continue;
        $take = ($rest <= $dispo) ? $rest : $dispo;

        // 3) Bajar disponible del lote
        $pdo->prepare("UPDATE recepciones_compra_lineas SET cantidad_disponible = cantidad_disponible - ? WHERE id = ?")
            ->execute([$take, (int)$lot['id']]);

        // 4) Movimiento de salida por este tramo
        $coment = sprintf("Consumo OP #%s, lote prod %s, lote recep #%s", $ordenId, $loteProd, $lot['id']);
        insertMovimiento($pdo, $mpId, 'salida', $take, $usuarioId, $coment, null);

        // 5) Traza por lote en produccion_consumos (cantidad del tramo y lote de recepción usado)
        if (REGISTRAR_CONSUMOS) {
            // produccionId = PT positivo (lote final), mpId = materia prima, take = cantidad consumida de este lote,
            // lote_recepcion = id de recepciones_compra_lineas usado en FIFO
            insertProduccionConsumo($pdo, (int)$produccionId, (int)$mpId, (float)$take, (int)$lot['id']);
        }

        $rest -= $take;
    }
    if ($rest > 0.000001) {
        throw new Exception("Stock por lotes insuficiente para MP #{$mpId}; faltan {$rest} g.");
    }
    // 6) Finalmente, reflejar el total en existencia global de la MP
    $pdo->prepare("UPDATE materias_primas SET existencia = existencia - ? WHERE id = ?")
        ->execute([$cantidad, $mpId]);
}

/**
 * Mapea el consumo de SUBPROCESOS contra sus lotes (+) de origen (PT) en FIFO
 * y LO REGISTRA en produccion_consumos_sp. No descuenta stock (eso ya lo hace el PT negativo).
 */
function mapearConsumoSubproceso(PDO $pdo, int $subproductoId, float $necesarioG, int $ptFinalId): void {
    if ($necesarioG <= 0) return;
    $presG = getPresentacionIdGramos($pdo);
    if (!$presG) return;

    // Lotes (+) disponibles del subproceso y cuánto ya se les asignó antes
    $q = $pdo->prepare("
        SELECT pt.id, pt.cantidad,
               COALESCE(SUM(spc.cantidad_g),0) AS asignado
          FROM productos_terminados pt
     LEFT JOIN produccion_consumos_sp spc ON spc.pt_origen_id = pt.id
         WHERE pt.producto_id = ?
           AND pt.presentacion_id = ?
           AND pt.cantidad > 0
      GROUP BY pt.id, pt.cantidad
      ORDER BY pt.fecha ASC, pt.id ASC
    ");
    $q->execute([$subproductoId, $presG]);

   $rest = (float)$necesarioG;
    while ($rest > 0 && ($row = $q->fetch(PDO::FETCH_ASSOC))) {
        $saldo = (float)$row['cantidad'] - (float)$row['asignado'];
        if ($saldo <= 0) continue;
        $take = ($rest <= $saldo) ? $rest : $saldo;

        $ins = $pdo->prepare("
          INSERT INTO produccion_consumos_sp
            (produccion_final_id, subproducto_id, pt_origen_id, cantidad_g)
          VALUES (?, ?, ?, ?)
        ");
        $ins->execute([$ptFinalId, $subproductoId, (int)$row['id'], $take]);
        $rest -= $take;
    }
    // Si quedara $rest > 0 no lanzamos excepción; el stock ya fue validado antes.
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
//require_once __DIR__.'/helpers_packaging.php';
require_once __DIR__.'/lib_packaging.php';
// ============================================================
// Helpers de presentaciones (defínelos SIEMPRE a nivel global)
// ============================================================

function q(PDO $pdo, string $sql, array $p=[]){ $st=$pdo->prepare($sql); $st->execute($p); return $st; }

function registrar_mo(PDO $pdo, int $orden_id, int $user_id, float $horas, string $fecha, ?string $actividad=null){
  $u = q($pdo,"SELECT nomina_diaria_mxn, jornada_horas FROM usuarios WHERE id=?",[$user_id])->fetch(PDO::FETCH_ASSOC);
  $rate = ($u && (float)$u['jornada_horas']>0) ? ((float)$u['nomina_diaria_mxn']/(float)$u['jornada_horas']) : 0.0;
  $costo = $horas * $rate;

    q($pdo,"INSERT INTO costeo_mano_obra(orden_id,usuario_id,fecha,horas,costo_hora,costo_prorrateado,actividad)
        VALUES(?,?,?,?,?,?,?)",
          [$orden_id,$user_id,$fecha,$horas,$rate,$costo,$actividad]);
}

// ¿Tiene el producto CUALQUIER presentación distinta a 'gramos'?
function tienePresentacionesVolumetricas(PDO $pdo, int $productoId): bool {
    $q = $pdo->prepare("
        SELECT COUNT(*) 
        FROM productos_presentaciones pp
        JOIN presentaciones pr ON pr.id = pp.presentacion_id
        WHERE pp.producto_id = ? AND LOWER(pr.slug) <> 'gramos'
    ");
    $q->execute([$productoId]);
    return ((int)$q->fetchColumn()) > 0;
}

function getVolumetricFlags(PDO $pdo, int $productoId): array {
    $q = $pdo->prepare("
        SELECT LOWER(pr.slug) AS slug
          FROM productos_presentaciones pp
          JOIN presentaciones pr ON pr.id = pp.presentacion_id
         WHERE pp.producto_id = ?
    ");
    $q->execute([$productoId]);
    $slugs = array_column($q->fetchAll(PDO::FETCH_ASSOC), 'slug');
    return [
        'litros'  => in_array('litros',  $slugs, true),
        'galones' => in_array('galones', $slugs, true),
        'cubetas' => in_array('cubetas', $slugs, true),
    ];
}

// Verdadero si el producto NO tiene presentaciones volumétricas (solo gramos)
function esSoloGramos(PDO $pdo, int $productoId): bool {
    return !tienePresentacionesVolumetricas($pdo, $productoId);
}

if (!function_exists('calcularGramosProduccion')) {
  /** Convierte litros/galones/cubetas a gramos usando densidad (kg/L). */
  function calcularGramosProduccion(PDO $pdo, float $litros, float $galones, int $cubetas, float $densidadKgPorL): float {
      $litrosTot = $litros + ($galones * 3.78541) + ($cubetas * 18.925);
      $kg        = $litrosTot * max($densidadKgPorL, 0.0001);
      return $kg * 1000.0; // a gramos
  }
}

/* Declarar SIEMPRE, de forma independiente a la existencia de calcularGramosProduccion() */
if (!function_exists('calcularGramosDesdePlan')) {
  /** Convierte pres_qty[presentacion_id]=unidades → gramos usando volumen_ml y densidad. */
  function calcularGramosDesdePlan(PDO $pdo, array $presQty, float $densidadKgPorL): float {
      if (empty($presQty)) return 0.0;
      $ids = array_values(array_filter(array_map('intval', array_keys($presQty)), fn($v)=>$v>0));
      if (empty($ids)) return 0.0;
      $in  = implode(',', array_fill(0, count($ids), '?'));
      $st  = $pdo->prepare("SELECT id, volumen_ml FROM presentaciones WHERE id IN ($in)");
      $st->execute($ids);
      $volPorId = [];
      while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
          $volPorId[(int)$r['id']] = (float)$r['volumen_ml'];
      }
      $mlTotal = 0.0;
      foreach ($presQty as $pid => $u) {
          $u = (float)$u; if ($u <= 0) continue;
          $mlTotal += $u * (float)($volPorId[(int)$pid] ?? 0);
      }
      if ($mlTotal <= 0) return 0.0;
      $litrosTot = $mlTotal / 1000.0;
      return $litrosTot * max($densidadKgPorL, 0.0001) * 1000.0;
  }
}

function cerrar_indirectos_dia(PDO $pdo, string $fecha){
  // 1) Pool indirectos del día
  $pool = 0.0;

  // Administrativos (todo el día a indirectos)
  $pool += (float) q($pdo, "
    SELECT COALESCE(SUM(nomina_diaria_mxn),0)
    FROM usuarios
    WHERE incluye_en_indirectos=1 AND tipo_usuario='administrativo'
  ")->fetchColumn();

  // Operativos: jornada - horas asignadas a OP (de ese día)
  $ops = q($pdo, "
    SELECT u.id, u.nomina_diaria_mxn, u.jornada_horas
    FROM usuarios u
    WHERE u.incluye_en_indirectos=1 AND u.tipo_usuario='operativo'
  ")->fetchAll(PDO::FETCH_ASSOC);

  foreach ($ops as $u){
    $horas_asignadas = (float) q($pdo, "
      SELECT COALESCE(SUM(horas),0)
      FROM costeo_mano_obra
      WHERE usuario_id=? AND fecha=?", [$u['id'],$fecha])->fetchColumn();
    $restantes = max(0.0, ((float)$u['jornada_horas']) - $horas_asignadas);
    $rate = ((float)$u['jornada_horas']>0) ? ((float)$u['nomina_diaria_mxn']/(float)$u['jornada_horas']) : 0.0;
    $pool += $restantes * $rate;
  }

  if ($pool<=0) return;

  // 2) OP del día con "peso" en gramos (≈ pr.volumen_ml * cantidad; si no hay volumen, tomamos 1 g/pza)
  $ops_dia = q($pdo, "
    SELECT op.id AS orden_id,
           SUM( COALESCE(NULLIF(pr.volumen_ml,0),1) * pt.cantidad ) AS gramos
    FROM ordenes_produccion op
    JOIN productos_terminados pt ON pt.orden_id=op.id
    LEFT JOIN presentaciones pr   ON pr.id=pt.presentacion_id
    WHERE op.estado='completada' AND DATE(op.fecha)=?
    GROUP BY op.id
  ", [$fecha])->fetchAll(PDO::FETCH_ASSOC);

  $total_g = 0.0;
  foreach($ops_dia as $r){ $total_g += (float)$r['gramos']; }
  if ($total_g<=0) return;

  // 3) Escribir indirectos por OP (costos_lote.indirectos)
  foreach($ops_dia as $r){
    $share = $pool * ((float)$r['gramos'] / $total_g);
    q($pdo,"
      INSERT INTO costos_lote(orden_id, indirectos)
      VALUES(?, ?)
      ON DUPLICATE KEY UPDATE indirectos=VALUES(indirectos)
    ", [$r['orden_id'], $share]);
  }
}


// Helpers ya vienen desde config.php
// Resolvemos una vez el id de la presentación "gramos" para usarlo en todo el archivo
$presentacionGramosId = getPresentacionIdGramos($pdo);

// ——————————————————————————————
//  1) Validar que el usuario esté autenticado y tenga rol “produccion”, “gerente” o “admin”
// ——————————————————————————————
if (!isset($_SESSION['user_id']) 
    || !in_array($_SESSION['rol'], ['produccion','gerente','admin'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];
$nombre     = $_SESSION['nombre'];

// ——————————————————————————————
//  2) Procesar el formulario de “Registrar Producción” (cuando se envíe)
// ——————————————————————————————
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_produccion'])) {
    // Asegurar orden_id
    $orden_id = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;
    if ($orden_id <= 0) {
        http_response_code(400);
        die('Falta orden_id en el envío.');
    }
    $litros     = (float)($_POST['litros']   ?? 0);
    $galones    = (float)($_POST['galones']  ?? 0);
    $cubetas    = (int)  ($_POST['cubetas']  ?? 0);
    // NUEVO: plan dinámico por presentación (inputs tipo pres_qty[<presentacion_id>])
    $presQty    = (isset($_POST['pres_qty']) && is_array($_POST['pres_qty'])) ? $_POST['pres_qty'] : [];
    
    // Si el usuario usa la tabla de presentaciones, ignoramos legacy (L/Gal/Cub)
    $usaTabla = false;
    foreach ($presQty as $v) { if ((float)$v > 0) { $usaTabla = true; break; } }
    if ($usaTabla) { $litros = 0.0; $galones = 0.0; $cubetas = 0; }    
    
    // Si viene “gramos” desde el form, puede traer coma de miles: 16,800.00 → 16800.00
    // ¡OJO! el input visible puede estar disabled y NO viaja.
    // Enviamos un hidden "gramos_base" y lo usamos como respaldo si no hay presentaciones.
    $gramosBaseOp = isset($_POST['gramos_base']) ? (float)$_POST['gramos_base'] : 0.0;
    $gramosPost   = isset($_POST['gramos']) ? (float)$_POST['gramos'] : 0.0; // solo informativo


    $horaInicio = !empty($_POST['hora_inicio']) 
                  ? date('Y-m-d H:i:s', strtotime($_POST['hora_inicio'])) 
                  : null;
    $horaFin    = !empty($_POST['hora_fin']) 
                  ? date('Y-m-d H:i:s', strtotime($_POST['hora_fin'])) 
                  : null;
    $fecha_reg  = date('Y-m-d');

    // Generar un lote de producción (por ejemplo: L20250606-0012)
    $nuevoLote = 'L' . date('Ymd') . '-' . str_pad($orden_id, 4, '0', STR_PAD_LEFT);

    // Recuperar producto, ficha y metadatos (unidad_produccion, unidad_venta, densidad)
    $getProdStmt = $pdo->prepare("
        SELECT 
          op.ficha_id,
          fp.producto_id, fp.unidad_produccion,
          p.unidad_venta, COALESCE(p.densidad_kg_por_l, 1) AS densidad_kg_por_l
          FROM ordenes_produccion op
        JOIN fichas_produccion fp ON op.ficha_id = fp.id
        JOIN productos p          ON p.id = fp.producto_id
        WHERE op.id = ?
    ");
    $getProdStmt->execute([$orden_id]);
    $filaProd = $getProdStmt->fetch(PDO::FETCH_ASSOC);
    if (!$filaProd) {
        die("Error: la orden <strong>#{$orden_id}</strong> no se encontró en la base de datos.");
    }
    $producto_id = intval($filaProd['producto_id']);
    $unidad_produccion = $filaProd['unidad_produccion'] ?? null;
    $unidad_venta      = $filaProd['unidad_venta'] ?? null;
    $densidad          = (float)$filaProd['densidad_kg_por_l'];
     // ¿Este producto tiene presentaciones volumétricas?
     $soloGramos = esSoloGramos($pdo, $producto_id);
     $tieneVolumetricas = !$soloGramos;  // lo usas más abajo en el bloque de packaging
    
    // 2) Fallback: si no hay presentaciones volumétricas, tomar “gramos” del POST (órdenes en g/kg)
    // 3) Resolver IDs de presentaciones por slug (para packaging y PT)
    $findPresBySlug = $pdo->prepare("SELECT id FROM presentaciones WHERE slug = ?");
    $presLitrosId = $presGalonesId = $presCubetasId = null;
    foreach (['litros','galones','cubetas'] as $slug) {
        $findPresBySlug->execute([$slug]);
        ${'pres'.ucfirst($slug).'Id'} = (int)($findPresBySlug->fetchColumn() ?: 0);
    }
    // NUEVO: unificar captura para evitar duplicados (pres_qty manda sobre legacy)
    $plan = [];
    
    // normalizar pres_qty (tabla dinámica)
    if (!empty($presQty)) {
        foreach ($presQty as $pid => $u) {
            $u = (float)$u;
            if ($u > 0) $plan[(int)$pid] = $u; // prioridad a la tabla
        }
    }
    
    // agregar legacy SOLO si esa presentación no viene en la tabla
    if ($litros  > 0 && $presLitrosId  && !isset($plan[$presLitrosId]))  $plan[$presLitrosId]  = (float)$litros;
    if ($galones > 0 && $presGalonesId && !isset($plan[$presGalonesId])) $plan[$presGalonesId] = (float)$galones;
    if ($cubetas > 0 && $presCubetasId && !isset($plan[$presCubetasId])) $plan[$presCubetasId] = (int)$cubetas;
    
    // gramos canónicos ÚNICAMENTE desde el plan unificado
    $gramos = calcularGramosDesdePlan($pdo, $plan, $densidad);
    
    // para productos “solo gramos”
    if ($soloGramos) {
        $gramos = (float)($_POST['gramos'] ?? 0.0);
        if ($gramos <= 0) $gramos = $gramosBaseOp;
    }
    
    if ($gramos <= 0 && empty($plan)) {
        throw new Exception("Debes capturar al menos una cantidad (>0).");
}


    // Preparar consulta para insertar en productos_terminados
    $insertPTStmt   = $pdo->prepare("
        INSERT INTO productos_terminados
        (orden_id, producto_id, presentacion_id, cantidad, lote_produccion, fecha)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    try {
        $pdo->beginTransaction();

        // — Insertar PT por presentaciones (plan unificado, SIN duplicar legacy)
        if (!empty($plan)) {
            $insPTp = $pdo->prepare("
                INSERT INTO productos_terminados
                  (orden_id, producto_id, presentacion_id, cantidad, lote_produccion, fecha)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($plan as $pid => $u) {
                $insPTp->execute([$orden_id, $producto_id, (int)$pid, (float)$u, $nuevoLote, $fecha_reg]);
            }
        }

        // — Insertar gramos producidos (si > 0) —
        $produccion_id = 0;
        if ($gramos > 0) {
            $presentacionGramos = $presentacionGramosId ?: getPresentacionIdGramos($pdo);
            if (!$presentacionGramos) {
                throw new Exception("No se pudo resolver el id de presentación para 'gramos'.");
            }
            $insertPTStmt->execute([
                $orden_id,
                $producto_id,
                $presentacionGramos,
                $gramos,
                $nuevoLote,
                $fecha_reg
            ]);
            $produccion_id = (int)$pdo->lastInsertId();
        }
        
        // Resolver SIEMPRE el ID del PT POSITIVO (amarre de consumos)
        if ($produccion_id <= 0) {
            $stmtUltPT = $pdo->prepare("
                SELECT id
                  FROM productos_terminados
                 WHERE orden_id = ?
                   AND lote_produccion = ?
                   AND cantidad > 0
              ORDER BY id DESC
                 LIMIT 1
            ");
            $stmtUltPT->execute([$orden_id, $nuevoLote]);
            $produccion_id = (int)$stmtUltPT->fetchColumn();
        }
        if ($produccion_id <= 0) {
            throw new Exception("No se generó ningún Producto Terminado (>0) para OP #{$orden_id}, lote {$nuevoLote}. Verifica cantidades.");
        }          
        
        $updateOrden = $pdo->prepare("
      UPDATE ordenes_produccion
        SET estado        = 'completada',
            fecha_inicio  = ?,
            hora_inicio   = ?,
            hora_fin      = ?
      WHERE id = ?
    ");
    $updateOrden->execute([
      $fecha_reg,
      $horaInicio,
      $horaFin,
      $orden_id
    ]);
    // === Mano de Obra directa del operario (según horas capturadas) ===
    // Calcula horas desde hora_inicio / hora_fin y registra costo-hora prorrateado
    $horas_usadas = 0.0;
    if (!empty($horaInicio) && !empty($horaFin)) {
      $horas_usadas = max(0, (strtotime($horaFin) - strtotime($horaInicio)) / 3600);
    }
    if ($horas_usadas > 0) {
      // $usuario_id ya está en sesión; registra MO para esta OP y fecha
      registrar_mo(
        $pdo,
        (int)$orden_id,
        (int)$usuario_id,
        (float)$horas_usadas,
        $fecha_reg,
        'Producción'
     );
    }

// ——————————————————————————————
//  5.x) Recuperar receta de MP/Subproceso para esta ficha
// ——————————————————————————————
$stmtF = $pdo->prepare("
  SELECT mp_id, porcentaje_o_gramos
    FROM ficha_mp
   WHERE ficha_id = ?
");
$stmtF->execute([$filaProd['ficha_id']]);
$receta = $stmtF->fetchAll(PDO::FETCH_ASSOC);

// Calcular total de gramos_formula
$totalFormula = array_sum(array_column($receta,'porcentaje_o_gramos'));

// Preparar statements (con bloqueos y trazabilidad)
$lockMP = $pdo->prepare("SELECT existencia FROM materias_primas WHERE id = ? FOR UPDATE");
$updMP  = $pdo->prepare("UPDATE materias_primas SET existencia = existencia - ? WHERE id = ?");
$insSub = $pdo->prepare("
  INSERT INTO productos_terminados
    (orden_id, producto_id, presentacion_id, cantidad, lote_produccion, fecha)
  VALUES (?, ?, ?, ?, ?, ?)
");

// 5.y) Descontar/inyectar cada ingrediente
foreach ($receta as $i) {
    $prop  = floatval($i['porcentaje_o_gramos']) / $totalFormula;
    $neces = $prop * $gramos; // gramos realmente usados (canónicos)

    if ($i['mp_id'] <= 100000) {
        // Materia prima: consumir por FIFU desde recepciones_compra_lineas
        consumirFIFO_MP(
          $pdo,
          (int)$i['mp_id'],
          (float)$neces,
          (int)$usuario_id,
          (int)$orden_id,
          $nuevoLote,
          (int)$produccion_id
        );
    } else {
        // Subproceso:
        // 1) PT negativo (afecta stock de subproceso en gramos)
        $subId = $i['mp_id'] - 100000;
        if ($neces > 0) {
          $insSub->execute([
              $orden_id,
              $subId,
              $presentacionGramosId,
              -$neces,
              $nuevoLote,
              $fecha_reg
          ]);
        }
        // 2) Mapeo FIFO contra los lotes (+) de origen del subproceso (trazabilidad)
        mapearConsumoSubproceso($pdo, $subId, (float)$neces, (int)$produccion_id);
        
    }
}

// — Packaging DENTRO de la misma transacción (solo desde $plan unificado)
if (!$soloGramos) {
    $producciones = [];
    foreach ($plan as $pid => $u) {
        $qty = (int)round((float)$u);
        if ($qty > 0) $producciones[] = ['presId' => (int)$pid, 'qty' => $qty];
    }
    if (!empty($producciones)) {
        // Usuario consistente
        $requestId = packaging_create_request($pdo, (int)$orden_id, (int)$usuario_id);

        // Limpiar por reintentos
        $pdo->prepare("DELETE FROM packaging_request_items WHERE request_id = ?")
            ->execute([$requestId]);

        $selKit = $pdo->prepare("
            SELECT insumo_comercial_id, cantidad
              FROM packaging_kits
             WHERE producto_id = ? AND presentacion_id = ?
        ");
    $insIt = $pdo->prepare("
        INSERT INTO packaging_request_items
          (request_id, insumo_comercial_id, cantidad, cantidad_solicitada, cantidad_autorizada, aprobado)
        VALUES (?, ?, ?, ?, NULL, 1)
        ON DUPLICATE KEY UPDATE
          cantidad = cantidad + VALUES(cantidad),
          cantidad_solicitada = cantidad_solicitada + VALUES(cantidad_solicitada),
          aprobado = VALUES(aprobado)
    ");

        $insertados = 0;
        foreach ($producciones as $p) {
            if ($p['presId'] <= 0 || $p['qty'] <= 0) continue;

            $selKit->execute([(int)$producto_id, (int)$p['presId']]);
            while ($r = $selKit->fetch(PDO::FETCH_ASSOC)) {
                $cantBase = (float)$r['cantidad'];   // por unidad
                $cantTot  = $cantBase * (int)$p['qty'];
            if ($cantTot > 0) {
                // Insertar o acumular si ya existe el mismo insumo en la misma solicitud
                $insIt->execute([$requestId, (int)$r['insumo_comercial_id'], $cantTot, $cantTot]);
                $insertados++;
            }
            }
        }

       // Si por alguna razón no se insertó nada, borramos la solicitud “vacía”
        if ($insertados === 0) {
            $pdo->prepare("DELETE FROM packaging_requests WHERE id = ?")->execute([$requestId]);
        }
    }
}
        // ——————————————————————————————
        //  5.z) finalmente commit y redirect (DENTRO del try)
        // ——————————————————————————————
        $pdo->commit();
        // Recalcular y prorratear indirectos del día con la MO recién registrada
        // (idempotente gracias a ON DUPLICATE KEY en costos_lote)
        cerrar_indirectos_dia($pdo, $fecha_reg);
        
        header("Location: ejecutar_produccion.php?ok=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al registrar producción: " . $e->getMessage());
    }
}


// ——————————————————————————————
//  3) Contar cuántas órdenes autorizadas y pendientes existen
// ——————————————————————————————
$countStmt = $pdo->query("
    SELECT COUNT(*) 
    FROM ordenes_produccion
    WHERE estado_autorizacion = 'autorizada'
      AND estado = 'pendiente'
      AND (cancelada = 0 OR cancelada IS NULL)
");
$totalPendientes = intval($countStmt->fetchColumn());

// ——————————————————————————————
//  4) Obtener TODAS las órdenes pendientes (ahora también con “cantidad” y “unidad”)
// ——————————————————————————————

$ordenesStmt = $pdo->query("
    SELECT 
      op.id                    AS orden_id,
      op.ficha_id              AS ficha_id,
      p.id                     AS producto_id,
      p.nombre                 AS nombre_producto,
      op.cantidad_a_producir   AS cantidad_orden,
      op.unidad                AS unidad_orden,
      fp.lote_minimo           AS lote_minimo_ficha,
      p.densidad_kg_por_l,
      fp.unidad_produccion     AS unidad_lote_ficha,
      fp.instrucciones         AS instrucciones
    FROM ordenes_produccion op
    JOIN fichas_produccion fp ON op.ficha_id = fp.id
    JOIN productos p           ON fp.producto_id = p.id
    WHERE op.estado_autorizacion = 'autorizada'
      AND op.estado             = 'pendiente'
      AND op.cancelada          = 0
    ORDER BY op.id ASC
");

$ordenesPendientes = $ordenesStmt->fetchAll(PDO::FETCH_ASSOC);

// ——————————————————————————————
//  5) Preparar la información de stock-resumen (rojo/amarillo/verde)
//     Solo consideramos las MP “involucradas” en las órdenes pendientes
// ——————————————————————————————
// 5.1) Traer todas las fichas distintas de los órdenes pendientes
$listaFichas = [];
foreach ($ordenesPendientes as $o) {
    $listaFichas[] = intval($o['ficha_id']);
}
$listaFichas = array_unique($listaFichas);
if (count($listaFichas) === 0) {
    // No hay ordenes → no mostramos nada de stock
    $mpResumen = [];
} else {
    // Para cada ficha pendiente, traemos la receta base de MP (en gramos):
    // Esto nos da por cada registro: ficha_id, mp_id, gramos_formula
    $inClause  = implode(',', $listaFichas);
    $stmtFormulas = $pdo->query("
        SELECT 
          fmp.ficha_id,
          fmp.mp_id,
          fmp.porcentaje_o_gramos AS gramos_formula
        FROM ficha_mp fmp
        WHERE fmp.ficha_id IN ({$inClause})
    ");
    $formulas = $stmtFormulas->fetchAll(PDO::FETCH_ASSOC);

    // 5.2) Reagrupamos por mp_id para sumarle “GRAMOS por orden” * número de órdenes que usan esa ficha
    $usoPorMp = []; // mp_id => acumulado de gramos totales necesarios EN TODAS las órdenes
    foreach ($ordenesPendientes as $o) {
        // Convertir “cantidad pedida” de la orden a gramos
        $rawCantidad = floatval($o['cantidad_orden']);
        $unidadOrden = trim($o['unidad_orden']);
        if ($unidadOrden === 'kg') {
            $cantidadEnGramos = $rawCantidad * 1000.0;
        } else {
            $cantidadEnGramos = $rawCantidad;
        }



        // Calcular sumatoria total de “gramos_formula” de esa ficha para dimensionar porcentajes
        $totalFormulaPorFicha = 0;
        foreach ($formulas as $f) {
            if ($f['ficha_id'] == $o['ficha_id']) {
                $totalFormulaPorFicha += floatval($f['gramos_formula']);
            }
        }
        if ($totalFormulaPorFicha <= 0) {
            continue;
        }

        // Recorremos todos los insumos de esa misma ficha:
        foreach ($formulas as $f) {
            if ($f['ficha_id'] != $o['ficha_id']) continue;
            $mp_id         = intval($f['mp_id']);
            $gramosFormula = floatval($f['gramos_formula']);
            // Porcentaje dentro de la receta base:
            $porc = ($gramosFormula / $totalFormulaPorFicha);
            // Gramos que esta orden necesita de ese MP:
            $gramosNecesarios = $porc * $cantidadEnGramos;

            if (!isset($usoPorMp[$mp_id])) {
                $usoPorMp[$mp_id] = 0.0;
            }
            // Acumulamos: cada orden suma sus gramosNecesarios al mp_id
            $usoPorMp[$mp_id] += $gramosNecesarios;
        }
    }

    // 5.3) Obtener stock actual (en gramos) para MP y Subprocesos por separado
    $stocks = [];
    if (!empty($usoPorMp)) {
        // Normalizamos IDs para evitar IN () o valores no válidos
        $idsAll = array_unique(array_map('intval', array_keys($usoPorMp)));
        $idsMp  = array_values(array_filter($idsAll, function($id){ return ($id > 0 && $id <= 100000); }));
        $idsSp  = array_values(array_filter($idsAll, function($id){ return ($id > 100000); }));
 

        // a) Materias primas: stock directo en materias_primas.existencia
        if (!empty($idsMp)) {
            $inMp = implode(',', $idsMp);
            $q = $pdo->query("
                SELECT id AS mp_id, nombre, existencia AS stock_actual
                FROM materias_primas
                WHERE id IN ({$inMp})
            ");
            $stocks = array_merge($stocks, $q->fetchAll(PDO::FETCH_ASSOC));
        }

         // b) Subprocesos: (mp_id = 100000 + producto_id) → stock en gramos desde productos_terminados

        if (!empty($idsSp)) {
            // Asegurar id de 'gramos'
            if (empty($presentacionGramosId)) {
                $presentacionGramosId = getPresentacionIdGramos($pdo);
            }
            // Mapear mp_id → producto_id y filtrar > 0

            $prodIds = array_values(array_filter(
                array_map(function($mid){ return (int)$mid - 100000; }, $idsSp),
                function($pid){ return $pid > 0; }
            ));
            if (!empty($prodIds)) {
                $inProd = implode(',', $prodIds);
                $sqlSp = "
                    SELECT 
                      (100000 + p.id) AS mp_id,
                      p.nombre,
                      COALESCE(SUM(CASE 
                          WHEN pt.presentacion_id = :pres_gramos THEN pt.cantidad 
                          ELSE 0 END), 0) AS stock_actual
                    FROM productos p
                    LEFT JOIN productos_terminados pt 
                           ON pt.producto_id = p.id
                    WHERE p.id IN ($inProd)
                    GROUP BY p.id, p.nombre
                ";
                $st = $pdo->prepare($sqlSp);
               $st->execute([':pres_gramos' => $presentacionGramosId]);
                $stocks = array_merge($stocks, $st->fetchAll(PDO::FETCH_ASSOC));
            }
        }
    }

    // 5.4) Construimos el resumen para cada mp_id:
    //      - “NecesarioTotal” = $usoPorMp[mp_id]  
    //      - “StockActual”   = de la consulta $stocks  
    //      - “ÓrdenesPendientes” = count($ordenesPendientes)  (todas requieren el mismo insumo)
    //      - Estado:
    //          * Rojo    = si StockActual < (gramosNecesarios de UNA sola orden)  
    //          * Amarillo= si cubre al menos 1 orden pero no a TODAS  
    //          * Verde   = si cubre TODAS las órdenes pendientes
    //
    //    Para saber “gramosNecesarios de UNA sola orden” calculamos (gramosNecesariosTotales ÷ #órdenes)
    //    porque asumimos que cada orden usa la misma proporción de esa MP.
    //
    $mpResumen = [];
    $numOrdenes = count($ordenesPendientes);

    // Volcamos stock a un array asociativo mp_id → (nombre, existencia)
    $arrStocks = [];
    foreach ($stocks as $s) {
        $arrStocks[intval($s['mp_id'])] = [
            'nombre'     => $s['nombre'],
            'existencia' => floatval($s['stock_actual']),
        ];
    }

    foreach ($usoPorMp as $mp_id => $totalNecesario) {
        $nombreMp     = $arrStocks[$mp_id]['nombre'] ?? '–';
        $stockActual  = $arrStocks[$mp_id]['existencia'] ?? 0.0;
        // Gramos necesarios POR CADA orden: (totalNecesario ÷ numOrdenes)
        $gramosPorOrden = $totalNecesario / $numOrdenes;

        // Ahora determinamos el color/estado:
        if ($stockActual < $gramosPorOrden) {
            // Rojo: No alcanza ni para 1 orden
            $estado = 'rojo';
            $etiqueta = "No alcanza para ninguna orden";
        }
        elseif ($stockActual < $totalNecesario) {
            // Amarillo: Alcanza para “algunas” órdenes, pero no para TODAS
            $estado = 'amarillo';
            // Cantidad de órdenes que sí puede cubrir:
            $puedeCubrir = floor($stockActual / $gramosPorOrden);
            $etiqueta = "Alcanza para {$puedeCubrir} de {$numOrdenes} orden(es)";
        }
        else {
            // Verde: Alcanza para todas las órdenes pendientes
            $estado = 'verde';
            $etiqueta = "Alcanza para todas las ordenes";
        }

        $mpResumen[] = [
            'mp_id'         => $mp_id,
            'nombre'        => $nombreMp,
            'stock_actual'  => $stockActual,
            'estado'        => $estado,
            'etiqueta'      => $etiqueta,
        ];
    }
}
?>

<?php include 'header.php'; ?>

<div class="container mt-4">
  <h3 class="text-danger mb-3">Ejecución de Producción</h3>

  <!-- ——————————————————————————————
       Alerta con total de órdenes pendientes
       —————————————————————————————— -->
  <?php if ($totalPendientes > 0): ?>
    <div class="alert alert-info">
      Tienes <strong><?= $totalPendientes ?></strong> órdenes pendientes por ejecutar.
    </div>
  <?php else: ?>
    <div class="alert alert-secondary">
      No hay órdenes pendientes de producción en este momento.
    </div>
  <?php endif; ?>


  <!-- ——————————————————————————————
       Estado de Stock de Materias Primas (resumido en máximo 3 líneas)
       —————————————————————————————— -->
  <?php if (!empty($mpResumen)): ?>
    <?php
      // Agrupar los MP según su estado (“rojo”, “amarillo” o “verde”)
      $faltanParaTodas   = [];
      $alcanzanTodas     = [];
      $alcanzanAlgunas   = [];

      foreach ($mpResumen as $m) {
        switch ($m['estado']) {
          case 'rojo':
            // Formato: nombre (stockActual g)
            $faltanParaTodas[] = "{$m['nombre']} (" . number_format($m['stock_actual'],2) . "g)";
            break;
          case 'amarillo':
            $alcanzanAlgunas[] = "{$m['nombre']} (" . number_format($m['stock_actual'],2) . "g)";
            break;
          default:
          case 'verde':
            $alcanzanTodas[]   = "{$m['nombre']} (" . number_format($m['stock_actual'],2) . "g)";
            break;
        }
      }
    ?>

    <!-- 1) Rojo: “No alcanzan para ninguna orden” -->
    <?php if (count($faltanParaTodas) > 0): ?>
      <div class="alert alert-danger">
        <strong>No alcanzan para ninguna orden:</strong><br>
        <?= implode(', ', $faltanParaTodas) ?>
      </div>
    <?php endif; ?>

    <!-- 2) Verde: “Alcanzan para todas las órdenes” -->
    <?php if (count($alcanzanTodas) > 0): ?>
      <div class="alert alert-success">
        <strong>Alcanzan para todas las órdenes:</strong><br>
        <?= implode(', ', $alcanzanTodas) ?>
      </div>
    <?php endif; ?>

    <!-- 3) Amarillo: “Alcanzan solo para X de Y órdenes” -->
    <?php if (count($alcanzanAlgunas) > 0): ?>
      <div class="alert alert-warning">
        <strong>Alcanzan parcialmente:</strong><br>
        <?= implode(', ', $alcanzanAlgunas) ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>


  <!-- ——————————————————————————————
       Mostrar todas las órdenes pendientes en un loop
       —————————————————————————————— -->
  <?php if (!empty($ordenesPendientes)): ?>
    <?php foreach ($ordenesPendientes as $ord):
        //  Datos de la orden
        $orden_id       = $ord['orden_id'];
        $ficha_id       = $ord['ficha_id'];
        $nombreProd     = $ord['nombre_producto'];
        $rawCantidad    = floatval($ord['cantidad_orden']);  // ej. 7000
        $densidad       = floatval($ord['densidad_kg_por_l']);
        $unidadOrden    = trim($ord['unidad_orden']);        // 'g' o 'kg'
        $instrucciones  = $ord['instrucciones'];

        // — Convertir “cantidad pedida” a gramos: —
        if ($unidadOrden === 'kg') {
            $cantidadEnGramos = $rawCantidad * 1000.0;
        } else {
            $cantidadEnGramos = $rawCantidad;
        }
        // Para mostrar “Lote mínimo (ficha)” (solo como referencia)
        $lote_minimo_ficha   = floatval($ord['lote_minimo_ficha']);
    ?>
      <div class="card p-4 mb-5" id="orden_<?= $orden_id ?>">
        <h5 class="text-primary">
          Orden #<?= $orden_id ?> — <?= htmlspecialchars($nombreProd) ?>
        </h5>

        <p>
          <strong>Cantidad solicitada:</strong>
          <?= number_format($rawCantidad, 2) ?>&nbsp;<?= htmlspecialchars($unidadOrden) ?>
          <small class="text-muted">(equivale a <?= number_format($cantidadEnGramos,2) ?> g)</small>
        </p>
        
      <?php
          // Cálculo de litros estimados DENTRO del bucle
          $kg_solicitados = ($unidadOrden === 'kg') ? $rawCantidad : ($rawCantidad / 1000.0);
          $litros_estimados = ($densidad > 0) ? ($kg_solicitados / $densidad) : 0.0;
      ?>
        
      <p>
        <strong>Litros estimados:</strong>
        <?php
          echo number_format($cantidadEnGramos, 2), ' g';
          if ($litros_estimados !== null) {
            echo ' (≈ ', number_format($litros_estimados, 2), ' L';
            echo ' — densidad ', number_format($densidad, 3), ' kg/L)';
          }
        ?>
      </p>
        
        <p>
          <strong>Lote mínimo (ficha):</strong>
          <?= number_format($lote_minimo_ficha, 2) ?> g
          <small class="text-muted">(valor de referencia en la receta base)</small>
        </p>
        <p>
          <strong>Instrucciones:</strong>
          <?= $instrucciones ?: '—' ?>
        </p>

<!-- ——————————————————————————————
     5) FÓRMULA (Materia Prima para ESTE lote de “cantidadEnGramos”)
     —————————————————————————————— -->
<?php
// 5.1) Traer insumos (MP + subprocesos) de esta ficha
// Asegurar id de presentación 'gramos' (helper)
if (!isset($presentacionGramosId) || !$presentacionGramosId) {
  $presentacionGramosId = getPresentacionIdGramos($pdo);
}

$stmtMP = $pdo->prepare("
  SELECT
    CASE
      WHEN fmp.mp_id <= 100000 THEN mp.id
      ELSE (fmp.mp_id - 100000)
    END AS mp_id,
    COALESCE(mp.nombre, prod.nombre) AS mp_nombre,
    fmp.porcentaje_o_gramos          AS gramos_formula,
    COALESCE(mp.existencia, prod_ex.stock_total, 0) AS stock_actual
  FROM ficha_mp fmp
  LEFT JOIN materias_primas mp ON fmp.mp_id = mp.id
  LEFT JOIN productos prod     ON prod.id   = (fmp.mp_id - 100000)
  LEFT JOIN (
    SELECT producto_id, SUM(cantidad) AS stock_total
      FROM productos_terminados
     WHERE presentacion_id = :pres_gramos       /* <-- SOLO gramos */
     GROUP BY producto_id
  ) prod_ex ON prod_ex.producto_id = prod.id
  WHERE fmp.ficha_id = :ficha_id
");
$stmtMP->execute([
  ':pres_gramos' => $presentacionGramosId,
  ':ficha_id'    => $ficha_id
]);

$ingredientes = $stmtMP->fetchAll(PDO::FETCH_ASSOC);

// 5.2) Calcular sumatoria total de gramos_formula
$totalGramosFormula = 0.0;
foreach ($ingredientes as $ing) {
    $totalGramosFormula += floatval($ing['gramos_formula']);
}
?>

<!-- === Botón colapsable de fórmula === -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <h6 class="mb-0">
    Fórmula (Materia Prima para este lote de <?= number_format($cantidadEnGramos,2) ?> g)
  </h6>
  <button class="btn btn-sm btn-outline-secondary"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#formula_<?= $orden_id ?>"
          aria-expanded="false"
          aria-controls="formula_<?= $orden_id ?>">
    <i class="bi bi-chevron-down" id="icon_<?= $orden_id ?>"></i>
  </button>
</div>

<!-- === Sección colapsable (inicia oculta) === -->
<div class="collapse" id="formula_<?= $orden_id ?>">
  <table class="table table-sm table-bordered">
    <thead class="table-light">
      <tr>
        <th>Materia Prima</th>
        <th class="text-center">% (sobre total)</th>
        <th class="text-center">Necesario (g)</th>
        <th class="text-center">Stock actual (g)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ingredientes as $ing):
          $porcentaje  = $totalGramosFormula > 0
                       ? (floatval($ing['gramos_formula']) / $totalGramosFormula) * 100
                       : 0;
          $necesario   = ($porcentaje / 100) * $cantidadEnGramos;
          $stockActual = floatval($ing['stock_actual']);
      ?>
      <tr>
        <td><?= htmlspecialchars($ing['mp_nombre']) ?></td>
        <td class="text-center"><?= number_format($porcentaje,2) ?> %</td>
        <td class="text-center"><?= number_format($necesario,2) ?> g</td>
        <td class="text-center"><?= number_format($stockActual,2) ?> g</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- —————————————————————————————— FIN FÓRMULA —————————————————————————————— -->

        <!-- ——————————————————————————————
             6) Formulario para registrar producción de ESTA orden
             – Incluye “Hora inicio” / “Hora fin”
             – Campos: Litros, Galones, Cubetas y Gramos (pre‐llenado)
             —————————————————————————————— -->
        <form method="POST" class="mt-4">
          <input type="hidden" name="orden_id" value="<?= $orden_id ?>">

          <div class="row g-3">
            <div class="col-md-3">
              <label>Hora de inicio</label>
              <input 
                type="datetime-local" 
                name="hora_inicio" 
                class="form-control" 
                value=""
              >
            </div>
            <div class="col-md-3">
              <label>Hora de fin</label>
              <input 
                type="datetime-local" 
                name="hora_fin" 
                class="form-control" 
                value=""
              >
            </div>
  <?php
    $esSoloGr = !tienePresentacionesVolumetricas($pdo, (int)$ord['producto_id']);
    if (!$esSoloGr):
      $stPres = $pdo->prepare("
        SELECT pr.id, pr.nombre, pr.volumen_ml
          FROM productos_presentaciones pp
          JOIN presentaciones pr ON pr.id = pp.presentacion_id
         WHERE pp.producto_id = ?
           AND LOWER(pr.slug) <> 'gramos'
         ORDER BY pr.volumen_ml ASC, pr.nombre ASC
      ");
      $stPres->execute([(int)$ord['producto_id']]);
      $presList = $stPres->fetchAll(PDO::FETCH_ASSOC);
  ?>
    <div class="col-12">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light">
            <tr><th>Presentación</th><th>Volumen (ml)</th><th>Unidades a envasar <small class="text-muted">(campo único)</small></th></tr>
          </thead>
          <tbody>
          <?php foreach ($presList as $pr): ?>
            <tr>
              <td><?= htmlspecialchars($pr['nombre']) ?></td>
              <td><?= (int)$pr['volumen_ml'] ?></td>
              <td style="max-width:180px">
                <input type="number" step="1" min="0" class="form-control"
                       name="pres_qty[<?= (int)$pr['id'] ?>]" value="0">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <small class="text-muted">Puedes combinar tamaños; el sistema validará contra gramos producidos (vía densidad). Captura solo aquí.</small>
    </div>

  <?php endif; ?>
          </div>
          <div class="row g-3 mt-3">
            <div class="col-md-3">
              <label>Gramos producidos</label>
 <?php if ($esSoloGr): ?>
   <!-- Subproceso / solo gramos: editable -->
   <input type="number" step="0.01" name="gramos" class="form-control" value="<?= number_format($cantidadEnGramos, 2, '.', '') ?>">
 <?php else: ?>
   <!-- Producto con presentaciones: visible (solo lectura) + respaldo oculto -->
   <input type="number" step="0.01" class="form-control" value="<?= number_format($cantidadEnGramos, 2) ?>" disabled>
   <input type="hidden" name="gramos_base" value="<?= number_format($cantidadEnGramos, 2, '.', '') ?>">
 <?php endif; ?>
              

              <small class="text-muted">
                (“<?= number_format($cantidadEnGramos,2) ?> g” pre‐llenado)
              </small>
            </div>
            <div class="col-md-9"></div>
          </div>

          <div class="row g-3 mt-3">
            <div class="col text-end">
              <button 
                type="submit" 
                name="registrar_produccion" 
                class="btn btn-success px-4"
              >
                Registrar Producción
              </button>
            </div>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<script>
// Rotar chevron al colapsar/desplegar
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
  const targetId = btn.dataset.bsTarget.slice(1);
  const collapseEl = document.getElementById(targetId);
  const icon = btn.querySelector('i');

  collapseEl.addEventListener('shown.bs.collapse', () => {
    icon.classList.replace('bi-chevron-down','bi-chevron-up');
  });
  collapseEl.addEventListener('hidden.bs.collapse', () => {
    icon.classList.replace('bi-chevron-up','bi-chevron-down');
  });
});
</script>

  <!-- ——————————————————————————————
       Mensaje de éxito tras registrar producción
       —————————————————————————————— -->
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success mb-5">
      Producción registrada correctamente.
    </div>
  <?php endif; ?>
</div>


<?php include 'footer.php'; ?>




