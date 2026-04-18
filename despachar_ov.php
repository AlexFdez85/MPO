<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente','logistica'])) {
    header('Location: login.php');
    exit;
}

$ov_id       = (int)$_POST['ov_id'];
$producto_id = (int)$_POST['producto_id'];
$presentacion = $_POST['presentacion'];
$cantidad     = floatval($_POST['cantidad']);

// 1) Restar la cantidad del inventario “Productos Terminados”
//    Aquí, asumiré que queremos restar de las filas más antiguas (FIFO).
//    Una forma simple es buscar los registros PT por orden ASC `fecha, id` 
//    y consumir hasta alcanzar lo solicitado.

$stmt = $pdo->prepare("
  SELECT id, cantidad 
  FROM productos_terminados 
  WHERE producto_id = ? AND presentacion = ? 
  ORDER BY fecha ASC, id ASC
");
$stmt->execute([$producto_id, $presentacion]);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$to_despachar = $cantidad;
foreach ($filas as $fila) {
    $pt_id     = $fila['id'];
    $pt_cant   = floatval($fila['cantidad']);
    if ($pt_cant <= $to_despachar) {
        // Borrar esta fila (o marcarla como consumida)
        $pdo->prepare("DELETE FROM productos_terminados WHERE id = ?")->execute([$pt_id]);
        $to_despachar -= $pt_cant;
    } else {
        // Actualizar cantidad restante en esta fila
        $nueva_cant = $pt_cant - $to_despachar;
        $pdo->prepare("UPDATE productos_terminados SET cantidad = ? WHERE id = ?")
            ->execute([$nueva_cant, $pt_id]);
        $to_despachar = 0;
    }
    if ($to_despachar <= 0) break;
}

if ($to_despachar > 0) {
    // Teóricamente no debería pasar (porque ya checamos inventario >= cantidad)
    die("Error: No se pudo despachar completamente.");
}

// 2) Marcar la OV como “enviada” o “completada” según tu lógica
$pdo->prepare("UPDATE ordenes_venta SET estado = 'enviada' WHERE id = ?")
    ->execute([$ov_id]);

header("Location: ordenes_venta.php?despachado=1");
exit;
