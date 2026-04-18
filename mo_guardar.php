<?php
// mo_guardar.php
require_once 'config.php';
header('Content-Type: application/json');

function costo_hora(PDO $pdo, ?int $userId): float {
  if (!$userId) return 0.0;
  $st = $pdo->prepare("SELECT nomina_diaria_mxn, jornada_horas FROM usuarios WHERE id=?");
  $st->execute([$userId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  if (!$r) return 0.0;
  $j = (float)($r['jornada_horas'] ?? 8);
  $n = (float)($r['nomina_diaria_mxn'] ?? 0);
  return ($j > 0 && $n > 0) ? ($n / $j) : 0.0;
}

try {
  $orden_id = (int)($_POST['orden_id'] ?? 0);
  $user_id  = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
  $rol      = $_POST['rol'] ?? 'operador';
  $min_prod = (int)($_POST['min_prod'] ?? 0);
  $min_setup= (int)($_POST['min_setup'] ?? 0);
  $min_limp = (int)($_POST['min_limpieza'] ?? 0);
  $min_mant = (int)($_POST['min_manto'] ?? 0);
  $fecha    = $_POST['fecha'] ?? date('Y-m-d');
  $notas    = trim($_POST['notas'] ?? '');

  if ($orden_id<=0) throw new Exception("orden_id inválido");

  $costo_hora_aplicado = isset($_POST['costo_hora_aplicado']) && $_POST['costo_hora_aplicado'] !== ''
    ? (float)$_POST['costo_hora_aplicado']
    : costo_hora($pdo, $user_id);

  if ($costo_hora_aplicado <= 0) throw new Exception("Define nómina diaria y jornada en usuarios, o envía costo_hora_aplicado");

  $ins = $pdo->prepare("
    INSERT INTO produccion_mo
      (orden_id,user_id,rol,min_prod,min_setup,min_limpieza,min_manto,costo_hora_aplicado,fecha,notas)
    VALUES (?,?,?,?,?,?,?,?,?,?)
  ");
  $ins->execute([$orden_id,$user_id,$rol,$min_prod,$min_setup,$min_limp,$min_mant,$costo_hora_aplicado,$fecha,$notas]);

  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
