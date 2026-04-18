<?php
// productos.php

// Mostrar errores en pantalla mientras se depura
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Solo Admin o Gerente pueden acceder
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'gerente'])) {
    header('Location: login.php');
    exit;
}

// ------------------------------------------------------------
// Utilidades de presentaciones (filtrado / deduplicación)
// ------------------------------------------------------------
/**
 * Regresa TRUE si existe en el catálogo alguna presentación en ml con ese volumen.
 */
function existeMl(array $rows, int $ml): bool {
    foreach ($rows as $r) {
        $slug = strtolower($r['slug'] ?? '');
        $vol  = (int)($r['volumen_ml'] ?? 0);
        if ($vol === $ml && strpos($slug, 'ml_') === 0) return true;
    }
    return false;
}

/**
 * Oculta sinónimos "históricos" si ya existen sus equivalentes en ml:
 * - Litros   (1000 ml)
 * - Galones  (3785 ml aprox)
 * - Cubetas  (19000 ml)
 * - Cuarto L (946 ml)
 * - Medio L  (473 ml)
 * - Octavo L (236 ml)
 */
function filtraPresentaciones(array $rows): array {
    $skipByName = [];
    if (existeMl($rows, 1000))  { $skipByName['litros']   = true; }
    if (existeMl($rows, 3785))  { $skipByName['galones']  = true; }
    if (existeMl($rows, 19000)) { $skipByName['cubetas']  = true; }
    if (existeMl($rows, 946))   { $skipByName['cuarto l'] = true; }
    if (existeMl($rows, 473))   { $skipByName['medio l']  = true; }
    if (existeMl($rows, 237))   { $skipByName['octavo l'] = true; }

    $out = [];
    foreach ($rows as $r) {
        $name = strtolower(trim($r['nombre'] ?? ''));
        if (isset($skipByName[$name])) continue;
        $out[] = $r;
    }
    return $out;
}


// 1) PROCESAR CREACIÓN o EDICIÓN de producto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_producto'])) {
    $nombre     = trim($_POST['nombre']);
    $densidad   = isset($_POST['densidad']) && $_POST['densidad'] !== '' 
                 ? floatval($_POST['densidad']) 
                 : null;
                 
    // NUEVO: unidad de venta ('g','kg','l')
    $unidad_venta = isset($_POST['unidad_venta']) && $_POST['unidad_venta'] !== ''
                    ? $_POST['unidad_venta'] : 'g';
                 
                 
    $usuario_id = $_SESSION['user_id'];

    if (!empty($_POST['producto_id'])) {
        // --- EDICIÓN de un producto ya existente ---
        $pid = (int)$_POST['producto_id'];
        $stmt = $pdo->prepare("
          UPDATE productos 
             SET nombre = ?, densidad_kg_por_l = ?, unidad_venta = ?
           WHERE id = ?
        ");
        $stmt->execute([$nombre, $densidad, $unidad_venta, $pid]);

        // Borrar presentaciones antiguas y luego reinsertar las nuevas
        $pdo->prepare("DELETE FROM productos_presentaciones WHERE producto_id = ?")
            ->execute([$pid]);

        $prodInsertId = $pid;
    } else {
        // --- CREACIÓN de producto nuevo ---
        $stmt = $pdo->prepare("
          INSERT INTO productos (nombre, densidad_kg_por_l, unidad_venta, creado_por)
          VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $densidad, $unidad_venta, $usuario_id]);
        $prodInsertId = $pdo->lastInsertId();
    }

    // 2) ASOCIAR PRESENTACIONES seleccionadas
    if (!empty($_POST['presentaciones'])) {
        foreach ($_POST['presentaciones'] as $pres_id) {
            $stmt2 = $pdo->prepare("
              INSERT INTO productos_presentaciones 
                (producto_id, presentacion_id) VALUES (?, ?)
            ");
            $stmt2->execute([$prodInsertId, (int)$pres_id]);
        }
    }



    // 3) NUEVO — Guardar kits de empaque por presentación (solo de las seleccionadas)
    $pdo->prepare("DELETE FROM packaging_kits WHERE producto_id = ?")->execute([$prodInsertId]);
    if (isset($_POST['kit']) && is_array($_POST['kit'])) {
        $seleccionadas = array_map('intval', $_POST['presentaciones'] ?? []);
        foreach ($_POST['kit'] as $presId => $kitPres) {
            $presId = (int)$presId;
            if (!in_array($presId, $seleccionadas, true)) { continue; }
            $insumos    = $kitPres['insumo_id'] ?? [];
            $cantidades = $kitPres['cantidad']  ?? [];
            $n = max(count($insumos), count($cantidades));
            for ($i=0; $i<$n; $i++) {
                $iid = isset($insumos[$i])    ? (int)$insumos[$i]    : 0;
                $qty = isset($cantidades[$i]) ? (float)$cantidades[$i] : 0.0;
                if ($iid > 0 && $qty > 0) {
                    $pdo->prepare("
                      INSERT INTO packaging_kits (producto_id, presentacion_id, insumo_comercial_id, cantidad)
                      VALUES (?, ?, ?, ?)
                    ")->execute([$prodInsertId, $presId, $iid, $qty]);
                }
            }
        }
    }

    header("Location: productos.php?ok=1");
    exit;
}



// 3) PROCESAR BORRAR producto (con sus presentaciones)
if (isset($_GET['borrar']) && is_numeric($_GET['borrar'])) {
    $pid = (int)$_GET['borrar'];
    // Primero eliminamos de la tabla pivot
    $pdo->prepare("DELETE FROM productos_presentaciones WHERE producto_id = ?")
        ->execute([$pid]);
    // Luego eliminamos el producto
    $pdo->prepare("DELETE FROM productos WHERE id = ?")
        ->execute([$pid]);

    header("Location: productos.php?ok_borrar=1");
    exit;
}

// 4) Si vienen para editar, cargamos los datos existentes
$editar = false;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $editar = true;
    $prod_id = (int)$_GET['editar'];
    // Obtenemos datos del producto
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$prod_id]);
    $productoEdit = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtenemos presentaciones asociadas
    $stmt2 = $pdo->prepare("
      SELECT presentacion_id 
      FROM productos_presentaciones 
      WHERE producto_id = ?
    ");
    $stmt2->execute([$prod_id]);
    $presentacionesSelect = array_column($stmt2->fetchAll(PDO::FETCH_ASSOC), 'presentacion_id');
}

// 5) Listar todos los productos (para mostrar en tabla), incluyendo densidad
$productosAll = $pdo->query("
  SELECT 
    p.id, 
    p.nombre, 
    p.densidad_kg_por_l, 
    u.nombre AS quien_creo
  FROM productos p
  JOIN usuarios u ON p.creado_por = u.id
  ORDER BY p.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

// 5.1) NUEVO — Mapa de kits para render informativo por producto
$kitsMap = [];
$KQ = $pdo->query("
  SELECT 
    pk.producto_id,
    pr.nombre  AS presentacion,
    pr.id      AS presentacion_id,
    ic.nombre  AS insumo,
    pk.cantidad
  FROM packaging_kits pk
  JOIN presentaciones pr       ON pr.id = pk.presentacion_id
  JOIN insumos_comerciales ic  ON ic.id = pk.insumo_comercial_id
  ORDER BY pr.volumen_ml, pr.nombre, ic.nombre
");
while ($r = $KQ->fetch(PDO::FETCH_ASSOC)) {
  $pid = (int)$r['producto_id'];
  $pres = $r['presentacion'];
  $kitsMap[$pid][$pres][] = ['insumo'=>$r['insumo'], 'cantidad'=>$r['cantidad']];
}


// 6) Listar todas las presentaciones disponibles
$presentacionesAllRaw = $pdo->query("
  SELECT id, nombre, volumen_ml, slug
  FROM presentaciones
  ORDER BY (volumen_ml IS NULL), volumen_ml, nombre
")->fetchAll(PDO::FETCH_ASSOC);
$presentacionesAll = filtraPresentaciones($presentacionesAllRaw);

// 6.1) NUEVO — Insumos comerciales para armar kits
$insumosAll = $pdo->query("
  SELECT id, nombre FROM insumos_comerciales
  WHERE activo = 1 ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

// 6.2) NUEVO — Kits existentes del producto en edición (si aplica)
$kitsPorPres = [];
if ($editar) {
  $stK = $pdo->prepare("
    SELECT presentacion_id, insumo_comercial_id, cantidad
    FROM packaging_kits
    WHERE producto_id = ?
    ORDER BY presentacion_id, id
  ");
  $stK->execute([$productoEdit['id']]);
  while ($r = $stK->fetch(PDO::FETCH_ASSOC)) {
    $pid = (int)$r['presentacion_id'];
    $kitsPorPres[$pid][] = [
      'insumo_id' => (int)$r['insumo_comercial_id'],
      'cantidad'  => (float)$r['cantidad']
    ];
  }
}

/**
 * Renderiza tarjeta con la receta (ficha) del producto.
 * Muestra cabecera (lote mínimo, unidad, instrucciones) y líneas de MP.
 */
function render_receta_card(PDO $pdo, int $productoId): string {
    // Buscar ficha (si hay varias, tomamos la más reciente)
    $stmt = $pdo->prepare("SELECT id, lote_minimo, unidad_produccion, instrucciones
                           FROM fichas_produccion
                           WHERE producto_id = ?
                           ORDER BY id DESC LIMIT 1");
    $stmt->execute([$productoId]);
    $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ficha) {
        // No hay ficha aún
        $urlNueva = "ficha_editar.php?producto_id=".$productoId;
        return '
          <div class="card my-2">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Receta</h6>
                <a class="btn btn-sm btn-primary" href="'.$urlNueva.'">Crear receta</a>
              </div>
              <p class="text-muted mb-0 mt-2">Este producto aún no tiene ficha de producción.</p>
            </div>
          </div>';
    }
    // Traer líneas
    $stmt = $pdo->prepare("SELECT fm.id, fm.mp_id, mp.nombre AS materia, fm.porcentaje_o_gramos AS valor
                           FROM ficha_mp fm
                           JOIN materias_primas mp ON mp.id = fm.mp_id
                           WHERE fm.ficha_id = ?
                           ORDER BY fm.id");
    $stmt->execute([$ficha['id']]);
    $lineas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unidad = strtolower(trim($ficha['unidad_produccion'] ?? 'g'));
    $unidadTxt = in_array($unidad, ['g','kg']) ? $unidad : '%';

    ob_start(); ?>
      <div class="card my-2">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Receta (lote mínimo: <?=htmlspecialchars($ficha['lote_minimo'])?> <?=htmlspecialchars($unidad)?>)</h6>
            <a class="btn btn-sm btn-outline-primary" href="ficha_editar.php?ficha_id=<?=$ficha['id']?>">Editar receta</a>
          </div>
          <?php if (!empty($ficha['instrucciones'])): ?>
            <p class="mt-2 mb-2"><strong>Instrucciones:</strong> <?=nl2br(htmlspecialchars($ficha['instrucciones']))?></p>
          <?php endif; ?>
          <ul class="mb-0">
            <?php foreach ($lineas as $l): ?>
              <li><?=htmlspecialchars($l['materia'])?> — <em><?=rtrim(rtrim(number_format((float)$l['valor'], 2, '.', ''), '0'), '.')?></em> <?=htmlspecialchars($unidadTxt)?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>

    <?php
    return ob_get_clean();
}
?>
<?php include 'header.php'; ?>
<div class="container mt-4">
        <h3 class="text-danger mb-3">Catálogo de Productos</h3>

  <!-- Mensajes de éxito -->
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Se guardó el producto correctamente.</div>
  <?php endif; ?>
  <?php if (isset($_GET['ok_borrar'])): ?>
    <div class="alert alert-success">Producto eliminado correctamente.</div>
  <?php endif; ?>

  <!-- 1. Formulario para crear/editar producto -->
  <div class="card mb-4 p-3">
    <h5><?= $editar ? "Editar Producto" : "Nuevo Producto" ?></h5>
    <form method="POST">
      <?php if ($editar): ?>
        <input type="hidden" name="producto_id" value="<?= $productoEdit['id'] ?>">
      <?php endif; ?>

      <div class="row mb-2">
        <div class="col-md-6">
          <label>Nombre del producto</label>
          <input 
            type="text" 
            name="nombre" 
            class="form-control" 
            value="<?= $editar ? htmlspecialchars($productoEdit['nombre']) : "" ?>" 
            required
          >
        </div>
        
        <div class="col-md-3">
          <label>Unidad de venta</label>
          <select name="unidad_venta" class="form-select">
            <?php 
              $uvEdit = $editar ? ($productoEdit['unidad_venta'] ?? 'g') : 'g';
              $opts = [
                'g' => 'Gramos (g)',
                'kg'=> 'Kilogramos (kg)',
                'l' => 'Volumen (Litros/Galones/Cubetas)'
              ];
              foreach ($opts as $k=>$lbl):
            ?>
              <option value="<?= $k ?>" <?= $uvEdit === $k ? 'selected':'' ?>>
                <?= $lbl ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="text-muted">Si vendes por envase (1 L, gal, cubeta) elige “Volumen”.</small>
        </div>
        
        <div class="col-md-3">
          <label>Unidad de venta</label>
          <?php $uvEdit = $editar ? ($productoEdit['unidad_venta'] ?? 'g') : 'g'; ?>
          <select name="unidad_venta" class="form-select">
            <option value="g"  <?= $uvEdit==='g'  ? 'selected' : '' ?>>Gramos (g)</option>
            <option value="kg" <?= $uvEdit==='kg' ? 'selected' : '' ?>>Kilogramos (kg)</option>
            <option value="l"  <?= $uvEdit==='l'  ? 'selected' : '' ?>>Volumen (envases)</option>
          </select>
          <small class="text-muted">Si vendes en envases 30–20000 ml, usa “Volumen”.</small>
        </div>
        <div class="col-md-3">
          <label>Densidad (kg/L) <small class="text-muted">(opcional)</small></label>
          <input 
            type="number" 
            step="0.0001" 
            name="densidad" 
            class="form-control" 
            value="<?= $editar && $productoEdit['densidad_kg_por_l'] !== null 
                      ? htmlspecialchars($productoEdit['densidad_kg_por_l']) 
                      : "" 
                   ?>"
            placeholder="0.9000"
          >
        </div>
      </div>

      <label>Presentaciones disponibles</label>
      <div class="row mb-3">
        <?php foreach ($presentacionesAll as $pr): ?>
          <div class="form-check col-md-2">
            <input 
              type="checkbox" 
              class="form-check-input" 
              name="presentaciones[]" 
              id="pres_<?= $pr['id'] ?>" 
              value="<?= $pr['id'] ?>"
              <?= $editar && in_array($pr['id'], $presentacionesSelect ?? []) 
                  ? "checked" 
                  : "" 
              ?>
            >
            <label class="form-check-label" for="pres_<?= $pr['id'] ?>">
              <?= htmlspecialchars($pr['nombre']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!defined('KITS_SECTION_RENDERED')): define('KITS_SECTION_RENDERED', true); ?>
        <!-- Empaque y etiquetas por presentación (solo una vez) -->
        <hr class="my-3">
        <h6>Empaque y etiquetas por presentación</h6>
        <p class="text-muted">Define el <strong>kit por unidad</strong> (envase, tapa, sello, etiqueta, folleto, etc.) para cada presentación. Solo se muestran las presentaciones marcadas arriba.</p>

        <div id="kits-container">
        <?php foreach ($presentacionesAll as $pr):
              // NO crear card para "Gramos"
              if (isset($pr['slug']) && strtolower($pr['slug']) === 'gramos') { continue; }
              $presId = (int)$pr['id'];
              $rows   = $kitsPorPres[$presId] ?? [['insumo_id'=>0,'cantidad'=>0]];
        ?>
          <div class="card mb-3 kit-card d-none" id="kit-card-<?= $presId ?>">
            <div class="card-header py-2"><strong><?= htmlspecialchars($pr['nombre']) ?></strong></div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm align-middle">
                  <thead><tr><th style="width:55%">Insumo comercial</th><th style="width:20%">Cantidad / unidad</th><th></th></tr></thead>
                  <tbody id="kit-rows-<?= $presId ?>">
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td>
                        <select name="kit[<?= $presId ?>][insumo_id][]" class="form-select">
                          <option value="0">— Selecciona —</option>
                          <?php foreach ($insumosAll as $ins): ?>
                           <option value="<?= (int)$ins['id'] ?>" <?= ((int)($row['insumo_id']??0)===(int)$ins['id'])?'selected':'' ?>>
                              <?= htmlspecialchars($ins['nombre']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </td>
                      <td><input type="number" step="0.01" min="0" class="form-control" name="kit[<?= $presId ?>][cantidad][]" value="<?= htmlspecialchars($row['cantidad']??0) ?>"></td>
                      <td><button type="button" class="btn btn-sm btn-outline-secondary" onclick="addKitRow(<?= $presId ?>)">+ Añadir</button></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div><!-- /#kits-container -->
      <?php endif; ?>
      <script>
      function addKitRow(presId){
        const tbody = document.getElementById('kit-rows-'+presId);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <select name="kit[${presId}][insumo_id][]" class="form-select">
              <option value="0">— Selecciona —</option>
              <?php foreach ($insumosAll as $ins): ?>
                <option value="<?= (int)$ins['id'] ?>"><?= htmlspecialchars($ins['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input type="number" step="0.01" min="0" class="form-control" name="kit[${presId}][cantidad][]" value="1"></td>
          <td><button type="button" class="btn btn-sm btn-outline-secondary" onclick="addKitRow(${presId})">+ Añadir</button></td>
        `;
        tbody.appendChild(tr);
      }
      // Mostrar/ocultar cards de kit según el check de presentación
      document.addEventListener('DOMContentLoaded', function(){
        const container = document.getElementById('kits-container');
        const checks    = document.querySelectorAll('input[name="presentaciones[]"]');
        // Si por error hay cards duplicados fuera del contenedor principal, elimínalos
        document.querySelectorAll('.kit-card').forEach(card=>{
          if (container && !container.contains(card)) { card.remove(); }
        });
        function sync(){
          // Oculta todos los cards y muestra solo los marcados
          container.querySelectorAll('.kit-card').forEach(card => card.classList.add('d-none'));
          checks.forEach(chk=>{
            const card = container.querySelector('#kit-card-'+chk.value);
            if (card) card.classList.toggle('d-none', !chk.checked);
          });
        }
        checks.forEach(chk=>chk.addEventListener('change', sync));
        sync(); // estado inicial
      });
      </script>

      <button name="guardar_producto" class="btn btn-primary">
        <?= $editar ? "Actualizar Producto" : "Crear Producto" ?>
      </button>
      <?php if ($editar): ?>
        <a href="productos.php" class="btn btn-secondary ms-2">Cancelar</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- 2. Tabla con los productos ya creados -->
  <h5>Listado de productos</h5>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>Producto</th>
        <th>Densidad (kg/L)</th>
        <th>Creado por</th>
        <th>Presentaciones</th>
        <th style="width:220px">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($productosAll as $prod):
          // Obtener presentaciones asociadas para mostrar en texto
          $stmt3 = $pdo->prepare("
            SELECT pr.nombre 
            FROM presentaciones pr
            JOIN productos_presentaciones pp ON pr.id = pp.presentacion_id
            WHERE pp.producto_id = ?
          ");
          $stmt3->execute([$prod['id']]);
          $presList = array_column($stmt3->fetchAll(PDO::FETCH_ASSOC), 'nombre');
      ?>
        <tr>
          <td><?= htmlspecialchars($prod['nombre']) ?></td>
          <td>
            <?= $prod['densidad_kg_por_l'] !== null 
                ? htmlspecialchars($prod['densidad_kg_por_l']) 
                : "—" ?>
          </td>
          <td><?= htmlspecialchars($prod['quien_creo']) ?></td>
          <td><?= !empty($presList) ? implode(", ", $presList) : "—" ?></td>
          <td>
            <a href="productos.php?editar=<?= $prod['id'] ?>" class="btn btn-sm btn-secondary">Editar</a>
            <a 
              href="productos.php?borrar=<?= $prod['id'] ?>" 
              onclick="return confirm('¿Seguro que quieres eliminar este producto?')"
              class="btn btn-sm btn-danger ms-1"
            >Eliminar</a>
          <button type="button" class="btn btn-sm btn-outline-primary ms-1"
                  onclick="document.getElementById('kits-row-<?= $prod['id'] ?>').classList.toggle('d-none')">
            Ver empaques
          </button>
          &nbsp;·&nbsp;
          <a href="#" class="text-primary small" onclick="return toggleBox('receta-<?= (int)$prod['id'] ?>')">Ver receta</a>
          <div id="receta-<?= (int)$prod['id'] ?>" class="mt-2 d-none">
            <?= render_receta_card($pdo, (int)$prod['id']); ?>
          </div>
          
          </td>
        </tr>
      <!-- NUEVO: detalle de presentaciones + insumos comerciales (kits) -->
      <tr id="kits-row-<?= $prod['id'] ?>" class="d-none bg-light">
        <td colspan="5">
          <?php 
            $map = $kitsMap[$prod['id']] ?? [];
            if (empty($map)): 
          ?>
            <div class="text-muted">No hay kits configurados para este producto.</div>
          <?php else: ?>
            <div class="row">
              <?php foreach ($map as $presNombre => $items): ?>
                <div class="col-md-6 mb-3">
                  <div class="card">
                    <div class="card-header py-2"><strong><?= htmlspecialchars($presNombre) ?></strong></div>
                    <div class="card-body p-2">
                      <ul class="mb-0">
                        <?php foreach ($items as $it): ?>
                          <li><?= htmlspecialchars($it['insumo']) ?> — <em><?= number_format((float)$it['cantidad'], 2) ?> pza/unidad</em></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
  function toggleBox(id){
    var el = document.getElementById(id);
    if(!el) return false;
    el.classList.toggle('d-none');
    return false;
  }
</script>

<?php include 'footer.php'; ?>
