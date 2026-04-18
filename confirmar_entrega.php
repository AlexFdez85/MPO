<?php
// confirmar_entrega.php
require_once 'config.php';

// Opcional: ver errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Validaci¨®n de sesi¨®n/permiso (ajusta roles si aplica)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)) {
    header('Location: ordenes_venta.php?error=permiso');
    exit;
}

// ID de la orden
$ovId = (int)($_GET['id'] ?? $_POST['orden_venta_id'] ?? 0);
if ($ovId <= 0) {
    header('Location: ordenes_venta.php?error=sin_id');
    exit;
}

/**
 * Convierte todas las reservas ACTIVAS de una OV en salidas reales (surtidos_venta)
 * y marca esas reservas como 'consumida'.
 */
function convertirReservasASurtidos(PDO $pdo, int $ovId): void {
    $st = $pdo->prepare("
        SELECT id, linea_venta_id, lote_codigo, cantidad
        FROM reservas_venta
        WHERE orden_venta_id = ? AND estado = 'activa'
        ORDER BY linea_venta_id, lote_codigo
    ");
    $st->execute([$ovId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return;

    $ins = $pdo->prepare("
        INSERT INTO surtidos_venta
            (orden_venta_id, linea_venta_id, lote_produccion, cantidad, fecha_surtido)
        VALUES
            (?, ?, ?, ?, NOW())
    ");
    $upd = $pdo->prepare("UPDATE reservas_venta SET estado='consumida' WHERE id=?");

    foreach ($rows as $r) {
        $ins->execute([
            $ovId,
            (int)$r['linea_venta_id'],
            $r['lote_codigo'],
            (float)$r['cantidad']
        ]);
        $upd->execute([(int)$r['id']]);
    }
}

try {
    $pdo->beginTransaction();

    // 1) Reservas activas -> surtidos (para que baje inventario y aparezca en trazabilidad)
    convertirReservasASurtidos($pdo, $ovId);

    $yaExiste = (int)$pdo->prepare("SELECT COUNT(*) FROM entregas_venta WHERE orden_venta_id = ?")
                         ->execute([$ovId]) ?: 0;
    // Nota: execute() devuelve bool; hacemos en dos pasos para seguridad:
    $stChk = $pdo->prepare("SELECT COUNT(*) FROM entregas_venta WHERE orden_venta_id = ?");
    $stChk->execute([$ovId]);
    $cnt = (int)$stChk->fetchColumn();
    
    if ($cnt === 0) {
        $pdo->prepare("INSERT INTO entregas_venta (orden_venta_id, fecha_entrega) VALUES (?, NOW())")
            ->execute([$ovId]);
    }

    // 3) Marcar estado 'entregado' (idempotente)
    $pdo->prepare("UPDATE ordenes_venta SET estado='entregado' WHERE id=?")->execute([$ovId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    // En producci¨®n podr¨ªas loguearlo y redirigir con error gen¨¦rico
    header('Location: ordenes_venta.php?error='.urlencode('confirmar: '.$e->getMessage()));
    exit;
}

// Vuelta al listado con mensaje de ¨¦xito
header('Location: ordenes_venta.php?entrega_confirmada=1');
exit;


//<?php
// confirmar_entrega.php

// 0) Ver errores en pantalla
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(E_ALL);

// 1) ConfiguraciÃ³n y sesiÃ³n
//require_once 'config.php';
//if (session_status() !== PHP_SESSION_ACTIVE) session_start();
//if (!isset($_SESSION['user_id'])) {
//    header('Location: login.php');
//    exit;
//}

// 2) Leer y validar ID
//$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
//if ($id <= 0) {
//    die('ID de pedido invÃ¡lido');
//}

// 3) Insertar entrega
//try {
//    $stmt = $pdo->prepare(
//        "INSERT INTO entregas_venta (orden_venta_id, firma_cliente)
//         VALUES (?, NULL)"
//    );
//    $stmt->execute([$id]);
//} catch (PDOException $e) {
//    die("Error al grabar la entrega: " . $e->getMessage());
//}

// 4) Limpiar buffers y redirigir
//if (ob_get_length()) ob_end_clean();
//header('Location: ordenes_venta.php?entrega_confirmada=1');
//exit;




