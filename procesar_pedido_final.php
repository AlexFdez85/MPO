<?php
// procesar_pedido_final.php
require_once 'config.php';

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pedido'])) {
    try {
        $pdo->beginTransaction();

        $clienteId = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
        $distribuidorId = !empty($_POST['distribuidor_id']) ? intval($_POST['distribuidor_id']) : null;
        $fechaEntrega = $_POST['fecha_entrega'] ?? date('Y-m-d');
        $incluyePaquete = isset($_POST['incluye_paquete']) ? 1 : 0;

        $insCab = $pdo->prepare("INSERT INTO ordenes_venta (cliente_id, distribuidor_id, fecha, fecha_entrega, estado, usuario_creador, incluye_paquete) VALUES (?, ?, CURDATE(), ?, 'pendiente', ?, ?)");
        $insCab->execute([$clienteId, $distribuidorId, $fechaEntrega, $usuario_id, $incluyePaquete]);
        $ventaId = $pdo->lastInsertId();

        $prodIds = $_POST['producto_id'] ?? [];
        $presIds = $_POST['presentacion_id'] ?? [];
        $cants = $_POST['cantidad'] ?? [];
        $stmtLinea = $pdo->prepare("INSERT INTO lineas_venta (orden_venta_id, producto_id, presentacion_id, cantidad) VALUES (?, ?, ?, ?)");

        foreach ($prodIds as $i => $pid) {
            $pid = intval($pid);
            $pr = intval($presIds[$i] ?? 0);
            $ct = floatval($cants[$i] ?? 0);
            if ($pid > 0 && $pr > 0 && $ct > 0) {
                $stmtLinea->execute([$ventaId, $pid, $pr, $ct]);
            }
        }
        
        $pdo->commit();
        header('Location: ordenes_venta.php?ok=1');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al registrar el pedido: " . $e->getMessage());
    }
} else {
    header('Location: ordenes_venta.php?error=no_data');
    exit;
}
?>