<?php
// lib_packaging.php
require_once 'config.php';

/**
 * Crea (si no existe) una solicitud de empaque pendiente para la OP dada.
 * Devuelve el ID de la request.
 */
function packaging_create_request(PDO $pdo, int $ordenId, int $solicitanteId): int {
  // ¿ya existe pendiente?
  $q = $pdo->prepare("
    SELECT id
      FROM packaging_requests
     WHERE orden_id = ? AND estado = 'pendiente'
     ORDER BY id DESC LIMIT 1
  ");
  $q->execute([$ordenId]);
  $id = (int)$q->fetchColumn();
  if ($id > 0) return $id;

  // Crear nueva
  $ins = $pdo->prepare("
    INSERT INTO packaging_requests (orden_id, solicitante_id, estado, creado_en)
    VALUES (?, ?, 'pendiente', NOW())
  ");
  $ins->execute([$ordenId, $solicitanteId]);
  return (int)$pdo->lastInsertId();
}

/**
 * Rechaza una solicitud (opcional).
 */
function packaging_reject_request(PDO $pdo, int $requestId, int $userId, string $motivo=''): void {
  $ownTx = false;
  if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $ownTx = true; }
  try {
    $q = $pdo->prepare("SELECT id, estado FROM packaging_requests WHERE id=? FOR UPDATE");
   $q->execute([$requestId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException("Solicitud #{$requestId} no encontrada");
    if ($row['estado'] !== 'pendiente') throw new RuntimeException("La solicitud no está pendiente");

    $upd = $pdo->prepare("
      UPDATE packaging_requests
         SET estado='rechazada', autorizador_id=?, autorizado_en=NOW()
       WHERE id=?
    ");
    $upd->execute([$userId, $requestId]);
    // (si tienes tabla de motivos, insértalo ahí)
    if ($ownTx) $pdo->commit();
  } catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}

/**
 * Autoriza y descuenta insumos comerciales de una solicitud de empaque.
 * - Valida stock
 * - Descuenta insumos_comerciales.stock
 * - Inserta salida en movimientos_insumos (usa columna `fecha`)
 * - Marca la solicitud como autorizada (autorizador_id / autorizado_en)
 */
function packaging_authorize_and_consume(PDO $pdo, int $requestId, int $userId): void {
  // No anidar transacciones
  $ownTx = false;
  if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $ownTx = true; }
  try {
    // Cabecera
    $qReq = $pdo->prepare("
      SELECT orden_id, estado
        FROM packaging_requests
       WHERE id = ? FOR UPDATE
    ");
    $qReq->execute([$requestId]);
    $req = $qReq->fetch(PDO::FETCH_ASSOC);
    if (!$req) throw new RuntimeException("Solicitud #{$requestId} no encontrada");
    if ($req['estado'] !== 'pendiente') throw new RuntimeException("La solicitud no está pendiente");
    $ordenId = (int)$req['orden_id'];

    // Renglones (lock) + stock actual
    $qIt = $pdo->prepare("
      SELECT pri.id,
             pri.insumo_comercial_id,
             pri.cantidad,
             pri.cantidad_solicitada,
             pri.cantidad_autorizada,
             COALESCE(pri.aprobado,1) AS aprobado,
             COALESCE(ic.stock,0) AS stock_actual,
             ic.nombre
        FROM packaging_request_items pri
        JOIN insumos_comerciales ic ON ic.id = pri.insumo_comercial_id
       WHERE pri.request_id = ?
       FOR UPDATE
    ");
    $qIt->execute([$requestId]);
    $items = $qIt->fetchAll(PDO::FETCH_ASSOC);
    if (!$items) throw new RuntimeException("La solicitud no tiene renglones");

    // 1) Validación de stock
    foreach ($items as $it) {
      $need = ((int)$it['aprobado'] === 1)
                ? (float)($it['cantidad_autorizada'] ?? $it['cantidad_solicitada'] ?? $it['cantidad'])
                : 0.0;
      if ($need <= 0) continue;
      if ((float)$it['stock_actual'] < $need) {
        $msj = "Stock insuficiente de '{$it['nombre']}' (requiere {$need}, hay {$it['stock_actual']})";
        throw new RuntimeException($msj);
      }
    }

    // 2) Descuentos + movimientos
    $updStock = $pdo->prepare("UPDATE insumos_comerciales SET stock = stock - ? WHERE id = ?");
    $insMov   = $pdo->prepare("
      INSERT INTO movimientos_insumos (insumo_id, tipo, cantidad, fecha, usuario_id, comentario)
      VALUES (?, 'salida', ?, NOW(), ?, ?)
    ");
    foreach ($items as $it) {
      $need = ((int)$it['aprobado'] === 1)
                ? (float)($it['cantidad_autorizada'] ?? $it['cantidad_solicitada'] ?? $it['cantidad'])
                : 0.0;
      if ($need <= 0) continue;
      $updStock->execute([$need, (int)$it['insumo_comercial_id']]);
      $coment = sprintf("Empaque OP #%d — request #%d", $ordenId, $requestId);
      $insMov->execute([(int)$it['insumo_comercial_id'], $need, $userId, $coment]);
    }

    // 3) Cerrar solicitud
    $updReq = $pdo->prepare("
      UPDATE packaging_requests
         SET estado='autorizada', autorizador_id=?, autorizado_en=NOW()
       WHERE id=?
    ");
    $updReq->execute([$userId, $requestId]);

    if ($ownTx) $pdo->commit();
  } catch (Throwable $e) {
    if ($ownTx && $pdo->inTransaction()) $pdo->rollBack();
    throw $e;
  }
}