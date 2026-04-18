<?php
// recepcion_compra.php
require 'config.php';

$userId = $_SESSION['user_id'];
$rol    = $_SESSION['rol'];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['oc_id'])) {
    $ocId = (int)$_POST['oc_id'];
    // 1) Alta de gasto
    if (isset($_POST['accion']) && $_POST['accion']==='agregar_gasto') {
        $tipo   = $_POST['tipo'] ?? 'otros';
        $monto  = (float)($_POST['monto'] ?? 0);
        $moneda = substr($_POST['moneda'] ?? 'MXN',0,3);
        $tc     = (float)($_POST['tipo_cambio'] ?? 1);
        $crit   = $_POST['criterio'] ?? 'valor';
        $usr    = (int)($_SESSION['user_id'] ?? 0);
        if ($monto > 0) {
            $ins = $pdo->prepare("INSERT INTO gastos_compra
              (oc_id, tipo, monto, moneda, tipo_cambio, criterio_prorrateo, creado_por)
              VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$ocId, $tipo, $monto, $moneda, $tc, $crit, $usr]);
        }
        header("Location: recepcion_compra.php?oc_id=".$ocId."#gastos"); exit;
    }
    // 2) Borrar gasto
    if (isset($_POST['accion']) && $_POST['accion']==='borrar_gasto' && isset($_POST['gasto_id'])) {
        $gid = (int)$_POST['gasto_id'];
        $pdo->prepare("DELETE FROM gastos_compra WHERE id=? AND oc_id=?")->execute([$gid,$ocId]);
        header("Location: recepcion_compra.php?oc_id=".$ocId."#gastos"); exit;
    }
    // 3) Prorratear
    if (isset($_POST['accion']) && $_POST['accion']==='prorratear') {
        // Lotes de la OC
        $lotes = $pdo->prepare("
          SELECT r.id, r.cantidad_recibida, r.costo_unitario_mxn, r.linea_id, lc.mp_id, lc.ic_id
          FROM recepciones_compra_lineas r
          JOIN lineas_compra lc ON lc.id = r.linea_id
          WHERE r.orden_compra_id = ?
        ");
        $lotes->execute([$ocId]);
        $L = $lotes->fetchAll(PDO::FETCH_ASSOC);
        if (!$L) { header("Location: recepcion_compra.php?oc_id=".$ocId."#gastos"); exit; }

        // Reset (idempotente)
        $pdo->prepare("UPDATE recepciones_compra_lineas SET gasto_prorrateado_unitario_mxn = 0 WHERE orden_compra_id = ?")->execute([$ocId]);

        // Gastos de la OC
        $gs = $pdo->prepare("SELECT id, tipo, monto, moneda, tipo_cambio, criterio_prorrateo FROM gastos_compra WHERE oc_id=?");
        $gs->execute([$ocId]);
        $G = $gs->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay gastos, salir
        if (!$G) { header("Location: recepcion_compra.php?oc_id=".$ocId."#gastos"); exit; }

        // Prepara mapa acumulado por lote
        $acumPorLote = [];
        foreach ($L as $r) $acumPorLote[$r['id']] = 0.0;

        // Para cada gasto, calcula su base y reparte proporcional
        foreach ($G as $g) {
            $gasto_mxn = (float)$g['monto'] * (float)$g['tipo_cambio'];
            $crit = $g['criterio_prorrateo'];
            // Bases por lote según criterio
            $bases = [];
            $totalBase = 0.0;
            foreach ($L as $r) {
                $base = 0.0;
                if ($crit === 'unidades') {
                    $base = (float)$r['cantidad_recibida'];
                } else if ($crit === 'valor') {
                    $base = (float)$r['costo_unitario_mxn'] * (float)$r['cantidad_recibida'];
                } else if ($crit === 'peso' || $crit === 'volumen') {
                    // Si no hay datos de peso/volumen, cae a valor para no bloquear
                    $base = (float)$r['costo_unitario_mxn'] * (float)$r['cantidad_recibida'];
                } else {
                    $base = (float)$r['costo_unitario_mxn'] * (float)$r['cantidad_recibida'];
                }
                $bases[$r['id']] = max(0.0, $base);
                $totalBase += $bases[$r['id']];
            }
            if ($totalBase <= 0) continue;
            // Reparte gasto: total -> por lote -> unitario
            foreach ($L as $r) {
                $loteId = $r['id'];
                $cant   = (float)$r['cantidad_recibida'];
                if ($cant <= 0) continue;
               $parte = $gasto_mxn * ($bases[$loteId] / $totalBase); // gasto total asignado a este lote
                $unit  = $parte / $cant;
                $acumPorLote[$loteId] += $unit;
            }
        }
        // Persistir acumulado
        $up = $pdo->prepare("UPDATE recepciones_compra_lineas SET gasto_prorrateado_unitario_mxn = ? WHERE id = ?");
        foreach ($acumPorLote as $loteId => $unit) {
            $up->execute([round($unit,6), $loteId]);
        }
        header("Location: recepcion_compra.php?oc_id=".$ocId."#gastos"); exit;
    }
}

// 1) Procesar recepción (INSERT + stock por LO RECIBIDO + movimientos + cerrar OC)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oc_id'], $_POST['linea_id'])) {
    $oc = intval($_POST['oc_id']);

    // cargar mapa línea → {mp_id, ic_id, cantidad}
    $stmtLn = $pdo->prepare("
      SELECT id, mp_id, ic_id, cantidad
        FROM lineas_compra
       WHERE orden_compra_id = ?
    ");
    $stmtLn->execute([$oc]);
    $mapLn = [];
    foreach ($stmtLn->fetchAll(PDO::FETCH_ASSOC) as $ln) {
        $mapLn[$ln['id']] = $ln;
    }

    // preparar INSERT de recepción (ya con cantidades)
    $ins = $pdo->prepare("
      INSERT INTO recepciones_compra_lineas
        (orden_compra_id, linea_id, cantidad_recibida, cantidad_disponible,
         lote, fecha_ingreso, factura_numero, recepcionador_id, comentario)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $pdo->beginTransaction();
    try {

    foreach ($_POST['linea_id'] as $i => $lineaId) {
        $lote    = trim($_POST['lote'][$i])           ?: null;
        $fecha   = $_POST['fecha_ingreso'][$i]        ?: null;
        $factura = trim($_POST['factura_numero'][$i]) ?: null;
        $coment  = trim($_POST['comentario'][$i])     ?: null;
        // cantidad efectivamente recibida (si no se manda, cae al plan de la OC)
        $cantRec = isset($_POST['cantidad_recibida'][$i]) 
                   ? (float)$_POST['cantidad_recibida'][$i] 
                   : (isset($mapLn[$lineaId]) ? (float)$mapLn[$lineaId]['cantidad'] : 0);
        if ($cantRec <= 0 && !$lote && !$fecha && !$factura && !$coment) continue;
        $ins->execute([
          $oc, (int)$lineaId,
          $cantRec, $cantRec,     // disponible arranca igual que recibida
          $lote, $fecha, $factura, $userId, $coment
        ]);
        
        $recId = $pdo->lastInsertId();
        // Congelar costo del lote con los datos de la línea/OC
        $pdo->prepare("
          UPDATE recepciones_compra_lineas r
          JOIN lineas_compra lc ON lc.id = r.linea_id
          JOIN ordenes_compra oc ON oc.id = r.orden_compra_id
             SET r.precio_unitario_neto       = lc.precio_unitario,
                 r.moneda                     = COALESCE(lc.moneda, oc.moneda),
                 r.tipo_cambio                = COALESCE(NULLIF(lc.tipo_cambio,0), oc.tipo_cambio),
                 r.costo_unitario_mxn         = lc.precio_unitario * COALESCE(NULLIF(lc.tipo_cambio,0), oc.tipo_cambio)
           WHERE r.id = ?
        ")->execute([$recId]);

        // actualizar stock con LO RECIBIDO
        if (!isset($mapLn[$lineaId])) {
            continue;
        }
        $ln = $mapLn[$lineaId];
        if ($ln['mp_id']) {
            // Lock & update existencia
            $pdo->prepare("SELECT existencia FROM materias_primas WHERE id=? FOR UPDATE")
                ->execute([$ln['mp_id']]);
            $pdo->prepare("UPDATE materias_primas SET existencia = existencia + ? WHERE id = ?")
                ->execute([$cantRec, $ln['mp_id']]);
            // Movimiento de entrada (SIN columna origen_id para evitar error 1054)
            $comentMov = sprintf("OC #%s, línea %s, lote %s", $oc, $lineaId, $lote ?: '-');
            $pdo->prepare("
              INSERT INTO movimientos_mp (mp_id, tipo, cantidad, fecha, usuario_id, comentario)
              VALUES (?, 'entrada', ?, NOW(), ?, ?)
            ")->execute([(int)$ln['mp_id'], (float)$cantRec, (int)$userId, $comentMov]);
        } elseif ($ln['ic_id']) {
            $pdo->prepare("
              UPDATE insumos_comerciales
                 SET stock = stock + ?
               WHERE id = ?
            ")->execute([$cantRec, $ln['ic_id']]);
        }
    }
    

    /* Cerrar la OC sólo si todas las líneas quedaron completas:
       suma recibida (todas las recepciones) >= cantidad plan para cada línea */
    $chk = $pdo->prepare("
      SELECT 1
      FROM lineas_compra lc
      LEFT JOIN (
        SELECT linea_id, SUM(cantidad_recibida) AS rec
          FROM recepciones_compra_lineas
         WHERE orden_compra_id = ?
         GROUP BY linea_id
      ) r ON r.linea_id = lc.id
      WHERE lc.orden_compra_id = ?
        AND COALESCE(r.rec,0) < lc.cantidad
      LIMIT 1
    ");
    $chk->execute([$oc, $oc]);
    if ($chk->fetchColumn() === false) {
        $pdo->prepare("UPDATE ordenes_compra SET estado='cerrada' WHERE id=?")->execute([$oc]);
    }

    $pdo->commit();
  } catch (Throwable $e) {
      $pdo->rollBack();
      http_response_code(500);
      echo 'Error al guardar recepción: ' . htmlspecialchars($e->getMessage());
      exit;
    }

    header('Location: recepcion_compra.php?ok=1');
    exit;
}

// 2) Mostrar formulario de detalle si vienen con ?oc=###
if (isset($_GET['oc'])) {
    $ocId = intval($_GET['oc']);

    // cabecera OC pagada
    $hd = $pdo->prepare("
      SELECT oc.id, p.nombre AS proveedor, oc.fecha_emision,
             u.nombre AS solicitante
        FROM ordenes_compra oc
        JOIN proveedores p ON p.id = oc.proveedor_id
        JOIN usuarios    u ON u.id = oc.solicitante_id
       WHERE oc.id = ? AND oc.estado = 'pagada'
    ");
    $hd->execute([$ocId]);
    $oc = $hd->fetch(PDO::FETCH_ASSOC);
    if (!$oc) {
        header('Location: recepcion_compra.php');
        exit;
    }

    // líneas OC
    $ln = $pdo->prepare("
      SELECT lc.id AS linea_id,
             COALESCE(mp.nombre, ic.nombre) AS producto,
             lc.cantidad
        FROM lineas_compra lc
        LEFT JOIN materias_primas     mp ON mp.id = lc.mp_id
        LEFT JOIN insumos_comerciales ic ON ic.id = lc.ic_id
       WHERE lc.orden_compra_id = ?
    ");
    $ln->execute([$ocId]);
    $lines = $ln->fetchAll(PDO::FETCH_ASSOC);

    include 'header.php';
    ?>
    <div class="container mt-4">
      <h3 class="text-primary">Recepción OC #<?= $oc['id'] ?></h3>
      <p>
        <strong>Proveedor:</strong> <?= htmlspecialchars($oc['proveedor']) ?>
        &nbsp;|&nbsp;
        <strong>Solicita:</strong> <?= htmlspecialchars($oc['solicitante']) ?>
      </p>

      <form method="POST">
        <input type="hidden" name="oc_id" value="<?= $oc['id'] ?>">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Producto</th>
              <th class="text-end">Cantidad (OC)</th>
              <th class="text-end">Recibido</th>
              <th>Lote</th>
              <th>Fecha Ingreso</th>
              <th>Factura #</th>
              <th>Comentario</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lines as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['producto']) ?></td>
              <td class="text-end"><?= number_format($r['cantidad'],0,',','.') ?></td>
              <td class="text-end" style="width:140px">
           <input type="number" step="0.001" min="0"
                       name="cantidad_recibida[]" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($r['cantidad']) ?>">
              </td>
              <input type="hidden" name="linea_id[]" value="<?= $r['linea_id'] ?>">
              <td><input type="text"   name="lote[]"           class="form-control form-control-sm"></td>
              <td><input type="date"   name="fecha_ingreso[]"  class="form-control form-control-sm"></td>
              <td><input type="text"   name="factura_numero[]" class="form-control form-control-sm"></td>
              <td><input type="text"   name="comentario[]"     class="form-control form-control-sm" placeholder="(opcional)"></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button class="btn btn-success">Guardar Recepción</button>
        <a href="recepcion_compra.php" class="btn btn-outline-secondary">Cancelar</a>
      </form>
    </div>
    <?php
    include 'footer.php';
    exit;
}

// 3) Listado de OCs pagadas pendientes de recibir
// Construyo el SELECT con la columna "responsable"
$baseSql = "
  SELECT
    oc.id            AS oc_id,
    p.nombre         AS proveedor,
    oc.fecha_emision,
    u.nombre         AS solicita,
    CASE
      WHEN EXISTS(
        SELECT 1 FROM lineas_compra lc
         WHERE lc.orden_compra_id = oc.id
           AND lc.mp_id IS NOT NULL
      ) THEN (
        SELECT nombre FROM usuarios WHERE rol = 'produccion' LIMIT 1
      )
      ELSE (
        SELECT nombre FROM usuarios WHERE rol = 'logistica' LIMIT 1
      )
    END AS responsable
  FROM ordenes_compra oc
  JOIN proveedores p ON p.id = oc.proveedor_id
  JOIN usuarios    u ON u.id = oc.solicitante_id
  WHERE oc.estado = 'pagada'
";

if (! in_array($rol, ['admin','gerente','produccion','logistica'], true)) {
    // el resto (p.ej logística) sólo ve sus propias OCs
    $baseSql .= " AND oc.solicitante_id = ?";
    $stmt = $pdo->prepare($baseSql . " ORDER BY oc.id DESC");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->query($baseSql . " ORDER BY oc.id DESC");
}
$ocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>
<div class="container mt-4">
  <h3 class="text-success">Recepción de Material</h3>

  <?php if (empty($ocs)): ?>
    <div class="alert alert-info">No hay órdenes pagadas pendientes de recibir.</div>
  <?php else: ?>
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th># OC</th>
          <th>Proveedor</th>
          <th>Emisión</th>
          <th>Solicita</th>
          <th>Responsable</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ocs as $o): ?>
        <tr>
          <td>
            <a href="autorizar_compra.php?view=historial&oc=<?= $o['oc_id'] ?>" class="text-decoration-none">
              <?= $o['oc_id'] ?>
            </a>
          </td>
          <td><?= htmlspecialchars($o['proveedor']) ?></td>
          <td><?= $o['fecha_emision'] ?></td>
          <td><?= htmlspecialchars($o['solicita']) ?></td>
          <td><?= htmlspecialchars($o['responsable']) ?></td>
          <td>
            <a href="recepcion_compra.php?oc=<?= $o['oc_id'] ?>"
               class="btn btn-sm btn-success">Recibir Material</a>
            <a href="recepcion_compra.php?oc_id=<?= $o['oc_id'] ?>#gastos"
               class="btn btn-link btn-sm p-0 ms-2 align-baseline">Gastos (landed cost)</a>
          </td>
        </tr>
        
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- ================== MODAL: Gastos (Landed Cost) ================== -->
<?php
  $ocIdModal = (int)($_GET['oc_id'] ?? 0);
  $rows = [];
  if ($ocIdModal) {
      $gs = $pdo->prepare("SELECT * FROM gastos_compra WHERE oc_id=? ORDER BY id DESC");
     $gs->execute([$ocIdModal]);
      $rows = $gs->fetchAll(PDO::FETCH_ASSOC);
  }
?>
<div class="modal fade" id="modalLandedCost" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          Gastos de compra (landed cost)
         <?php if ($ocIdModal): ?>
            — OC <span class="badge bg-secondary"><?= $ocIdModal ?></span>
          <?php endif; ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <!-- Formulario alta de gasto -->
        <form method="post" class="row row-cols-1 row-cols-lg-auto g-3 align-items-end mb-3">
          <input type="hidden" name="oc_id" value="<?= $ocIdModal ?>">
          <input type="hidden" name="accion" value="agregar_gasto">
          <div class="col">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="flete">Flete</option>
              <option value="aduana">Aduana</option>
              <option value="maniobras">Maniobras</option>
              <option value="seguro">Seguro</option>
              <option value="otros" selected>Otros</option>
            </select>
          </div>
          <div class="col">
            <label class="form-label">Monto</label>
            <input name="monto" type="number" step="0.01" min="0" class="form-control" required>
          </div>
          <div class="col">
            <label class="form-label">Moneda</label>
            <select name="moneda" class="form-select" id="moneda">
              <option value="MXN" selected>MXN</option>
              <option value="USD">USD</option>
              <option value="EUR">EUR</option>
            </select>
          </div>
          <div class="col">
            <label class="form-label">Tipo de cambio</label>
            <input name="tipo_cambio" type="number" step="0.000001" min="0" value="1.000000" class="form-control" id="tipo_cambio">
         </div>
          <div class="col-md-2">
            <label class="form-label">Criterio</label>
            <select name="criterio" class="form-select">
              <option value="valor" selected>Por valor</option>
              <option value="unidades">Por unidades</option>
              <option value="peso">Por peso*</option>
              <option value="volumen">Por volumen*</option>
            </select>
            <div class="form-text">*si no hay datos, cae a valor</div>
          </div>
          <div class="col d-flex gap-2 justify-content-lg-end align-items-end">
            <button type="submit" class="btn btn-primary">Agregar gasto</button>
          </div>
        </form>

        <!-- Lista de gastos existentes -->
        <?php if ($ocIdModal && $rows): ?>
          <div class="table-responsive mt-2">
            <table class="table table-sm table-striped">
              <thead>
                <tr><th>ID</th><th>Tipo</th><th>Monto</th><th>Moneda</th><th>TC</th><th>Criterio</th><th></th></tr>
              </thead>
              <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['tipo']) ?></td>
                  <td><?= number_format($r['monto'],2) ?></td>
                  <td><?= htmlspecialchars($r['moneda']) ?></td>
                  <td><?= number_format((float)$r['tipo_cambio'],6) ?></td>
                  <td><?= htmlspecialchars($r['criterio_prorrateo']) ?></td>
                  <td>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="oc_id" value="<?= $ocIdModal ?>">
                      <input type="hidden" name="accion" value="borrar_gasto">
                      <input type="hidden" name="gasto_id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar gasto?');">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php elseif ($ocIdModal): ?>
          <div class="alert alert-light border">Aún no hay gastos capturados para esta OC.</div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <?php if ($ocIdModal): ?>
          <form method="post" class="me-auto">
            <input type="hidden" name="oc_id" value="<?= $ocIdModal ?>">
            <input type="hidden" name="accion" value="prorratear">
            <button class="btn btn-success">Aplicar prorrateo a lotes</button>
          </form>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- ============================================================ -->

<script>
// Autoconfigurar TC según moneda dentro del modal
(function(){
  var moneda = document.getElementById('moneda');
  var tc = document.getElementById('tipo_cambio');
  if (!moneda || !tc) return;
  function syncTC(){
    var isMXN = (moneda.value === 'MXN');
    tc.readOnly = isMXN;
    if (isMXN) { tc.value = '1.000000'; }
    else if (!tc.value || tc.value === '1.000000') { tc.value = '18.000000'; }
  }
  moneda.addEventListener('change', syncTC);
  syncTC();
})();

// Si viene ?oc_id=### en la URL, abrir el modal automáticamente
<?php if ($ocIdModal): ?>
  document.addEventListener('DOMContentLoaded', function(){
    var modal = new bootstrap.Modal(document.getElementById('modalLandedCost'));
    modal.show();
  });
<?php endif; ?>
</script>

<?php include 'footer.php'; ?>