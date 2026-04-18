<?php
// ordenes_compra.php
require_once 'config.php';
// Asegura try/catch global (antes no-AJAX también lo usa)
try {
    // Sesión + permiso
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente','logistica','produccion'], true)) {
        header('Location: dashboard.php'); exit;
    }
    $usuario_id = (int)$_SESSION['user_id'];

    // =======================
    // AJAX: últimos precios por proveedor (mp/ic)
    // =======================
    if (isset($_GET['ajax']) && $_GET['ajax']==='lastprices') {
        header('Content-Type: application/json; charset=utf-8');
        $provId = isset($_GET['proveedor_id']) ? (int)$_GET['proveedor_id'] : 0;
        if ($provId <= 0) { echo json_encode([]); exit; }
        // MP: último precio por (proveedor_id, mp_id)
        $sqlMp = "
          SELECT t.mp_id, lc.precio_unitario
          FROM lineas_compra lc
          JOIN (
            SELECT lc2.mp_id, MAX(lc2.id) AS max_id
            FROM lineas_compra lc2
            JOIN ordenes_compra oc2 ON oc2.id = lc2.orden_compra_id
            WHERE oc2.proveedor_id = ? AND lc2.mp_id IS NOT NULL
            GROUP BY lc2.mp_id
          ) t ON t.max_id = lc.id
        ";
        $stmt = $pdo->prepare($sqlMp); $stmt->execute([$provId]);
        $mp = [];
        foreach ($stmt as $r) $mp['mp_'.$r['mp_id']] = (float)$r['precio_unitario'];
        // IC: último precio por (proveedor_id, ic_id)
        $sqlIc = "
          SELECT t.ic_id, lc.precio_unitario
          FROM lineas_compra lc
          JOIN (
            SELECT lc2.ic_id, MAX(lc2.id) AS max_id
            FROM lineas_compra lc2
            JOIN ordenes_compra oc2 ON oc2.id = lc2.orden_compra_id
            WHERE oc2.proveedor_id = ? AND lc2.ic_id IS NOT NULL
            GROUP BY lc2.ic_id
          ) t ON t.max_id = lc.id
        ";
        $stmt = $pdo->prepare($sqlIc); $stmt->execute([$provId]);
        $ic = [];
        foreach ($stmt as $r) $ic['ic_'.$r['ic_id']] = (float)$r['precio_unitario'];
        echo json_encode(['mp'=>$mp,'ic'=>$ic], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // AJAX: información de producto (densidad, unidad, flag)
    //   GET params:
    //     ajax=prodinfo
    //     type=mp|ic
    //     id=<int>
    //   Respuesta:
    //     { ok: true, type: "mp|ic", id: 123,
    //       nombre: "Xilol",
    //       densidad_kg_l: 0.8700,
    //       unidad_compra: "kg",     // 'g' | 'kg' | 'L' | 'pza'
    //       es_solvente: 1 }
    // ======================================================
    if (isset($_GET['ajax']) && $_GET['ajax'] === 'prodinfo') {
        header('Content-Type: application/json; charset=utf-8');
        $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
        $id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;
        if (!$id || !in_array($type, ['mp','ic'], true)) {
            echo json_encode(['ok'=>false,'error'=>'params']); exit;
        }
        try {
            if ($type === 'mp') {
                $sql = "SELECT id, nombre, densidad_kg_l, unidad_compra, es_solvente
                          FROM materias_primas
                         WHERE id = ?";
            } else { // ic
                $sql = "SELECT id, nombre, densidad_kg_l, unidad_compra, es_solvente
                          FROM insumos_comerciales
                         WHERE id = ?";
            }
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
            if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                echo json_encode(['ok'=>true,'type'=>$type] + $row, JSON_UNESCAPED_UNICODE); exit;
            }
            echo json_encode(['ok'=>false,'error'=>'not_found']); exit;
        } catch (Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;
        }
    }

    // 2) Proveedores activos
    $proveedores = $pdo
      ->query("SELECT id, nombre FROM proveedores WHERE activo=1 ORDER BY nombre")
      ->fetchAll(PDO::FETCH_ASSOC);

    $prov   = null;
    $provId = null;
    
        // Si viniste filtrando productos por ?proveedor_id=…
    if (isset($_GET['proveedor_id'])) {
        $provId = intval($_GET['proveedor_id']);
    }
    // O si estás en el POST de creación (name="proveedor_id")
    elseif (isset($_POST['proveedor_id'])) {
        $provId = intval($_POST['proveedor_id']);
    }

    // Si tenemos ID, lo leo de la tabla
    if ($provId) {
        $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = ?");
        $stmt->execute([$provId]);
        $prov = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3) Mapa proveedor → MP + IC
    $map = [];
    foreach ($pdo->query("
        SELECT pm.proveedor_id AS prov_id,
               mp.id            AS prod_id,
               mp.nombre        AS prod_nombre
          FROM proveedores_mp pm
          JOIN materias_primas mp ON mp.id=pm.mp_id
         WHERE mp.activo=1
         ORDER BY mp.nombre
    ") as $r) {
        $map[$r['prov_id']]['mp'][] = [
          'id'     => (int)$r['prod_id'],
          'nombre' => $r['prod_nombre'],
        ];
    }
    foreach ($pdo->query("
        SELECT ic.proveedor_id,
               ic.id       AS prod_id,
               ic.nombre   AS prod_nombre
          FROM insumos_comerciales ic
         WHERE ic.activo=1
         ORDER BY ic.nombre
    ") as $r) {
        $map[$r['proveedor_id']]['ic'][] = [
          'id'     => (int)$r['prod_id'],
          'nombre' => $r['prod_nombre'],
        ];
    }

    // 4) Crear nueva OC y líneas
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_oc'])) {
        // 4.a) Cabecera
        $provId   = intval($_POST['proveedor_id']);
        $fecha_em = $_POST['fecha_emision'] ?: date('Y-m-d');
        $moneda    = isset($_POST['moneda']) ? substr($_POST['moneda'],0,3) : 'MXN';
        $tipoCambio = isset($_POST['tipo_cambio']) ? floatval($_POST['tipo_cambio']) : 1.0;
        $pdo->prepare("
          INSERT INTO ordenes_compra
            (proveedor_id, solicitante_id, fecha_emision, estado, moneda, tipo_cambio)
          VALUES (?, ?, ?, 'pendiente', ?, ?)")->execute([$provId, $usuario_id, $fecha_em, $moneda, $tipoCambio]);
        $ocId = $pdo->lastInsertId();

        // 4.b) Líneas
        $prodIds = $_POST['prod_id'] ?? [];
        $cants   = $_POST['cantidad'] ?? [];
        $prices  = $_POST['precio_unitario'] ?? [];

        $mpValid = array_column($map[$provId]['mp'] ?? [], 'id');
        $icValid = array_column($map[$provId]['ic'] ?? [], 'id');

        $insLine = $pdo->prepare("
          INSERT INTO lineas_compra
            (orden_compra_id, mp_id, ic_id, cantidad, precio_unitario, subtotal, moneda, tipo_cambio, descuento_pct, notas_linea)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
        ");

        foreach ($prodIds as $i => $rawId) {
            $idSel = intval($rawId);
            $cant  = floatval($cants[$i] ?? 0);
            $unit  = floatval($prices[$i] ?? 0);
            if ($cant <= 0 || $unit < 0) continue;

            $mpId = $icId = null;
            if ($_SESSION['rol'] === 'logistica') {
                if (in_array($idSel, $icValid, true)) {
                    $icId = $idSel;
                } else {
                    continue;
                }
            } else {
                if      (in_array($idSel, $mpValid, true)) $mpId = $idSel;
                elseif  (in_array($idSel, $icValid, true)) $icId = $idSel;
                else continue;
            }

            $sub = $cant * $unit;
            $insLine->execute([$ocId, $mpId, $icId, $cant, $unit, $sub, $moneda, $tipoCambio]);
        }

        header("Location: ordenes_compra.php?ok=1");
        exit;
    }

    // 5) Listar OC existentes
    if ($_SESSION['rol'] === 'logistica') {
        $stmt = $pdo->prepare("
          SELECT oc.id, p.nombre AS proveedor,
                 oc.fecha_emision, u.nombre AS solicitante,
                 oc.estado
            FROM ordenes_compra oc
            JOIN proveedores p ON oc.proveedor_id = p.id
            JOIN usuarios    u ON oc.solicitante_id = u.id
           WHERE oc.solicitante_id = ?
           ORDER BY oc.id DESC
        ");
        $stmt->execute([$usuario_id]);
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $ordenes = $pdo->query("
          SELECT oc.id, p.nombre AS proveedor,
                 oc.fecha_emision, u.nombre AS solicitante,
                 oc.estado
            FROM ordenes_compra oc
            JOIN proveedores p ON oc.proveedor_id = p.id
            JOIN usuarios    u ON oc.solicitante_id = u.id
           ORDER BY oc.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    include 'header.php';
    ?>

    <div class="container mt-4">
      <h3 class="text-success mb-3">Órdenes de Compra</h3>
      <?php if(isset($_GET['ok'])): ?>
        <div class="alert alert-success">Orden de compra creada correctamente.</div>
      <?php endif; ?>

      <div class="card mb-4 p-3">
        <h5>Crear nueva Orden de Compra</h5>
        <form method="POST" class="row g-3">
          <div class="col-md-4">
            <label>Proveedor</label>
            <select id="provSelect" name="proveedor_id" class="form-select" required>
              <option value="">-- Selecciona Proveedor --</option>
              <?php foreach($proveedores as $pr): ?>
                <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label>Moneda</label>
            <select name="moneda" class="form-select">
              <option value="MXN" selected>MXN</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
          <div class="col-md-2">
            <label>Tipo de cambio</label>
            <input name="tipo_cambio" type="number" step="0.000001" min="0" class="form-control" value="1.000000">
          </div>
          
 <div class="col-12 mt-3">
    <label class="form-label">Tipo de entrega</label>
    <div class="form-check">
      <input class="form-check-input"
             type="radio" name="entrega_domicilio" id="opt_domicilio"
             value="1"
             <?= ($prov && $prov['entrega_domicilio']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="opt_domicilio">
        Proveedor entrega a domicilio
        <?php if ($prov && $prov['direccion']): ?>
          (<?= htmlspecialchars($prov['direccion']) ?>)
        <?php endif; ?>
      </label>
    </div>
    <div class="form-check">
      <input class="form-check-input"
             type="radio" name="entrega_domicilio" id="opt_recoger"
             value="0"
             <?= ($prov && !$prov['entrega_domicilio']) ? 'checked' : '' ?>>
      <label class="form-check-label" for="opt_recoger">
        Nosotros recogemos en proveedor
      </label>
    </div>
  </div>
          
          <div class="col-md-3">
            <label>Fecha emisión</label>
            <input type="date" name="fecha_emision" class="form-control"
                   value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="col-md-1 text-end">
            <button type="submit" name="crear_oc" class="btn btn-success px-4">Crear</button>
          </div>

          <div class="col-12 mt-4">
            <div class="card p-3">
              <h6>Productos a solicitar</h6>
              <table class="table table-bordered mb-2">
                <thead>
                  <tr>
                    <th style="width:35%">Producto</th>
                    <th style="width:15%">Cant. (g o pzas)</th>
                    <th style="width:20%">Precio unitario</th>
                    <th style="width:20%">Sub-total</th>
                    <th style="width:10%">
                      <button type="button" id="addLinea"
                              class="btn btn-sm btn-outline-primary">Agregar</button>
                    </th>
                  </tr>
                </thead>
                <tbody id="lineasBody"></tbody>
              </table>
              <small class="text-muted">
                Ingresa cantidad y precio; el subtotal se calcula automáticamente.
              </small>
            </div>
          </div>
        </form>
      </div>

      <table class="table table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Proveedor</th>
            <th>Fecha</th>
            <th>Solicita</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($ordenes as $o): ?>
            <tr>
              <td><?= $o['id'] ?></td>
              <td><?= htmlspecialchars($o['proveedor']) ?></td>
              <td><?= $o['fecha_emision'] ?></td>
              <td><?= htmlspecialchars($o['solicitante']) ?></td>
              <td>
                <?php
                  switch($o['estado']) {
                    case 'pendiente':   $c='warning';  break;
                    case 'autorizada':  $c='success';  break;
                    case 'rechazada':   $c='danger';   break;
                    case 'enviada':     $c='primary';  break;
                    default:            $c='secondary';break;
                  }
                ?>
                <span class="badge bg-<?= $c ?>"><?= ucfirst($o['estado']) ?></span>
              </td>
              <td>
                <a href="autorizar_compra.php?view=pendientes&oc=<?= $o['id'] ?>"
                   class="btn btn-sm btn-outline-secondary">Ver / Editar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <script>
    const mapProv = <?= json_encode($map, JSON_UNESCAPED_UNICODE) ?>;
    const role    = '<?= $_SESSION['rol'] ?>';
    const provSelect = document.getElementById('provSelect'),
          bodyLines  = document.getElementById('lineasBody'),
          btnAdd     = document.getElementById('addLinea');
    // Mapa de últimos precios
    let priceMap = { mp:{}, ic:{} };
    let typeIndex = { mp:new Set(), ic:new Set() };


    function addLinea(){
      const prov   = provSelect.value,
            grupos = mapProv[prov] || {},
            mpList = grupos.mp || [],
            icList = grupos.ic || [];

      let opts = '<option value="">-- Elige producto --</option>';
      if (role!=='logistica') {
        if (mpList.length) {
          opts += '<optgroup label="- Materias Primas -">';
          mpList.forEach(p => opts += `<option value="${p.id}">${p.nombre}</option>`);
          opts += '</optgroup>';
        }
      }
      if (icList.length) {
        opts += '<optgroup label="- Insumos Comerciales -">';
        icList.forEach(p => opts += `<option value="${p.id}">${p.nombre}</option>`);
        opts += '</optgroup>';
      }

      const tr = document.createElement('tr');
      tr.setAttribute('data-oc-row','');
      tr.innerHTML = `
        <td>
          <select name="prod_id[]" class="form-select form-select-sm producto-select" required>${opts}</select>
          <div class="form-text text-muted lh-1 d-flex gap-2">
            <span class="densidad-badge d-none">Densidad: <strong class="densidad-val">—</strong> kg/L</span>
          </div>
        </td>
        <td>
          <input name="cantidad[]" type="number" step="0.0001" min="0" class="form-control form-control-sm cantidad-input" placeholder="Cant. (g/kg/L)">
          <div class="form-text text-muted lh-1">
            <span class="litros-tip d-none">≈ <strong class="litros-val">0.00</strong> L</span>
          </div>
        </td>
        <td>
          <input name="precio_unitario[]" type="number" step="0.000001" min="0" class="form-control form-control-sm">
          <div class="form-text text-muted lh-1" data-lastprice></div>
        </td>
        <td class="text-end">0.00</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger">Quitar</button></td>`;
      tr.querySelector('button').onclick = ()=> tr.remove();
      // Al cambiar producto: aplicar precio sugerido
      tr.querySelector('select[name="prod_id[]"]').addEventListener('change', () => applySuggestedPrice(tr));
      // Al cambiar producto: aplicar precio sugerido + densidad
      tr.querySelector('select[name="prod_id[]"]').addEventListener('change', () => {
        applySuggestedPrice(tr);
        fetchProductoInfo(tr);   // <-- NUEVO
      });
      // Al teclear cantidad/precio: recalcula subtotal
      bodyLines.appendChild(tr);
      applySuggestedPrice(tr);
      fetchProductoInfo(tr);
    }
    

    bodyLines.addEventListener('input', e => {
      if (['cantidad[]','precio_unitario[]'].includes(e.target.name)) {
        const tr = e.target.closest('tr'),
              q  = parseFloat(tr.querySelector('[name="cantidad[]"]').value) || 0,
              u  = parseFloat(tr.querySelector('[name="precio_unitario[]"]').value) || 0;
            tr.cells[3].textContent = (q*u).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});
                if (e.target.classList.contains('cantidad-input')) {
                    calcLitros(tr);
            }
        }
    });

    // Construye índice de tipos (mp / ic) para el proveedor actual
    function rebuildTypeIndex() {
      typeIndex = { mp:new Set(), ic:new Set() };
      const prov = provSelect.value, grupos = mapProv[prov] || {};
      (grupos.mp || []).forEach(p => typeIndex.mp.add(String(p.id)));
      (grupos.ic || []).forEach(p => typeIndex.ic.add(String(p.id)));
    }

    // Descarga últimos precios del proveedor
    async function fetchLastPrices() {
      const prov = provSelect.value;
      if (!prov) { priceMap = {mp:{}, ic:{}}; return; }
      try {
        const r = await fetch(`ordenes_compra.php?ajax=lastprices&proveedor_id=${encodeURIComponent(prov)}`, {cache:'no-store'});
        if (r.ok) priceMap = await r.json();
      } catch(e) { /* noop */ }
    }

    // Aplica precio sugerido a una fila y muestra etiqueta "Último: $x"
    function applySuggestedPrice(rowEl) {
      if (!rowEl) return;
      const sel  = rowEl.querySelector('select[name="prod_id[]"]');
      const inp  = rowEl.querySelector('input[name="precio_unitario[]"]');
      const hint = rowEl.querySelector('[data-lastprice]');
      if (!sel || !inp) return;
      const id = sel.value;
      if (!id) { if(hint) hint.textContent=''; return; }
      let key='', val;
      if (typeIndex.mp.has(id)) {
        key = 'mp_'+id;
        val = priceMap.mp ? priceMap.mp[key] : undefined;
     } else if (typeIndex.ic.has(id)) {
        key = 'ic_'+id;
        val = priceMap.ic ? priceMap.ic[key] : undefined;
      }
      if (typeof val === 'number' && !isNaN(val) && val > 0) {
        inp.value = Number(val).toFixed(4);
        if (hint) hint.textContent = `Último: ${Number(val).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:4})}`;
        // recalcula subtotal
        const q  = parseFloat(rowEl.querySelector('[name="cantidad[]"]').value) || 0;
        rowEl.cells[3].textContent = (q*val).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});
      } else {
        if (hint) hint.textContent = '';
      }
    }

async function fetchProductoInfo(rowEl){
  const sel = rowEl.querySelector('select[name="prod_id[]"]');
  if (!sel || !sel.value) return;
  const id = sel.value;

  // Determina si es mp o ic con tus índices ya construidos
  let type = '';
  if (typeIndex?.mp?.has(id)) type = 'mp';
  else if (typeIndex?.ic?.has(id)) type = 'ic';
  if (!type) return;

  try {
    const r = await fetch(`ordenes_compra.php?ajax=prodinfo&type=${type}&id=${id}`, {cache:'no-store'});
    if (!r.ok) return;
    const data = await r.json();
    const densidadBadge = rowEl.querySelector('.densidad-badge');
    const densidadVal   = rowEl.querySelector('.densidad-val');

    // Guarda en dataset para cálculos
    rowEl.dataset.esSolvente = (data.ok && Number(data.es_solvente)===1) ? '1' : '0';
    rowEl.dataset.densidad   = (data.ok && data.densidad_kg_l) ? String(data.densidad_kg_l) : '';
    rowEl.dataset.unidad     = (data.ok && data.unidad_compra) ? String(data.unidad_compra).toLowerCase() : 'g';

    if (rowEl.dataset.esSolvente==='1' && rowEl.dataset.densidad){
      densidadVal.textContent = Number(rowEl.dataset.densidad).toFixed(4);
      densidadBadge.classList.remove('d-none');
    } else {
      densidadBadge.classList.add('d-none');
    }
    calcLitros(rowEl); // recalcula si ya hay cantidad
  } catch(e){ /* noop */ }
}

// Convierte cantidad a litros usando densidad y unidad_compra
function calcLitros(rowEl){
  const litrosTip = rowEl.querySelector('.litros-tip');
  const litrosVal = rowEl.querySelector('.litros-val');
  const qtyEl     = rowEl.querySelector('.cantidad-input');

  if (!qtyEl || !litrosTip || !litrosVal) return;

  const esSolvente = rowEl.dataset.esSolvente === '1';
  const densidad   = Number(rowEl.dataset.densidad || NaN); // kg/L
  const unidad     = (rowEl.dataset.unidad || 'g').toLowerCase();
  const q          = Number(qtyEl.value || 0);

  if (!esSolvente || !isFinite(densidad) || !q){
    litrosTip.classList.add('d-none');
    return;
  }

  let litros = 0;
  switch (unidad){
    case 'g':  litros = (q/1000) / densidad; break;  // g -> kg -> L
    case 'kg': litros = q / densidad;        break;  // kg -> L
    case 'l':  litros = q;                   break;  // L -> L
    default:   litros = 0;                   break;  // pza u otros
  }

  litrosVal.textContent = litros.toFixed(2);
  litrosTip.classList.remove('d-none');
}

    btnAdd.addEventListener('click', addLinea);
    provSelect.addEventListener('change', async () => {
      bodyLines.innerHTML = '';
      rebuildTypeIndex();
      await fetchLastPrices();
      addLinea();
      // Reaplica sugerencias a filas existentes (si agregas varias)
      document.querySelectorAll('[data-oc-row]').forEach(applySuggestedPrice);
    });
    window.addEventListener('DOMContentLoaded', () => {
      rebuildTypeIndex();
      fetchLastPrices().then(() => addLinea());
    });
    </script>

    <?php include 'footer.php'; ?>

<?php
} catch (PDOException $e) {
    echo '<h1>Error de BD:</h1><pre>'.htmlspecialchars($e->getMessage()).'</pre>';
    exit;
} catch (Throwable $e) {
    echo '<h1>Error inesperado:</h1><pre>'.htmlspecialchars($e->getMessage()).'</pre>';
    exit;
}
