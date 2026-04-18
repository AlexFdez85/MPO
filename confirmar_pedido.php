<?php
// ordenes_venta.php
require_once 'config.php';

// Mostrar errores en desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['admin', 'gerente', 'logistica', 'produccion'], true)) {
    header('Location: dashboard.php');
    exit;
}
$usuario_id = $_SESSION['user_id'];
$rol = $_SESSION['rol'];

// AJAX endpoint para obtener presentaciones y stock de un producto
if (isset($_GET['action']) && $_GET['action'] === 'get_presentations_by_product') {
    header('Content-Type: application/json');
    $productoId = intval($_GET['producto_id'] ?? 0);
    $data = [];
    if ($productoId > 0) {
        $stmt = $pdo->prepare("
            SELECT pr.id, pr.nombre, COALESCE(SUM(pt.cantidad), 0) AS stock
            FROM productos_presentaciones pp
            JOIN presentaciones pr ON pp.presentacion_id = pr.id
            LEFT JOIN productos_terminados pt ON pt.producto_id = pp.producto_id AND pt.presentacion_id = pp.presentacion_id
            WHERE pp.producto_id = ?
            GROUP BY pr.id, pr.nombre
            ORDER BY pr.volumen_ml ASC, pr.nombre ASC
        ");
        $stmt->execute([$productoId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($data);
    exit;
}

// 1) Eliminar o solicitar eliminación (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_venta'])) {
    $ordenId = intval($_POST['orden_id_delete'] ?? 0);
    if (in_array($rol, ['admin', 'gerente'], true)) {
        $pdo->prepare("DELETE sv FROM surtidos_venta sv JOIN lineas_venta lv ON sv.linea_venta_id = lv.id WHERE lv.orden_venta_id = ?")->execute([$ordenId]);
        $pdo->prepare("DELETE FROM lineas_venta WHERE orden_venta_id = ?")->execute([$ordenId]);
        $pdo->prepare("DELETE FROM ordenes_venta WHERE id = ?")->execute([$ordenId]);
        header('Location: ordenes_venta.php?deleted=1');
    } else {
        header('Location: ordenes_venta.php?requested=1');
    }
    exit;
}

// 2) Crear nuevo pedido (POST) - Ahora es un endpoint para AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_venta_ajax'])) {
    $response = ['success' => false, 'message' => ''];
    try {
        $pdo->beginTransaction();
        $clienteId = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : null;
        $distribuidorId = !empty($_POST['distribuidor_id']) ? intval($_POST['distribuidor_id']) : null;
        $fechaEntrega = $_POST['fecha_entrega'] ?: date('Y-m-d');
        $incluyePaquete = isset($_POST['incluye_paquete']) ? 1 : 0;
        
        $insCab = $pdo->prepare("INSERT INTO ordenes_venta (cliente_id, distribuidor_id, fecha, fecha_entrega, estado, usuario_creador, incluye_paquete) VALUES (?, ?, CURDATE(), ?, 'pendiente', ?, ?)");
        $insCab->execute([$clienteId, $distribuidorId, $fechaEntrega, $usuario_id, $incluyePaquete]);
        $ventaId = $pdo->lastInsertId();

        $prodIds = $_POST['producto_id'] ?? [];
        $presIds = $_POST['presentacion_id'] ?? [];
        $cants = $_POST['cantidad'] ?? [];
        $stmtLinea = $pdo->prepare("INSERT INTO lineas_venta (orden_venta_id, producto_id, presentacion_id, cantidad) VALUES (?, ?, ?, ?)");
        foreach ($prodIds as $i => $pid) {
            $pid = intval($pid);
            $pr = intval($presIds[$i] ?? 0);
            $ct = floatval($cants[$i] ?? 0);
            if ($pid > 0 && $pr > 0 && $ct > 0) {
                $stmtLinea->execute([$ventaId, $pid, $pr, $ct]);
            }
        }
        
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Pedido registrado correctamente.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $response['message'] = 'Error al registrar el pedido: ' . $e->getMessage();
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// 3) Cargar distribuidores activos
$stmtDist = $pdo->prepare("SELECT id, nombre FROM clientes WHERE activo = 1 AND es_distribuidor = 1 ORDER BY nombre");
$stmtDist->execute();
$distribuidores = $stmtDist->fetchAll(PDO::FETCH_ASSOC);

// 4) Filtrar clientes por distribuidor (GET)
$distribuidorSeleccionado = isset($_GET['distribuidor_id']) ? intval($_GET['distribuidor_id']) : null;
$clientes = [];
if ($distribuidorSeleccionado) {
    $stmtCli = $pdo->prepare("SELECT id, nombre FROM clientes WHERE activo = 1 AND es_distribuidor = 0 AND distribuidor_id = ? ORDER BY nombre");
    $stmtCli->execute([$distribuidorSeleccionado]);
    $clientes = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
}

// 5) Cargar catálogos estáticos
$productos = $pdo->query("SELECT id, nombre FROM productos WHERE es_para_venta = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

// 6) Actualizar estados según avance y entregas
$pdo->exec("UPDATE ordenes_venta ov JOIN entregas_venta e ON e.orden_venta_id = ov.id SET ov.estado = 'entregado' WHERE ov.estado <> 'entregado'");
$pdo->exec("UPDATE ordenes_venta ov LEFT JOIN (SELECT orden_venta_id, SUM(cantidad) AS total_pedida FROM lineas_venta GROUP BY orden_venta_id) lp ON lp.orden_venta_id = ov.id LEFT JOIN (SELECT orden_venta_id, SUM(cantidad) AS total_surtida FROM surtidos_venta GROUP BY orden_venta_id) ls ON ls.orden_venta_id = ov.id SET ov.estado = 'Listo_entrega' WHERE COALESCE(ls.total_surtida, 0) >= COALESCE(lp.total_pedida, 0) AND ov.estado <> 'entregado'");
$pdo->exec("UPDATE ordenes_venta ov LEFT JOIN (SELECT orden_venta_id, SUM(cantidad) AS total_pedida FROM lineas_venta GROUP BY orden_venta_id) lp ON lp.orden_venta_id = ov.id LEFT JOIN (SELECT orden_venta_id, SUM(cantidad) AS total_surtida FROM surtidos_venta GROUP BY orden_venta_id) ls ON ls.orden_venta_id = ov.id SET ov.estado = 'proceso_surtido' WHERE COALESCE(ls.total_surtida, 0) > 0 AND COALESCE(ls.total_surtida, 0) < COALESCE(lp.total_pedida, 0) AND ov.estado NOT IN ('entregado','Listo_entrega')");
$pdo->exec("UPDATE ordenes_venta ov LEFT JOIN (SELECT orden_venta_id, SUM(cantidad) AS total_surtida FROM surtidos_venta GROUP BY orden_venta_id) ls ON ls.orden_venta_id = ov.id SET ov.estado = 'pendiente' WHERE COALESCE(ls.total_surtida, 0) = 0 AND ov.estado <> 'entregado'");

// 7) Listar pedidos existentes
$ordenes = $pdo->query("SELECT ov.id, c.nombre AS cliente, d.nombre AS distribuidor, ov.fecha, ov.fecha_entrega, ov.estado, u.nombre AS creador, ov.incluye_paquete, COALESCE((SELECT SUM(cantidad) FROM lineas_venta lv WHERE lv.orden_venta_id = ov.id), 0) AS total_pedida, COALESCE((SELECT SUM(cantidad) FROM surtidos_venta sv WHERE sv.orden_venta_id = ov.id), 0) AS total_surtida FROM ordenes_venta ov LEFT JOIN clientes c ON c.id = ov.cliente_id LEFT JOIN clientes d ON d.id = ov.distribuidor_id LEFT JOIN usuarios u ON u.id = ov.usuario_creador ORDER BY ov.id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Pedido registrado correctamente.</div><?php elseif (isset($_GET['deleted'])): ?><div class="alert alert-success">Pedido eliminado correctamente.</div><?php elseif (isset($_GET['requested'])): ?><div class="alert alert-warning">Solicitud de eliminación enviada.</div><?php endif; ?>
<?php if (isset($_GET['entrega_confirmada'])): ?><div class="alert alert-success">Entrega confirmada correctamente.</div><?php endif; ?>

<div class="container mt-4">
    <button class="btn btn-secondary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#registroPedido" aria-expanded="true" aria-controls="registroPedido">Mostrar / Ocultar Registro de Pedido</button>
    <div class="collapse show" id="registroPedido">
        <div class="card mb-4 p-3">
            <?php if (isset($_GET['ok'])): ?><div class="alert alert-success">Pedido registrado correctamente.</div><?php elseif (isset($_GET['deleted'])): ?><div class="alert alert-success">Pedido eliminado correctamente.</div><?php elseif (isset($_GET['requested'])): ?><div class="alert alert-warning">Solicitud de eliminación enviada.</div><?php elseif (isset($_GET['entrega_confirmada'])): ?><div class="alert alert-success">Entrega confirmada correctamente.</div><?php endif; ?>
            <h3 class="text-primary mb-3">Registro de Pedido</h3>

            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Distribuidor</label>
                    <select name="distribuidor_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Selecciona --</option>
                        <?php foreach ($distribuidores as $dist): ?>
                            <option value="<?= $dist['id'] ?>" <?= $distribuidorSeleccionado === intval($dist['id']) ? 'selected' : '' ?>><?= htmlspecialchars($dist['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <form method="post" id="pedido-form" class="row g-3 mt-3" action="confirmar_pedido.php">
                <input type="hidden" name="crear_pedido" value="1">
                <?php if ($distribuidorSeleccionado): ?>
                    <input type="hidden" name="distribuidor_id" value="<?= $distribuidorSeleccionado ?>">
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label">Cliente Final</label>
                    <select name="cliente_id" class="form-select" <?= empty($clientes) ? 'disabled' : '' ?>>
                        <option value="">-- Elige cliente --</option>
                        <?php foreach ($clientes as $cli): ?>
                            <option value="<?= $cli['id'] ?>"><?= htmlspecialchars($cli['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Fecha de entrega</label>
                    <input type="date" name="fecha_entrega" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" name="incluye_paquete" id="incluye_paquete" class="form-check-input">
                        <label for="incluye_paquete" class="form-check-label">Embalado para paquetería?</label>
                    </div>
                </div>

                <div class="col-md-2 text-end">
                    <button type="submit" name="crear_pedido" class="btn btn-success w-100">Crear Pedido</button>
                </div>

                <div class="col-12">
                    <h6 class="mt-3">Productos a surtir</h6>
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th><th>Presentación</th><th>Cantidad (pzas)</th>
                                <th style="width:1%"><button type="button" id="addLinea" class="btn btn-sm btn-outline-primary">+</button></th>
                            </tr>
                        </thead>
                        <tbody id="lineasBody">
                            </tbody>
                        <tr id="template-row" style="display:none;">
                            <td>
                                <select name="producto_id[]" class="form-select form-select-sm producto-select" required onchange="fetchPresentations(this)">
                                    <option value="">-- Elige producto --</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <select name="presentacion_id[]" class="form-select form-select-sm presentation-select" required>
                                    <option value="">-- Elige presentación --</option>
                                </select>
                            </td>
                            <td>
                                <input name="cantidad[]" type="number" step="1" min="1" class="form-control form-control-sm" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-linea">–</button>
                            </td>
                        </tr>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="tabsPedidos" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="enproceso-tab" data-bs-toggle="tab" data-bs-target="#enproceso" type="button" role="tab" aria-controls="enproceso" aria-selected="true">En Proceso</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab" aria-controls="historial" aria-selected="false">Historial</button></li>
    </ul>

    <div class="tab-content" id="tabsPedidosContent">
        <div class="tab-pane fade show active" id="enproceso" role="tabpanel" aria-labelledby="enproceso-tab">
            <table class="table table-striped align-middle">
                <thead><tr><th>#</th><th>Cliente</th><th>Creada</th><th>Entrega</th><th>Estado</th><th>Creador</th><th>Paquetería</th><th>Avance</th><th>Días rest.</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($ordenes as $o):
                        if ($o['estado'] === 'entregado') continue;
                        $porc = $o['total_pedida'] > 0 ? round($o['total_surtida'] / $o['total_pedida'] * 100) : 0; ?>
                        <tr>
                            <td>#<?= htmlspecialchars($o['id']) ?></td>
                            <td><?php $cli = $o['cliente'] ?? null; $dist = $o['distribuidor'] ?? null; if ($dist && $cli) echo "{$dist} ({$cli})"; elseif ($dist) echo $dist; else echo '—'; ?></td>
                            <td><?= (new DateTime($o['fecha']))->format('d/m/Y') ?></td>
                            <td><?= (new DateTime($o['fecha_entrega']))->format('d/m/Y') ?></td>
                            <td><?php switch ($o['estado']) { case 'pendiente': $color = 'warning'; $texto = 'Pendiente'; break; case 'proceso_surtido': $color = 'info'; $texto = 'En Proceso'; break; case 'Listo_entrega': $color = 'success'; $texto = 'Listo'; break; case 'entregado': $color = 'primary'; $texto = 'Entregado'; break; default: $color = 'secondary'; $texto = ucfirst(str_replace('_', ' ', $o['estado'])); } echo "<span class='badge bg-{$color}'>{$texto}</span>"; ?></td>
                            <td><?= htmlspecialchars($o['creador']) ?></td>
                            <td><?= $o['incluye_paquete'] ? 'Sí' : 'No' ?></td>
                            <td><span class="badge bg-primary"><?= $porc ?>%</span></td>
                            <td><?php date_default_timezone_set('America/Mexico_City'); $hoy = new DateTime('today'); $fEntrega = DateTime::createFromFormat('Y-m-d', $o['fecha_entrega']); $fEntrega->setTime(0, 0, 0); $dias = (int)$hoy->diff($fEntrega)->format('%r%a'); if ($dias === 0) echo 'Entrega Hoy !!'; elseif ($dias === 1) echo 'Entrega Mañana !'; elseif ($dias > 1) echo $dias . ' días'; else echo abs($dias) . ' días atrás'; ?></td>
                            <td style="white-space: nowrap;">
                                <a href="ordenes_venta_detalle.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
                                <?php if ($porc < 100): ?><a href="status_pedido.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">Surtir</a><?php endif; ?>
                                <?php if (in_array($o['estado'], ['Listo_entrega', 'entregado'], true)): ?><a href="ver_entrega_pdf.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success"><?= $o['estado'] === 'entregado' ? 'PDF' : 'Entrega PDF' ?></a><?php endif; ?>
                                <?php if ($o['estado'] === 'Listo_entrega'): ?><a href="confirmar_entrega.php?id=<?= $o['id'] ?>" onclick="return confirm('¿Confirmar entrega?');" class="btn btn-sm btn-outline-primary">Confirmar entrega</a><?php endif; ?>
                                <form method="post" style="display:inline"><input type="hidden" name="orden_id_delete" value="<?= $o['id'] ?>"><?php if (in_array($rol, ['admin', 'gerente'], true)): ?><button name="eliminar_venta" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar #<?= $o['id'] ?>?');">Eliminar</button><?php else: ?><button name="eliminar_venta" value="1" class="btn btn-sm btn-outline-warning" onclick="return confirm('Solicitar eliminación #<?= o['id'] ?>?');">Solicitar eliminación</button><?php endif; ?></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="historial" role="tabpanel" aria-labelledby="historial-tab">
            <table class="table table-striped align-middle">
                <thead><tr><th>#</th><th>Cliente</th><th>Creada</th><th>Entrega</th><th>Estado</th><th>Creador</th><th>Paquetería</th><th>Avance</th><th>Días rest.</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($ordenes as $o):
                        if ($o['estado'] !== 'entregado') continue;
                        $porc = $o['total_pedida'] > 0 ? round($o['total_surtida'] / $o['total_pedida'] * 100) : 0; ?>
                        <tr>
                            <td>#<?= htmlspecialchars($o['id']) ?></td>
                            <td><?php $cli = $o['cliente'] ?? null; $dist = $o['distribuidor'] ?? null; if ($dist && $cli) echo "{$dist} ({$cli})"; elseif ($dist) echo $dist; else echo '—'; ?></td>
                            <td><?= (new DateTime($o['fecha']))->format('d/m/Y') ?></td>
                            <td><?= (new DateTime($o['fecha_entrega']))->format('d/m/Y') ?></td>
                            <td><?php switch ($o['estado']) { case 'entregado': $color = 'primary'; $texto = 'Entregado'; break; default: $color = 'secondary'; $texto = ucfirst($o['estado']); } echo "<span class='badge bg-{$color}'>{$texto}</span>"; ?></td>
                            <td><?= htmlspecialchars($o['creador']) ?></td>
                            <td><?= $o['incluye_paquete'] ? 'Sí' : 'No' ?></td>
                            <td><span class="badge bg-primary"><?= $porc ?>%</span></td>
                            <td><?php date_default_timezone_set('America/Mexico_City'); $hoy = new DateTime('today'); $fEntrega = DateTime::createFromFormat('Y-m-d', $o['fecha_entrega']); $fEntrega->setTime(0, 0, 0); $dias = (int)$hoy->diff($fEntrega)->format('%r%a'); if ($dias === 0) echo 'Entrega Hoy !!'; elseif ($dias === 1) echo 'Entrega Mañana !'; elseif ($dias > 1) echo $dias . ' días'; else echo abs($dias) . ' días atrás'; ?></td>
                            <td style="white-space: nowrap;"><a href="ver_entrega_pdf.php?id=<?= $o['id'] ?>" target="_blank" class="btn btn-sm btn-outline-success">PDF</a><form method="post" style="display:inline"><input type="hidden" name="orden_id_delete" value="<?= $o['id'] ?>"><button name="eliminar_venta" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Eliminar #<?= o['id'] ?>?');">Eliminar</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php include 'footer.php'; ?>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirmar Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modal-body-content">
                <p>Estás a punto de crear un pedido con los siguientes detalles:</p>
                <div id="order-summary"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirm-and-save-btn" class="btn btn-primary">Confirmar y Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    function fetchPresentations(selectElement) {
        const row = selectElement.closest('tr');
        const productId = selectElement.value;
        const presentationSelect = row.querySelector('.presentation-select');
        presentationSelect.innerHTML = '<option value="">-- Elige presentación --</option>';
        if (!productId) return;
        fetch(`ordenes_venta.php?action=get_presentations_by_product&producto_id=${productId}`)
            .then(response => response.json())
            .then(presentations => {
                presentations.forEach(pres => {
                    const option = document.createElement('option');
                    option.value = pres.id;
                    option.textContent = `${pres.nombre} (Stock: ${parseFloat(pres.stock).toFixed(2)} pzas)`;
                    presentationSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error al cargar presentaciones:', error));
    }
    document.addEventListener('DOMContentLoaded', function() {
        const lineasBody = document.getElementById('lineasBody');
        const templateRow = document.getElementById('template-row');

        const addFirstRow = () => {
            const newRow = templateRow.cloneNode(true);
            newRow.removeAttribute('id');
            newRow.style.display = 'table-row';
            newRow.querySelector('.remove-linea').addEventListener('click', function() {
                if (lineasBody.querySelectorAll('.remove-linea').length > 1) {
                    newRow.remove();
                }
            });
            lineasBody.appendChild(newRow);
        };
        addFirstRow();

        document.getElementById('addLinea').addEventListener('click', function() {
            const newRow = templateRow.cloneNode(true);
            newRow.removeAttribute('id');
            newRow.style.display = 'table-row';
            newRow.querySelector('.producto-select').value = '';
            newRow.querySelector('.presentation-select').value = '';
            newRow.querySelector('input[name="cantidad[]"]').value = '';
            newRow.querySelector('.remove-linea').addEventListener('click', function() {
                if (lineasBody.querySelectorAll('.remove-linea').length > 1) {
                    newRow.remove();
                }
            });
            lineasBody.appendChild(newRow);
        });

        const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        const openConfirmBtn = document.getElementById('open-confirm-modal-btn');
        const confirmSaveBtn = document.getElementById('confirm-and-save-btn');
        const form = document.getElementById('pedido-form');

        openConfirmBtn.addEventListener('click', function() {
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const totalLines = form.querySelectorAll('#lineasBody tr:not([style*="display: none"])').length;
            if (totalLines === 0) {
                alert('Debes agregar al menos una línea de producto.');
                return;
            }

            const formData = new FormData(form);
            let summaryHtml = '<strong>Distribuidor:</strong> ' + (form.querySelector('select[name="distribuidor_id"] option:checked') ? form.querySelector('select[name="distribuidor_id"] option:checked').textContent : 'N/A') + '<br>';
            summaryHtml += '<strong>Cliente Final:</strong> ' + (form.querySelector('select[name="cliente_id"] option:checked') ? form.querySelector('select[name="cliente_id"] option:checked').textContent : 'N/A') + '<br>';
            summaryHtml += '<strong>Fecha de Entrega:</strong> ' + form.querySelector('input[name="fecha_entrega"]').value + '<br><br>';
            summaryHtml += '<strong>Productos:</strong><ul>';
            
            form.querySelectorAll('#lineasBody tr:not([style*="display: none"])').forEach(row => {
                const producto = row.querySelector('.producto-select option:checked').textContent;
                const presentacion = row.querySelector('.presentation-select option:checked').textContent;
                const cantidad = row.querySelector('input[name="cantidad[]"]').value;
                if (producto && presentacion && cantidad) {
                    summaryHtml += `<li>${cantidad} x ${producto} (${presentacion})</li>`;
                }
            });
            summaryHtml += '</ul>';
            document.getElementById('order-summary').innerHTML = summaryHtml;
            confirmModal.show();
        });

        confirmSaveBtn.addEventListener('click', function() {
            const formData = new FormData(form);
            fetch('ordenes_venta.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                confirmModal.hide();
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                confirmModal.hide();
                alert('Error al enviar el pedido. Por favor, intenta de nuevo.');
                console.error('Error:', error);
            });
        });
    });
</script>