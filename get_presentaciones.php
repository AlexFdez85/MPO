<?php
require_once 'config.php';

// Validar que se haya pasado un ID de producto
if (!isset($_GET['producto_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$productoId = intval($_GET['producto_id']);

// Consultar las presentaciones válidas para el producto
$stmt = $pdo->prepare("
  SELECT p.id, p.nombre
  FROM presentaciones p
  INNER JOIN productos_presentaciones pp ON p.id = pp.presentacion_id
  WHERE pp.producto_id = ?
");
$stmt->execute([$productoId]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Devolver las presentaciones en formato JSON
echo json_encode($result);
?>
