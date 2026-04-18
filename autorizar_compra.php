<?php
// autorizar_compra.php v2.3
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

try {
    // 1) Permisos
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin','gerente'], true)) {
        header('Location: login.php');
        exit;
    }
    $userId = $_SESSION['user_id'];
    $view   = $_GET['view'] ?? 'pendientes';
    $ocId   = intval($_GET['oc'] ?? $_GET['id'] ?? 0);

    // (2) Candado: si la OC ya tiene recepciones, bloqueamos ediciones de líneas
    $ocBloqueada = false;
    if ($ocId > 0) {
        $stBlk = $pdo->prepare("
          SELECT COUNT(r.id) AS n
          FROM lineas_compra lc
          JOIN recepciones_compra_lineas r ON r.linea_id = lc.id
          WHERE lc.orden_compra_id = ?
        ");
        $stBlk->execute([$ocId]);
        $ocBloqueada = ((int)$stBlk->fetchColumn() > 0);
    }

    // 2) Reconstruir mapa MP+IC por proveedor
    $map = [];
    foreach ($pdo->query(
        "SELECT pm.proveedor_id, mp.id AS prod_id, mp.nombre AS prod_nombre
           FROM proveedores_mp pm
           JOIN materias_primas mp ON mp.id=pm.mp_id
          WHERE mp.activo=1
          ORDER BY mp.nombre"
    ) as $r) {
        $map[$r['proveedor_id']]['mp'][] = ['id'=>(int)$r['prod_id'],'nombre'=>$r['prod_nombre']];
    }
    foreach ($pdo->query(
        "SELECT ic.proveedor_id, ic.id AS prod_id, ic.nombre AS prod_nombre
           FROM insumos_comerciales ic
          WHERE ic.activo=1
          ORDER BY ic.nombre"
    ) as $r) {
        $map[$r['proveedor_id']]['ic'][] = ['id'=>(int)$r['prod_id'],'nombre'=>$r['prod_nombre']];
    }

    // 3) Procesar POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ocId > 0) {
        // (2) Endpoint modal: actualizar UNA línea con recandado
        if (($_POST['action'] ?? '') === 'update_linea') {
            $lineaId  = (int)($_POST['linea_id'] ?? 0);
            $cantidad = (float)($_POST['cantidad'] ?? 0);
            $precio   = (float)($_POST['precio_unitario'] ?? 0);

            // Revalida: ¿esta línea ya tiene recepciones?
            $st = $pdo->prepare("SELECT COUNT(*) FROM recepciones_compra_lineas WHERE linea_id = ?");
            $st->execute([$lineaId]);
            if ($st->fetchColumn() > 0) {
                header("Location: autorizar_compra.php?view=$view&oc=$ocId&err=bloqueada"); exit;
            }
            $upd1 = $pdo->prepare("
              UPDATE lineas_compra
              SET cantidad=?, precio_unitario=?, subtotal=(?*?)
              WHERE id=? AND orden_compra_id=?
            ");
            $upd1->execute([$cantidad,$precio,$cantidad,$precio,$lineaId,$ocId]);
            header("Location: autorizar_compra.php?view=$view&oc=$ocId&ok=upd"); exit;
        }
        // a) Actualizar fecha emisión
        if (!empty($_POST['fecha_emision'])) {
            $pdo->prepare(
                "UPDATE ordenes_compra
                   SET fecha_emision      = ?,
                       modificador_id     = ?,
                       fecha_modificacion = NOW()
                 WHERE id = ?"
            )->execute([$_POST['fecha_emision'], $userId, $ocId]);
        }

        // b) Guardar líneas
        if (isset($_POST['action']) && $_POST['action'] === 'guardar'
            && !empty($_POST['prod_id']) && is_array($_POST['prod_id'])
        ) {
            
            // Si la OC está bloqueada, no permitas guardado masivo
            if ($ocBloqueada) {
                header("Location: autorizar_compra.php?view=$view&oc=$ocId&err=bloqueada"); exit;
            }
            
            $prodIds = $_POST['prod_id'];
            $cants   = $_POST['cantidad'];
            $prices  = $_POST['precio_unitario'];
            $lineIds = $_POST['linea_id'] ?? [];
            $provId  = intval($_POST['proveedor_id']);

            // preparar statements
            $upd = $pdo->prepare(
                "UPDATE lineas_compra
                   SET mp_id=?, ic_id=?, cantidad=?, precio_unitario=?, subtotal=?
                 WHERE id=? AND orden_compra_id=?"
            );
            $ins = $pdo->prepare(
                "INSERT INTO lineas_compra
                   (orden_compra_id, mp_id, ic_id, cantidad, precio_unitario, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            // ids válidos para este proveedor
            $mpValid = array_column($map[$provId]['mp'] ?? [], 'id');
            $icValid = array_column($map[$provId]['ic'] ?? [], 'id');

            foreach ($prodIds as $i => $rawId) {
                $cant = floatval($cants[$i] ?? 0);
                $unit = floatval($prices[$i] ?? 0);
                if ($cant <= 0 || $unit < 0) continue;

                $sel    = intval($rawId);
                $lineId = intval($lineIds[$i] ?? 0);

                // decidir mp o ic
                $mpId = $icId = null;
                if (in_array($sel, $mpValid, true)) {
                    $mpId = $sel;
                } elseif (in_array($sel, $icValid, true)) {
                    $icId = $sel;
                } else {
                    continue;
                }

                $sub = $cant * $unit;
                if ($lineId > 0) {
                    // actualizar
                    $upd->execute([$mpId, $icId, $cant, $unit, $sub, $lineId, $ocId]);
                } else {
                    // insertar
                    $ins->execute([$ocId, $mpId, $icId, $cant, $unit, $sub]);
                }
            }
        }

        // c) Autorizar / Rechazar
        if (isset($_POST['action']) && in_array($_POST['action'], ['aprobar','rechazar'], true)) {
            $newState = ($_POST['action'] === 'aprobar') ? 'autorizada' : 'rechazada';
            $pdo->prepare(
                "UPDATE ordenes_compra
                   SET estado             = ?,
                       autorizador_id     = ?,
                       fecha_autorizacion = NOW()
                 WHERE id = ?"
            )->execute([$newState, $userId, $ocId]);
        }

    if (isset($_POST['action']) && in_array($_POST['action'], ['aprobar','rechazar'], true)) {
        header("Location: autorizar_compra.php");
    } else {
        // si fue guardar cambios, volvemos al detalle
        header("Location: autorizar_compra.php?view=$view&oc=$ocId");
    }
    exit;
    }

    // 4) Mostrar formulario si hay OC
    if ($ocId > 0) {
        $hd = $pdo->prepare(
            "SELECT oc.*, p.id AS proveedor_id, p.nombre AS proveedor, u.nombre AS solicitante
               FROM ordenes_compra oc
               JOIN proveedores p ON p.id = oc.proveedor_id
               JOIN usuarios    u ON u.id = oc.solicitante_id
              WHERE oc.id = ?"
        );
        $hd->execute([$ocId]);
        $oc = $hd->fetch(PDO::FETCH_ASSOC);
        if (!$oc) {
            header('Location: autorizar_compra.php');
            exit;
        }
        $ln = $pdo->prepare(
            "SELECT lc.*, COALESCE(mp.nombre, ic.nombre) AS prod_nombre
               FROM lineas_compra lc
               LEFT JOIN materias_primas     mp ON mp.id = lc.mp_id
               LEFT JOIN insumos_comerciales ic ON ic.id = lc.ic_id
              WHERE lc.orden_compra_id = ?"
        );
        $ln->execute([$ocId]);
        $lines = $ln->fetchAll(PDO::FETCH_ASSOC);

        include 'header.php';
        ?>
        <div class="container mt-4">
          <h3>OC #<?= $oc['id'] ?> – <?= ucfirst($view) ?></h3>
          
          <?php if (isset($_GET['ok']) && $_GET['ok']==='upd'): ?>
            <div class="alert alert-success">Línea actualizada correctamente.</div>
          <?php endif; ?>
          <?php if (isset($_GET['err']) && $_GET['err']==='bloqueada'): ?>
            <div class="alert alert-warning">La OC tiene recepciones: edición de líneas bloqueada.</div>
          <?php endif; ?>
          
          <form method="POST" class="mb-4">
            <input type="hidden" name="proveedor_id" value="<?= $oc['proveedor_id'] ?>">
            <div class="row g-3">
              <div class="col-md-4">
                <label>Proveedor</label>
                <input type="text" class="form-control" disabled value="<?= htmlspecialchars($oc['proveedor']) ?>">
              </div>
              <div class="col-md-3">
                <label>Fecha emisión</label>
                <input type="date" name="fecha_emision" class="form-control"
                       value="<?= htmlspecialchars($oc['fecha_emision']) ?>">
              </div>
              <div class="col-md-3">
                <label>Solicita</label>
                <input type="text" class="form-control" disabled value="<?= htmlspecialchars($oc['solicitante']) ?>">
              </div>
<div class="col-md-2 text-end align-self-end">
  <?php if ($view === 'pendientes'): ?>
    <button name="action" value="aprobar" class="btn btn-success">Autorizar</button>
    <button name="action" value="rechazar" class="btn btn-danger">Rechazar</button>
  <?php endif; ?>
<button name="action" value="guardar" class="btn btn-primary" <?= $ocBloqueada?'disabled':'' ?>>Guardar Cambios</button>
  <a href="ver_oc_pdf.php?id=<?= $ocId?>" class="btn btn-outline-secondary">PDF</a>

  <?php if ($oc['estado'] === 'pagada'): /* sólo si ya marcaste la OC como pagada */ ?>
    <a href="recepcion_material.php?oc=<?= $ocId ?>"
       class="btn btn-info mt-2">Recepcionar Material</a>
  <?php endif; ?>
</div>

              
            </div>

            <h5 class="mt-4">Líneas de la OC</h5>
            <?php if ($ocBloqueada): ?>
              <div class="alert alert-warning d-flex align-items-center" role="alert">
                <!-- <i class="bi bi-lock me-2"></i> -->
                Esta orden ya tiene recepciones registradas. La edición y eliminación de líneas está bloqueada.
              </div>
            <?php endif; ?>
            <table class="table table-bordered" id="tblLineas">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cant.</th>
                  <th>Precio u.</th>
                  <th>Sub-total</th>
                  <th class="text-center">
                    <button id="addRow" class="btn btn-sm btn-success" <?= $ocBloqueada?'disabled':'' ?>>+</button>
                  </th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $r): ?>
                <tr>
                  <input type="hidden" name="linea_id[]" value="<?= $r['id'] ?>">
                  <td>
                    <select name="prod_id[]" class="form-select form-select-sm">
                      <option value="<?= $r['mp_id']?:$r['ic_id'] ?>" selected><?= htmlspecialchars($r['prod_nombre']) ?></option>
                      <?php foreach ($map[$oc['proveedor_id']]['mp'] ?? [] as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= $opt['id']==$r['mp_id']?'selected':''?>><?= htmlspecialchars($opt['nombre']) ?></option>
                      <?php endforeach; ?>
                      <?php foreach ($map[$oc['proveedor_id']]['ic'] ?? [] as $opt): ?>
                        <option value="<?= $opt['id'] ?>" <?= $opt['id']==$r['ic_id']?'selected':''?>><?= htmlspecialchars($opt['nombre']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td>
                    <input type="number" step="1" name="cantidad[]" class="form-control form-control-sm text-end"
                           value="<?= $r['cantidad'] ?>" <?= $ocBloqueada?'disabled':'' ?>>
                  </td>
                  <td>
                    <input type="number" step="0.000001" name="precio_unitario[]" class="form-control form-control-sm text-end"
                           value="<?= $r['precio_unitario'] ?>" <?= $ocBloqueada?'disabled':'' ?>>
                  </td>
                  <td class="text-end"><?= number_format($r['subtotal'],2,',','.') ?></td>
                  <td class="text-center">
                    <div class="btn-group">
                      <button type="button"
                              class="btn btn-sm btn-outline-secondary"
                              data-edit-linea
                              data-linea-id="<?= (int)$r['id'] ?>"
                              data-prod-id="<?= (int)($r['mp_id'] ?: $r['ic_id']) ?>"
                              data-cantidad="<?= (float)$r['cantidad'] ?>"
                              data-precio="<?= (float)$r['precio_unitario'] ?>"
                              <?= $ocBloqueada?'disabled':'' ?>>
                        Editar
                      </button>
                      <button class="btn btn-sm btn-danger delRow" <?= $ocBloqueada?'disabled':'' ?>>Eliminar</button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </form>
        </div>

        <script>
        const OC_BLOQUEADA = <?= $ocBloqueada ? 'true' : 'false' ?>;
        function attachDelete(){ document.querySelectorAll('.delRow').forEach(btn=>{
            btn.onclick = e=>{ if(OC_BLOQUEADA){ e.preventDefault(); return; } e.preventDefault(); btn.closest('tr').remove(); };
        }); }
        document.getElementById('addRow').onclick = e=>{
            e.preventDefault();
            if (OC_BLOQUEADA) return;
            let tb = document.querySelector('#tblLineas tbody'),
                tr = tb.querySelector('tr').cloneNode(true);
            tr.querySelector('[name="linea_id[]"]').value = 0;
            tr.querySelector('[name="cantidad[]"]').value = '';
            tr.querySelector('[name="precio_unitario[]"]').value = '';
            tr.cells[3].textContent = '0.00';
            tb.appendChild(tr);
            attachDelete();
        };
        attachDelete();

        document.querySelector('#tblLineas tbody').addEventListener('input', e=>{
            if (['cantidad[]','precio_unitario[]'].includes(e.target.name)) {
                const tr = e.target.closest('tr');
                const q = parseFloat(tr.querySelector('[name="cantidad[]"]').value) || 0;
                const u = parseFloat(tr.querySelector('[name="precio_unitario[]"]').value) || 0;
                tr.cells[3].textContent = (q*u).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});
            }
        });
        

        // ===== Modal de edición por línea (3 y 4) =====
        // Crea modal (HTML al final de la página)
        (function(){
          // Carga último precio por proveedor (para alerta 4)
          let priceMap = {mp:{}, ic:{}};
          async function fetchLastPrices(proveedorId){
            try{
              const r = await fetch(`ordenes_compra.php?ajax=lastprices&proveedor_id=${encodeURIComponent(proveedorId)}`, {cache:'no-store'});
              if (r.ok) priceMap = await r.json();
            }catch(_){}
          }
          fetchLastPrices(<?= (int)$oc['proveedor_id'] ?>);

          const modalEl = document.getElementById('modalEditarLinea');
          const bsModal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
          const form  = document.getElementById('formEditarLinea');
          const idEl  = document.getElementById('edit_linea_id');
          const cantEl= document.getElementById('edit_cantidad');
          const precEl= document.getElementById('edit_precio');
          const bloqueoMsg = document.getElementById('edit_bloqueo_msg');
          const btnOk = document.getElementById('edit_btn_guardar');

          // Al abrir el modal
          document.querySelectorAll('[data-edit-linea]').forEach(btn=>{
            btn.addEventListener('click', ()=>{
              bloqueoMsg.style.display = OC_BLOQUEADA ? '' : 'none';
              btnOk.disabled = OC_BLOQUEADA;
              idEl.value   = btn.dataset.lineaId || '';
              cantEl.value = btn.dataset.cantidad || '';
              precEl.value = btn.dataset.precio || '';
              if (bsModal) bsModal.show();
            });
          });

          // Alerta suave si se aleja del "último" (4)
          function warnIfOutlier(){
            precEl.classList.remove('is-invalid');
            const lineaId = idEl.value;
            // buscamos el prodId a partir del botón activo
            const btn = [...document.querySelectorAll('[data-edit-linea]')].find(b=>b.dataset.lineaId===lineaId);
            if (!btn) return;
            const prodId = String(btn.dataset.prodId||'');
            let last;
            if (priceMap.mp && priceMap.mp['mp_'+prodId] != null) last = Number(priceMap.mp['mp_'+prodId]);
            if (priceMap.ic && priceMap.ic['ic_'+prodId] != null) last = Number(priceMap.ic['ic_'+prodId]);
            const now = Number(precEl.value||NaN);
            if (!isFinite(now) || !isFinite(last) || last<=0) return;
            const diff = Math.abs((now-last)/last);
            if (diff > 0.25) precEl.classList.add('is-invalid');
          }
          precEl.addEventListener('input', warnIfOutlier);
          form.addEventListener('submit', ()=>{ if (OC_BLOQUEADA) event.preventDefault(); });
        })();
        </script>
    
        <!-- Modal edición línea -->
        <div class="modal fade" id="modalEditarLinea" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <form id="formEditarLinea" method="post" autocomplete="off">
                <div class="modal-header">
                  <h5 class="modal-title">Editar línea de compra</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="action" value="update_linea">
                  <input type="hidden" name="oc_id" value="<?= (int)$ocId ?>">
                  <input type="hidden" name="linea_id" id="edit_linea_id">
                  <div class="mb-3">
                    <label class="form-label">Cantidad (g o pzas)</label>
                    <input type="number" step="0.0001" min="0" class="form-control" name="cantidad" id="edit_cantidad" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Precio unitario</label>
                    <input type="number" step="0.000001" min="0" class="form-control" name="precio_unitario" id="edit_precio" required>
                    <div class="invalid-feedback">Atención: el precio difiere &gt;25% del último.</div>
                  </div>
                  <div class="alert alert-warning mb-0" id="edit_bloqueo_msg" style="display:none;">
                    Esta OC tiene recepciones, no es posible editar la línea.
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary" id="edit_btn_guardar">Guardar cambios</button>
                </div>
              </form>
            </div>
          </div>
        </div>



        <?php
        include 'footer.php';
        exit;
    }

    // 5) Listado global…
    $sql = $view==='historial'
        ? "SELECT oc.id,p.nombre AS proveedor,oc.fecha_emision,u.nombre AS solicitante,oc.estado
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id=oc.proveedor_id
            JOIN usuarios   u ON u.id=oc.solicitante_id
           WHERE oc.estado IN ('autorizada','rechazada','enviada')
           ORDER BY oc.id DESC"
        : "SELECT oc.id,p.nombre AS proveedor,oc.fecha_emision,u.nombre AS solicitante,oc.estado
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id=oc.proveedor_id
            JOIN usuarios   u ON u.id=oc.solicitante_id
           WHERE oc.estado='pendiente'
           ORDER BY oc.id DESC";
    $ocs = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    include 'header.php';
    ?>
    <div class="container mt-4">
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
          <a class="nav-link <?= $view==='pendientes'?'active':'' ?>" href="?view=pendientes">Pendientes</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $view==='historial'?'active':'' ?>" href="?view=historial">Historial</a>
        </li>
      </ul>

      <?php if (!$ocs): ?>
        <div class="alert alert-info">No hay órdenes en esta vista.</div>
      <?php else: ?>
        <table class="table table-hover">
          <thead>
            <tr><th>#</th><th>Proveedor</th><th>Fecha emisión</th><th>Solicita</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($ocs as $o): ?>
            <tr>
              <td><?= $o['id'] ?></td>
              <td><?= htmlspecialchars($o['proveedor']) ?></td>
              <td><?= $o['fecha_emision'] ?></td>
              <td><?= htmlspecialchars($o['solicitante']) ?></td>
              <td><span class="badge bg-<?=
                ($o['estado']=='pendiente'? 'warning'
                :($o['estado']=='autorizada'? 'success'
                :($o['estado']=='rechazada'? 'danger'
                :($o['estado']=='enviada'? 'primary':'secondary'))))
              ?>"><?= ucfirst($o['estado']) ?></span></td>
              <td><a href="autorizar_compra.php?view=<?= $view ?>&oc=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">Ver / Editar</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>

<?php
} catch (PDOException $e) {
    echo '<h1>Error de BD:</h1><pre>'.htmlspecialchars($e->getMessage()).'</pre>';
    exit;
} catch (Throwable $e) {
    echo '<h1>Error inesperado:</h1><pre>'.htmlspecialchars($e->getMessage()).'</pre>';
    exit;
}



