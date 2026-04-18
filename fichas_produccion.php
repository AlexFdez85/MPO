<?php
require_once 'config.php';

// Solo Admin puede entrar a esta página
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// 0) GUARDAR PACKAGING KIT (si llega desde la sección de packaging)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_kit'])) {
    $producto_id     = (int)($_POST['producto_id'] ?? 0);
    $presentacion_id = (int)($_POST['presentacion_id'] ?? 0);
    $insumo_ids      = $_POST['insumo_id'] ?? [];
    $cantidades      = $_POST['cantidad']  ?? [];

    if ($producto_id <= 0 || $presentacion_id <= 0) {
        die('Datos inválidos para guardar el kit de packaging.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM packaging_kits WHERE producto_id = ? AND presentacion_id = ?")
            ->execute([$producto_id, $presentacion_id]);

        $ins = $pdo->prepare("
            INSERT INTO packaging_kits (producto_id, presentacion_id, insumo_comercial_id, cantidad)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($insumo_ids as $i => $insumo_id) {
            $cid = (int)$insumo_id;
            $qty = (float)($cantidades[$i] ?? 0);
            if ($cid > 0 && $qty > 0) {
                $ins->execute([$producto_id, $presentacion_id, $cid, $qty]);
            }
        }

        $pdo->commit();
        header("Location: fichas_produccion.php?ok_kit=1&producto_id={$producto_id}");
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        die('Error al guardar kit: ' . $e->getMessage());
    }
}


// 1) PROCESAR GUARDADO DE LA FICHA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1.1. Capturar el nombre libre del producto a fabricar
    $nombre_producto = trim($_POST['nombre_producto']);
    $usuario_id      = $_SESSION['user_id'];

    if ($nombre_producto === '') {
        die("Error: Debes indicar un nombre para el producto a fabricar.");
    }

    // 1.2. Comprobar si ya existe el producto en la tabla `productos`
    $stmt0 = $pdo->prepare("SELECT id FROM productos WHERE nombre = ?");
    $stmt0->execute([$nombre_producto]);
    $filaProd = $stmt0->fetch(PDO::FETCH_ASSOC);

    // 1.2.1. ¿Es “de venta”?
    $esParaVenta   = isset($_POST['para_venta']) ? 1 : 0;        // 1 = producto de venta
    // ←– NUEVO: ¿Es subproceso?
    $esSubproceso  = isset($_POST['es_subproducto']) ? 1 : 0;     // 1 = producto subproceso

    if ($filaProd) {
        // El producto ya existía: obtenemos su ID y actualizamos banderas
        $producto_id = $filaProd['id'];

        // Si la casilla “Para Venta” está marcada, forzamos es_para_venta = 1
        if ($esParaVenta) {
            $pdo->prepare("UPDATE productos SET es_para_venta = 1 WHERE id = ?")
                ->execute([$producto_id]);
        }
        // ←– Si la casilla “Es Subproceso” está marcada, forzamos es_subproducto = 1
        if ($esSubproceso) {
            $pdo->prepare("UPDATE productos SET es_subproducto = 1 WHERE id = ?")
                ->execute([$producto_id]);
        }
    } else {
        // El producto NO existía: lo creamos e insertamos ambas banderas
        $stmtNuevo = $pdo->prepare("
          INSERT INTO productos 
            (nombre, densidad_kg_por_l, creado_por, es_para_venta, es_subproducto)
          VALUES (?, NULL, ?, ?, ?)
        ");
        $stmtNuevo->execute([
            $nombre_producto,
            $usuario_id,
            $esParaVenta,
            $esSubproceso
        ]);
        $producto_id = $pdo->lastInsertId();
    }

    // 1.3. Capturar lote mínimo y unidad de lote
    $lote_minimo   = floatval($_POST['lote_minimo']);
    $unidad_lote   = $_POST['unidad']; // 'kg' o 'g'
    $instrucciones = trim($_POST['instrucciones']);

    if ($lote_minimo <= 0) {
        die("Error: El lote mínimo debe ser un número mayor que cero.");
    }

    // 1.4. Insertar registro en fichas_produccion
    $pdo->beginTransaction();
    $stmt1 = $pdo->prepare("
      INSERT INTO fichas_produccion 
        (producto_id, lote_minimo, unidad_produccion, instrucciones, creado_por)
      VALUES (?, ?, ?, ?, ?)
    ");
    $stmt1->execute([
        $producto_id,
        $lote_minimo,
        $unidad_lote,
        $instrucciones,
        $usuario_id
    ]);
    $ficha_id = $pdo->lastInsertId();

    // 1.5. Insertar cada materia prima en gramos dentro de ficha_mp
    foreach ($_POST['mp_id'] as $i => $mp_id) {
        $cantidad_gramos = floatval($_POST['cantidad_gramos'][$i]);
        if ($mp_id && $cantidad_gramos > 0) {
            $stmt2 = $pdo->prepare("
              INSERT INTO ficha_mp (ficha_id, mp_id, porcentaje_o_gramos)
              VALUES (?, ?, ?)
            ");
            $stmt2->execute([$ficha_id, $mp_id, $cantidad_gramos]);
        }
    }

    // 1.6. Si es producto para venta, asignar todas las presentaciones por defecto
    if ($esParaVenta) {
        // Obtener todos los IDs de presentaciones
        $todasPres = $pdo
            ->query("SELECT id FROM presentaciones")
            ->fetchAll(PDO::FETCH_COLUMN, 0);
        $stmtInsPres = $pdo->prepare("
          INSERT INTO productos_presentaciones (producto_id, presentacion_id)
          VALUES (?, ?)
        ");
        foreach ($todasPres as $pid) {
            $stmtInsPres->execute([$producto_id, $pid]);
        }
        // Si prefieres asignar manualmente las presentaciones, comenta este bloque
    }

    $pdo->commit();
    header("Location: fichas_produccion.php?ok=1&producto_id=".$producto_id."&show=packaging");
    exit;
}

// 2) Listar todas las materias primas (activo=1)
$mp = $pdo->query("
    SELECT id, nombre, unidad
      FROM materias_primas
     WHERE activo = 1
  ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$sub = $pdo->query("
    SELECT
      id + 100000       AS id,      /* para evitar choque con MP */
      nombre,
      'g'               AS unidad   /* o la unidad que use tu subproceso */
    FROM productos
   WHERE es_subproducto = 1
  ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);

$mp = array_merge($mp, $sub);

?>
<?php include 'header.php'; ?>
<div class="container mt-4">
  <h3 class="text-danger mb-3">Crear Ficha de Producción</h3>

  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">Ficha registrada correctamente.</div>
  <?php endif; ?>

  <form method="POST">
    <!-- ==================== -->
    <!--  PRODUCTO A FABRICAR -->
    <!-- ==================== -->
    <div class="row mb-3">
      <div class="col-md-8">
        <label>Producto a fabricar</label>
        <input 
          type="text" 
          name="nombre_producto" 
          class="form-control" 
          placeholder="Ej. Fondo de Relleno Gris" 
          required
        >
        <small class="text-muted">
          Si no existe en el catálogo, se creará con este nombre.  
          Luego podrás editar densidad y presentaciones en “Productos”.
        </small>
      </div>

      <div class="col-md-2">
        <label>Lote mínimo (en)</label>
        <input 
          name="lote_minimo" 
          type="number" 
          step="0.01" 
          class="form-control" 
          placeholder="Ej. 25" 
          required
        >
      </div>

      <div class="col-md-2">
        <label>Unidad de lote</label>
        <select name="unidad" class="form-select" required>
          <option value="kg">Kilogramos</option>
          <option value="g">Gramos</option>
        </select>
      </div>
    </div>

    <!-- ==================== -->
    <!--  CASILLA “PARA VENTA” -->
    <!-- ==================== -->
    <div class="form-check mb-3">
      <input 
        type="checkbox" 
        class="form-check-input" 
        name="para_venta" 
        id="para_venta"
      >
      <label class="form-check-label" for="para_venta">
        <strong>Este producto es terminado y se venderá directamente</strong>
      </label>
      <small class="form-text text-muted">
        Si marcas esto, el sistema lo agregará automáticamente al catálogo de venta  
        (se le asignarán todas las presentaciones por defecto).  
        Luego podrás ajustar las presentaciones específicas en 
        <a href="productos.php">Productos</a>.
      </small>
    </div>

    <!-- ============================= -->
    <!-- CASILLA “ES SUBPROCESO” (NUEVO) -->
    <!-- ============================= -->
    <div class="form-check mb-3">
      <input 
        type="checkbox" 
        class="form-check-input" 
        name="es_subproducto" 
        id="es_subproducto"
      >
      <label class="form-check-label" for="es_subproducto">
        <strong>Este producto es un subproceso</strong>
        <small class="text-muted">
          (se usará como insumo en otro producto)
        </small>
      </label>
    </div>

    <!-- ==================== -->
    <!--   INSTRUCCIONES (OPC) -->
    <!-- ==================== -->
    <label>Instrucciones (opcional)</label>
    <textarea 
      name="instrucciones" 
      class="form-control mb-4" 
      rows="2"
      placeholder="Ej. Mezclar con agitación lenta por 10 minutos..."
    ></textarea>

    <!-- ==================== -->
    <!--  TABLA DE MATERIAS PRIMAS -->
    <!-- ==================== -->
    <h5>Materias primas (en gramos)</h5>
    <table class="table" id="tablaMP">
      <thead>
        <tr>
          <th style="width: 50%;">Materia Prima</th>
          <th style="width: 20%;">Unidad</th>
          <th style="width: 20%;">Cantidad en gramos</th>
          <th style="width: 10%;"></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <select name="mp_id[]" class="form-select" required>
              <option value="">Selecciona</option>
              <?php foreach ($mp as $m): ?>
                <option value="<?= $m['id'] ?>">
                  <?= htmlspecialchars($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="unidad-texto"><?= $mp[0]['unidad'] ?? '' ?></td>
          <td>
            <input 
              type="number" 
              step="0.01" 
              name="cantidad_gramos[]" 
              class="form-control" 
              placeholder="Ej. 7540" 
              required
            >
          </td>
          <td>
            <button 
              type="button" 
              class="btn btn-danger btn-sm" 
              onclick="eliminarFila(this)"
            >✖</button>
          </td>
        </tr>
      </tbody>
    </table>

    <button 
      type="button" 
      class="btn btn-outline-primary btn-sm mb-4" 
      onclick="agregarFila()"
    >+ Añadir fila</button>

    <button class="btn btn-primary">Guardar ficha</button>
  </form>
  
  <!-- ============================= -->
  <!--  PACKAGING: Envases/Etiquetas -->
  <!-- ============================= -->
<?php
$productoSel = (int)($_GET['producto_id'] ?? 0);
if ($productoSel > 0):
  // Presentaciones asignadas al producto
  $presStmt = $pdo->prepare("
    SELECT p.id, p.nombre, p.slug
      FROM productos_presentaciones pp
      JOIN presentaciones p ON p.id = pp.presentacion_id
     WHERE pp.producto_id = ?
     ORDER BY p.id
  ");
  $presStmt->execute([$productoSel]);
  $presentaciones = $presStmt->fetchAll(PDO::FETCH_ASSOC);

  // Catálogo de insumos comerciales activos
  $insumos = $pdo->query("
    SELECT id, CONCAT(COALESCE(tipo,''),' - ', nombre) AS nombre
      FROM insumos_comerciales
     WHERE activo = 1
     ORDER BY tipo, nombre
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (!$presentaciones): ?>
    <div class="alert alert-warning mt-4">
      Este producto no tiene presentaciones asignadas aún.
      Asígnalas en <em>productos_presentaciones</em> para poder definir su kit de packaging.
    </div>
  <?php else: ?>
    <hr class="my-4">
    <h4 class="mb-3">Packaging (envases y etiquetas) por presentación</h4>
    <?php if (isset($_GET['ok_kit'])): ?>
      <div class="alert alert-success">Kit de packaging guardado.</div>
    <?php endif; ?>

    <?php foreach ($presentaciones as $pres):
      $presId = (int)$pres['id'];
      // Cargar kit existente
      $kitStmt = $pdo->prepare("
        SELECT pk.insumo_comercial_id, pk.cantidad, ic.nombre AS insumo_nombre, ic.tipo
          FROM packaging_kits pk
          JOIN insumos_comerciales ic ON ic.id = pk.insumo_comercial_id
         WHERE pk.producto_id = ? AND pk.presentacion_id = ?
         ORDER BY ic.tipo, ic.nombre
      ");
      $kitStmt->execute([$productoSel, $presId]);
      $kitRows = $kitStmt->fetchAll(PDO::FETCH_ASSOC);
      $tablaId = 'tablaKit_' . $presId;
    ?>
      <div class="card mb-4">
        <div class="card-header">
          <strong>Presentación:</strong> <?= htmlspecialchars($pres['nombre']) ?>
        </div>
       <div class="card-body">
          <form method="POST">
            <input type="hidden" name="guardar_kit" value="1">
            <input type="hidden" name="producto_id" value="<?= $productoSel ?>">
            <input type="hidden" name="presentacion_id" value="<?= $presId ?>">

            <div class="table-responsive">
              <table class="table table-sm align-middle" id="<?= $tablaId ?>">
                <thead>
                  <tr>
                    <th style="width:70%">Insumo comercial</th>
                    <th style="width:20%">Cantidad</th>
                    <th style="width:10%"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($kitRows): foreach ($kitRows as $r): ?>
                  <tr>
                    <td>
                      <select name="insumo_id[]" class="form-select">
                        <option value="">-- seleccionar --</option>
                        <?php foreach ($insumos as $opt): ?>
                          <option value="<?= (int)$opt['id'] ?>" <?= ((int)$opt['id'] === (int)$r['insumo_comercial_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opt['nombre']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <input type="number" name="cantidad[]" class="form-control" step="0.01" min="0" value="<?= (float)$r['cantidad'] ?>">
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaKit('<?= $tablaId ?>', this)">✖</button>
                    </td>
                  </tr>
                  <?php endforeach; else: ?>
                  <tr>
                    <td>
                      <select name="insumo_id[]" class="form-select">
                        <option value="">-- seleccionar --</option>
                        <?php foreach ($insumos as $opt): ?>
                          <option value="<?= (int)$opt['id'] ?>">
                            <?= htmlspecialchars($opt['nombre']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <input type="number" name="cantidad[]" class="form-control" step="0.01" min="0" value="1">
                    </td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" onclick="eliminarFilaKit('<?= $tablaId ?>', this)">✖</button>
                    </td>
                  </tr>
                 <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-outline-primary btn-sm" onclick="agregarFilaKit('<?= $tablaId ?>')">+ Añadir fila</button>
              <button class="btn btn-success btn-sm">Guardar kit de <?= htmlspecialchars($pres['nombre']) ?></button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
  
</div>

<script>
// Duplica la primera fila para añadir otra materia prima
function agregarFila() {
  const row = document.querySelector("#tablaMP tbody tr");
  const nueva = row.cloneNode(true);
  // Limpiar inputs de la nueva fila
  nueva.querySelectorAll("input").forEach(input => input.value = "");
  row.parentNode.appendChild(nueva);
}
// Elimina la fila correspondiente, si queda al menos 1
function eliminarFila(btn) {
  const filas = document.querySelectorAll("#tablaMP tbody tr");
  if (filas.length > 1) {
    btn.closest("tr").remove();
  }
}


// ===== Helpers Packaging =====
function agregarFilaKit(tablaId) {
  const tabla = document.getElementById(tablaId);
  if (!tabla) return;
  const row = tabla.querySelector("tbody tr");
  const nueva = row.cloneNode(true);
  nueva.querySelectorAll("input").forEach(i => i.value = "");
  const sel = nueva.querySelector("select");
  if (sel) sel.selectedIndex = 0;
  tabla.querySelector("tbody").appendChild(nueva);
}
function eliminarFilaKit(tablaId, btn) {
  const tabla = document.getElementById(tablaId);
  const filas = tabla.querySelectorAll("tbody tr");
  if (filas.length > 1) {
    btn.closest("tr").remove();
  }
}
</script>
<?php include 'footer.php'; ?>
