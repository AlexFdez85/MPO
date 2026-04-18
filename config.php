<?php
// MPO - ConfiguraciÃ³n base
session_start();

define('APP_NAME', 'Manufacturing & Process Operations');
define('BASE_URL', 'https://www.a4paints.com/mpo/');

// ConexiÃ³n a base de datos (ajusta credenciales segÃºn tu servidor)
define('DB_HOST', 'localhost');
define('DB_NAME', 'apaintsc_a4_mpo');
define('DB_USER', 'apaintsc_a4paints');
define('DB_PASS', 'Petrolera85**');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    die("Error en conexiÃ³n: " . $e->getMessage());
}

$pendientesCompra = (int)$pdo
  ->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado='pendiente'")
  ->fetchColumn();

function getParam(PDO $pdo, string $clave, $default=null) {
  $stmt = $pdo->prepare("SELECT valor FROM parametros_sistema WHERE clave=?");
  $stmt->execute([$clave]);
  $v = $stmt->fetchColumn();
  return $v !== false ? $v : $default;
}

function getPresentacionIdGramos(PDO $pdo) {
  // 1) por slug
  $id = $pdo->query("SELECT id FROM presentaciones WHERE slug='gramos'")->fetchColumn();
  if ($id) return (int)$id;
  // 2) por par¨¢metro
  $id = getParam($pdo, 'presentacion_gramos_id', null);
  if ($id) return (int)$id;
  // 3) ¨²ltimo recurso por nombre
  $stmt = $pdo->prepare("SELECT id FROM presentaciones WHERE LOWER(nombre)='gramos' LIMIT 1");
  $stmt->execute();
  return (int)$stmt->fetchColumn();
}

function insertMovimiento(PDO $pdo, int $mp_id, string $tipo, float $cantidad, int $usuario_id, string $comentario='', $origen_id=null) {
  $stmt = $pdo->prepare("
    INSERT INTO movimientos_mp (mp_id, tipo, cantidad, fecha, usuario_id, comentario, origen_id)
    VALUES (?, ?, ?, NOW(), ?, ?, ?)
  ");
  $stmt->execute([$mp_id, $tipo, $cantidad, $usuario_id, $comentario, $origen_id]);
}

function insertProduccionConsumo(PDO $pdo, int $produccion_id, int $mp_id, float $gramos, ?string $lote=null) {
  $stmt = $pdo->prepare("
    INSERT INTO produccion_consumos (produccion_id, mp_id, cantidad_consumida, lote_recepcion)
    VALUES (?, ?, ?, ?)
  ");
  $stmt->execute([$produccion_id, $mp_id, $gramos, $lote]);
}

/** Convierte presentaciones a gramos. Requiere densidad kg/L para l¨ªquidos.
 *  $litros, $galones, $cubetas pueden venir 0/null. */
function calcularGramosProduccion(PDO $pdo, float $litros=0, float $galones=0, float $cubetas=0, ?float $densidad_kg_por_l=null): float {
  $lpC = (float)getParam($pdo, 'litros_por_cubeta', 19);      // litros por cubeta
  $litros_tot = (float)$litros + ((float)$galones * 3.785411784) + ((float)$cubetas * $lpC);
  // Si no aplica densidad (s¨®lidos), puedes pasar $densidad_kg_por_l=1 y ajustar seg¨²n producto
  $kg = $litros_tot * (float)($densidad_kg_por_l ?: 1);
  return round($kg * 1000, 3); // a gramos
}


// Helpers de empaque / kits
if (!function_exists('packaging_autofill_items_from_kit')) {
  /**
   * Carga los insumos de empaque definidos en packaging_kits para (producto,presentaci¨®n)
   * y los inserta en packaging_request_items del request indicado.
   */
  function packaging_autofill_items_from_kit(PDO $pdo, int $requestId, int $productoId, int $presentacionId): void {
    $q = $pdo->prepare("
      SELECT insumo_comercial_id, cantidad
      FROM packaging_kits
      WHERE producto_id=? AND presentacion_id=?
    ");
    $q->execute([$productoId, $presentacionId]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return;

    // Evitar duplicados por (request_id, insumo_comercial_id)
    $ins = $pdo->prepare("
      INSERT INTO packaging_request_items (request_id, insumo_comercial_id, cantidad)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)
    ");
    foreach ($rows as $r) {
      $ins->execute([$requestId, (int)$r['insumo_comercial_id'], (float)$r['cantidad']]);
    }
  }
}

?>



