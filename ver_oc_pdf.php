<?php
// ver_oc_pdf.php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// limpiar buffers previos
if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/fpdf.php';

// autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 1) leer ID de la OC
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2) obtener datos de la OC, incluyendo autorizador y fecha
$stmt = $pdo->prepare("
    SELECT oc.*,
           p.nombre   AS proveedor,
           u.nombre   AS solicitante,
            p.entrega_domicilio,
            p.direccion,
           oc.estado,
           oc.autorizador_id,
           oc.fecha_autorizacion,
           au.nombre  AS autorizador,
           au.rol     AS autorizador_rol
      FROM ordenes_compra oc
      JOIN proveedores p  ON p.id = oc.proveedor_id
      JOIN usuarios    u  ON u.id = oc.solicitante_id
      LEFT JOIN usuarios au ON au.id = oc.autorizador_id
     WHERE oc.id = ?
");
$stmt->execute([$id]);
$oc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$oc) {
    die('Orden de compra no encontrada');
}

// 3) líneas de la OC (insumos_comerciales o materias_primas)
$stmt = $pdo->prepare("
  SELECT 
         lc.mp_id,
         lc.ic_id,
         COALESCE(mp.nombre, ic.nombre) AS item_nombre,
         lc.cantidad,
         lc.precio_unitario,
         lc.subtotal
    FROM lineas_compra lc
    LEFT JOIN materias_primas     mp ON mp.id = lc.mp_id
    LEFT JOIN insumos_comerciales ic ON ic.id = lc.ic_id
   WHERE lc.orden_compra_id = ?
");
$stmt->execute([$id]);
$lines = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 4) calcular subtotal + IVA
$subtotal = 0.0;
foreach ($lines as $r) {
    $subtotal += floatval($r['subtotal']);
}
$iva      = $subtotal * 0.16;          // 16%
$totalIva = $subtotal + $iva;

// limpiar buffer para FPDF
if (ob_get_length()) ob_end_clean();

// 5) generar PDF
$pdf = new FPDF();
$pdf->AddPage();

// logo
$pdf->Image('assets/images/logo.png', 10, 8, 50);
$pdf->Ln(20);

// cabecera
$pdf->SetFont('Helvetica','B',14);
$pdf->Cell(0,10, "Orden de Compra #{$oc['id']}", 0,1,'C');
$pdf->Ln(5);

// datos generales
$pdf->SetFont('Helvetica','',10);
$pdf->Cell(0,6, "Proveedor: ".utf8_decode($oc['proveedor']), 0,1);
$pdf->Cell(0,6, "Fecha emision: ".$oc['fecha_emision'], 0,1);
$pdf->Cell(0,6, "Solicita: ".utf8_decode($oc['solicitante']), 0,1);
if ($oc['entrega_domicilio']) {
    // entrega a domicilio
    $pdf->SetFont('Helvetica','',10);
    $pdf->MultiCell(
      0,6,
      "El proveedor entrega el material en:\n" .
      utf8_decode($oc['direccion']),
      0,'L'
    );
} else {
    // recogida en proveedor
    $pdf->SetFont('Helvetica','',10);
    $pdf->Cell(
      0,6,
      "El Proveedor entrega el material en nuestras instalaciones.",
      0,1,'L'
    );
}
$pdf->Ln(2);

// estado y autorizador
$pdf->Cell(0,7, "Estado: ". ucfirst($oc['estado']), 0,1);

// determinar texto de “autorizado por”
if (empty($oc['autorizador_id'])) {
    $autor = 'No autorizada';
} else {
    switch ($oc['autorizador_rol']) {
        case 'admin':
            $autor = 'Direccion';
            break;
        case 'gerente':
            $autor = 'Gerencia';
            break;
        default:
            $autor = utf8_decode($oc['autorizador']);
    }
}
$pdf->Cell(0,7, "Autorizado por: {$autor}", 0,1);

// fecha de autorización (firma)
if (!empty($oc['fecha_autorizacion'])) {
    $dt = date_create($oc['fecha_autorizacion']);
    $pdf->SetFont('Helvetica','I',7);
    $pdf->Cell(
        0,5,
        'Firmado por medios digitales el: ' . date_format($dt,'d/m/Y H:i'),
        0,1
    );
}

// encabezados de la tabla
$pdf->SetFont('Helvetica','B',10);
$pdf->Cell(80,5,'Producto',        1,0,'C');
$pdf->Cell(30,5,'Cant. (g/pzas)',  1,0,'C');
$pdf->Cell(40,5,'P.Unitario',      1,0,'C');
$pdf->Cell(40,5,'Subtotal',        1,1,'C');

// contenido
$pdf->SetFont('Helvetica','',9);
foreach ($lines as $r) {
    $pdf->Cell(80,5, $r['item_nombre'], 1,0,'L');
    $pdf->Cell(30,5, number_format($r['cantidad'], 0, '.', ','), 
               1,0,'R');
    $pdf->Cell(40,5, '$'.number_format($r['precio_unitario'], 6, '.', ','), 
               1,0,'R');
    $pdf->Cell(40,5, '$'.number_format($r['subtotal'],        2, '.', ','), 
               1,1,'R');
}


$pdf->SetFont('Helvetica','B',10);

// 1ª fila: Subtotal
$pdf->Cell(80,6,'',             0,0); // Producto (vacio)
$pdf->Cell(30,6,'',             0,0); // Cant.     (vacio)
$pdf->Cell(40,6,'Subtotal:',    1,0,'R'); // P.Unitario colapsado para etiqueta
$pdf->Cell(40,6,'$'.number_format($subtotal,2,'.',','), 1,1,'R');

// 2ª fila: IVA
$pdf->Cell(80,6,'',             0,0);
$pdf->Cell(30,6,'',             0,0);
$pdf->Cell(40,6,'IVA (16%):',   1,0,'R');
$pdf->Cell(40,6,'$'.number_format($iva,2,'.',','),      1,1,'R');

// 3ª fila: Total
$pdf->Cell(80,6,'',             0,0);
$pdf->Cell(30,6,'',             0,0);
$pdf->Cell(40,6,'Total:',       1,0,'R');
$pdf->Cell(40,6,'$'.number_format($totalIva,2,'.',','), 1,1,'R');



$pdf->Ln(20);

// pie de página con texto adicional
$pdf->SetFont('Helvetica','I',9);
$pdf->MultiCell(
    0,5,
    utf8_decode(
        "Moneda: Pesos Mexicanos.\n".
        "El numero de orden de compra debera aparecer en todos sus documentos.\n".
        "La columna de cantidad expresa gramos o piezas segun sea el caso.\n".
        "En caso que el proveedor tenga servicio a domicilio la mercancia se recibe en: Artemio Alpizar 1413, Zona Industrial, Guadalajara, Jalisco.\n\n".
        "Datos de Facturacion:\n".
        "ALEJANDRO MIGUEL FERNANDEZ ARIAS\n".
        "FEAA8509013D3\n".
        "AV. 8 DE JULIO 3001- B COL. LOMAS DE POLANCO\n".
        "C.P. 44960\n".
        "GUADALAJARA, JALISCO"
    ),
    0,
    'L'
);

// salida definitiva
$pdf->Output('D','oc_'.$oc['id'].'.pdf');
exit;
