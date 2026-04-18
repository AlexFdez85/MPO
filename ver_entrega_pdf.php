<?php
// ver_entrega_pdf.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// limpiar buffers previos
if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/fpdf.php';

// autenticación
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 1) leer ID de la orden de venta
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// validar existencia
$stmt = $pdo->prepare("SELECT ov.*, 
       c.nombre AS cliente, 
       d.nombre AS distribuidor
  FROM ordenes_venta ov
  LEFT JOIN clientes c ON c.id = ov.cliente_id
  LEFT JOIN clientes d ON d.id = ov.distribuidor_id
 WHERE ov.id = ?");
$stmt->execute([$id]);
$ov = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ov) {
    die('Pedido no encontrado');
}

// 2) líneas APARTADAS (reservas activas) por lote para esta OV
$stmt = $pdo->prepare("
  SELECT
    p.nombre               AS producto,
    pr.nombre              AS presentacion,
    SUM(rv.cantidad)       AS cantidad,
    rv.lote_codigo         AS lote,
    MIN(rv.creado_en)      AS fecha_reserva
  FROM reservas_venta rv
  JOIN lineas_venta lv   ON lv.id = rv.linea_venta_id
  JOIN productos p       ON p.id = rv.producto_id
  JOIN presentaciones pr ON pr.id = rv.presentacion_id
  WHERE rv.orden_venta_id = ?
    AND rv.estado = 'activa'
  GROUP BY rv.producto_id, rv.presentacion_id, rv.lote_codigo
  ORDER BY p.nombre, pr.nombre, rv.lote_codigo
");
$stmt->execute([$id]);

$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2b) Fallback: si ya no hay reservas activas (pedido en Historial),
// construimos el PDF desde SURTIDOS (lo realmente entregado)
if (!$lines || count($lines) === 0) {
    $stmt = $pdo->prepare("
      SELECT
        p.nombre                 AS producto,
        pr.nombre                AS presentacion,
        SUM(sv.cantidad)         AS cantidad,
        sv.lote_produccion       AS lote,
        MIN(sv.fecha_surtido)    AS fecha_surtido
      FROM surtidos_venta sv
      JOIN lineas_venta lv   ON lv.id = sv.linea_venta_id
      JOIN productos p       ON p.id  = lv.producto_id
      JOIN presentaciones pr ON pr.id = lv.presentacion_id
      WHERE lv.orden_venta_id = ?
      GROUP BY lv.producto_id, lv.presentacion_id, sv.lote_produccion
      ORDER BY p.nombre, pr.nombre, sv.lote_produccion
    ");
    $stmt->execute([$id]);
    $lines = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// limpiar buffer para FPDF
if (ob_get_length()) ob_end_clean();

// 3) generar PDF
$pdf = new FPDF();
$pdf->AddPage();
// logo (opcional)
$pdf->Image('assets/images/logo.png',10,8,50);
$pdf->Ln(20);

// Título
$pdf->SetFont('Helvetica','B',14);
$pdf->Cell(0,10, "Entrega de Pedido #{$ov['id']}", 0,1,'C');
$pdf->Ln(5);

// Datos generales
$pdf->SetFont('Helvetica','',10);
$pdf->Cell(0,6, "Cliente: " . utf8_decode($ov['cliente']), 0,1);
$pdf->Cell(0,6, "Distribuidor: " . utf8_decode($ov['distribuidor']), 0,1);
$pdf->Cell(0,6, "Fecha de entrega programada: " . $ov['fecha_entrega'], 0,1);
$pdf->Ln(5);

// Tabla de productos y lotes
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(70,8, 'Producto',1,0,'C');
$pdf->Cell(35,8, 'Presentacion',1,0,'C');
$pdf->Cell(20,8, 'Cant.',1,0,'C');
$pdf->Cell(40,8, 'Lote de produccion',1,0,'C');
$pdf->Cell(25,8, 'Fecha',1,1,'C');

$pdf->SetFont('Helvetica','',9);
foreach ($lines as $r) {
    // obtener fecha_surtido (si hace falta)
    // asumimos fecha_surtido en sv
    $pdf->Cell(70,6, utf8_decode($r['producto']),1,0);
    $pdf->Cell(35,6, utf8_decode($r['presentacion']),1,0,'C');
    $pdf->Cell(20,6, (string)$r['cantidad'],1,0,'C');
    $pdf->Cell(40,6, (string)$r['lote'],1,0,'C');
    // Fecha: usa la de RESERVA si existe; si no, la de SURTIDO
    $fecha = '';
    if (!empty($r['fecha_reserva'])) {
        $fecha = substr($r['fecha_reserva'], 0, 10);
    } elseif (!empty($r['fecha_surtido'])) {
        $fecha = substr($r['fecha_surtido'], 0, 10);
    }
    $pdf->Cell(25,6, $fecha,1,1,'C');
}

$pdf->Ln(10);
// Firma cliente
$pdf->SetFont('Helvetica','',10);
$pdf->Cell(0,8,'Recibido por (firma): _________________________________',0,1,'L');
$pdf->Cell(0,6,'Fecha y hora: _______________________',0,1,'L');

// Pie
$pdf->SetFont('Helvetica','I',7);
$pdf->MultiCell(0,5, utf8_decode(
    "Este documento sirve como constancia de entrega de materiales y lotes. " .
    "Se debe resguardar para seguimiento de calidad y trazabilidad."
));

// Salida
$pdf->Output('D', 'entrega_pedido_'.$ov['id'].'.pdf');
exit;
?>
