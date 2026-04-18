<?php
// mpo/api_convertir_distribuidor.php
if (session_status()===PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/clientes_upsert.php';

// Valida que sea un usuario MPO con permisos (admin/gerente) o el propio mayorista dueño.
$mpoUserId  = (int)($_SESSION['user']['id'] ?? 0);
$mpoUserRol = $_SESSION['user']['rol'] ?? '';
if (!$mpoUserId) { http_response_code(401); exit('No autorizado'); }

$data = [
  'razon_social'      => trim($_POST['razon_social'] ?? ''),
  'rfc'               => trim($_POST['rfc'] ?? ''),
  'email'             => trim($_POST['email'] ?? ''),
  'telefono'          => trim($_POST['telefono'] ?? ''),
  'domicilio'         => trim($_POST['domicilio'] ?? ''),
  'ciudad'            => trim($_POST['ciudad'] ?? ''),
  'estado'            => trim($_POST['estado'] ?? ''),
  'pais'              => trim($_POST['pais'] ?? 'México'),
  'cp'                => trim($_POST['cp'] ?? ''),
  'mayorista_user_id' => (int)($_POST['mayorista_user_id'] ?? 0),
  'zona'              => trim($_POST['zona'] ?? ''),
  'exclusivo'         => (int)($_POST['exclusivo'] ?? 1),
  'status'            => 'activo'
];

$clienteId = mpo_upsert_cliente_y_vinculo($pdo, $data);
header('Content-Type: application/json');
echo json_encode(['ok'=>true, 'cliente_id'=>$clienteId]);
