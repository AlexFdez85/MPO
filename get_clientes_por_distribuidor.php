<?php
require_once 'config.php';

if (!isset($_GET['distribuidor_id'])) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$distribuidor_id = intval($_GET['distribuidor_id']);

$stmt = $pdo->prepare("
    SELECT dc.cliente_id AS id, c.nombre, dc.localidad
    FROM distribuidores_clientes dc
    JOIN clientes c ON dc.cliente_id = c.id
    WHERE dc.distribuidor_id = ?
");

$stmt->execute([$distribuidor_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($result);
?>