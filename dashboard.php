<?php
// dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

  // Helper para ocultar bloques a ciertos roles
  function ocultar_para(array $roles): bool {
      global $rol;
      return in_array($rol, $roles, true);
  }

$nombre = $_SESSION['nombre'];
$rol    = $_SESSION['rol'];

// Consultas para obtener los indicadores
$totalPend           = 0;
$pendientesEjecucion = 0;
$pendientesFichas    = 0;
$pendientesAutorizar = 0;

if (in_array($rol, ['produccion', 'admin', 'gerente'])) {
    $totalPend = (int)$pdo->query("
        SELECT COUNT(*) FROM ordenes_produccion
        WHERE estado_autorizacion='autorizada' AND estado='pendiente'
        AND (cancelada = 0 OR cancelada IS NULL)
    ")->fetchColumn();
    $pendientesEjecucion = $totalPend;
    $pendientesFichas    = (int)$pdo->query("
        SELECT COUNT(*) FROM fichas_produccion fp
        LEFT JOIN ordenes_produccion op ON fp.id=op.ficha_id
        WHERE op.id IS NULL
    ")->fetchColumn();
    $pendientesAutorizar = (int)$pdo->query("
        SELECT COUNT(*) FROM ordenes_produccion
        WHERE estado_autorizacion='pendiente'
    ")->fetchColumn();
}

$pendientesSD = (int)$pdo->query("
    SELECT COUNT(*) FROM C_distribuidores_registro WHERE status='pendiente'
")->fetchColumn();

$pendientesOV = (int)$pdo->query("
    SELECT COUNT(*) FROM ordenes_venta WHERE estado='pendiente'
")->fetchColumn();

$pendientesFinancieros = (int)$pdo->query("
    SELECT COUNT(*) FROM costos_lote WHERE fecha_costo IS NULL
")->fetchColumn();

$pendientesCompra = (int)$pdo->query("
    SELECT COUNT(*) FROM ordenes_compra WHERE estado='pendiente'
")->fetchColumn();

$pendientesEmpaque = (int)$pdo->query("
    SELECT COUNT(*) FROM packaging_requests WHERE estado='pendiente'
")->fetchColumn();

$proveedoresSinCatalogar = (int)$pdo->query("
    SELECT COUNT(*) FROM proveedores WHERE activo=0
")->fetchColumn();

$ordenesCompraPendientes = (int)$pdo->query("
    SELECT COUNT(*) FROM ordenes_compra WHERE estado='borrador'
")->fetchColumn();

$ordenesCompraAutorizar = (int)$pdo->query("
    SELECT COUNT(*) FROM ordenes_compra WHERE estado='solicitada'
")->fetchColumn();


include 'header.php';
?>
<div class="container mt-5">
    <div class="d-flex align-items-center justify-content-center mb-4">
        <!-- Logo -->
        <div class="flex-shrink-0">
            <img src="assets/images/logo.png"
                 alt="a4 Paints"
                 style="height:100px; max-width:200px;">
        </div>
        <!-- Texto de bienvenida -->
        <div class="flex-grow-1 ms-3 text-start">
            <h1 class="h4 mb-1">Bienvenido, <?= htmlspecialchars($nombre) ?></h1>
            <p class="mb-0 text-secondary">Rol: <?= htmlspecialchars($rol) ?></p>
        </div>
    </div>

    <!-- Mensaje rápido de producción -->
    <?php if (in_array($rol, ['produccion', 'admin', 'gerente'])): ?>
        <div class="alert alert-info">
            Tienes <strong><?= $totalPend ?></strong> órdenes pendientes por ejecutar.
        </div>
    <?php endif; ?>

    <!-- Accordion Principal -->
    <div class="accordion" id="dashboardAccordion">

        <!-- PRODUCCIÓN -->
        <?php if (in_array($rol, ['produccion', 'admin', 'gerente'])): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingProd">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseProd" aria-expanded="false"
                            aria-controls="collapseProd">
                        PRODUCCIÓN
                    </button>
                </h2>
                <div id="collapseProd" class="accordion-collapse collapse"
                     aria-labelledby="headingProd" data-bs-parent="#dashboardAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <!-- Ejecutar Producción -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-success position-relative">
                                    <h5 class="text-success">Ejecutar Producción</h5>
                                    <p class="small">Visualiza y reporta volumen producido.</p>
                                    <?php if ($pendientesEjecucion > 0): ?>
                                        <span class="badge bg-success position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesEjecucion ?> autorizadas
                                        </span>
                                    <?php endif; ?>
                                    <a href="ejecutar_produccion.php" class="btn btn-outline-success btn-sm mt-2">Ir a Ejecución</a>
                                </div>
                            </div>
                            <!-- Crear Ficha -->
                            <?php if ($rol === 'admin'): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-primary position-relative">
                                        <h5 class="text-primary">Crear Ficha de Producción</h5>
                                        <p class="small">Receta base con gramaje exacto.</p>
                                        <?php if ($pendientesFichas > 0): ?>
                                            <span class="badge bg-primary position-absolute"
                                                  style="top:1rem; right:1rem;">
                                                <?= $pendientesFichas ?> pendientes
                                            </span>
                                        <?php endif; ?>
                                        <a href="fichas_produccion.php" class="btn btn-outline-primary btn-sm mt-2">Ir a Fichas</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Autorizar Producción -->
                            <?php if (in_array($rol, ['admin', 'gerente'])): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-warning position-relative">
                                        <h5 class="text-warning">Autorizar Producción</h5>
                                        <p class="small">Revisa y autoriza órdenes.</p>
                                        <?php if ($pendientesAutorizar > 0): ?>
                                            <span class="badge bg-warning position-absolute"
                                                  style="top:1rem; right:1rem;">
                                                <?= $pendientesAutorizar ?> por autorizar
                                            </span>
                                        <?php endif; ?>
                                        <a href="autorizar_produccion.php" class="btn btn-outline-warning btn-sm mt-2">Autorizar</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Autorizar Envases -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-success position-relative">
                                    <h5 class="text-success">Autorizar Envases</h5>
                                    <p class="small">Se autoriza entrega de envases.</p>
                                    <?php if ($pendientesEjecucion > 0): ?>
                                        <span class="badge bg-success position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesEjecucion ?> autorizadas
                                        </span>
                                    <?php endif; ?>
                                    <a href="autorizar_envases.php" class="btn btn-outline-success btn-sm mt-2">Ir a Autorizar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- VENTAS -->
        <?php if (in_array($rol, ['admin', 'logistica','produccion','gerente'])): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingVentas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseVentas" aria-expanded="false"
                            aria-controls="collapseVentas">
                        VENTAS
                    </button>
                </h2>

                 <div id="collapseVentas" class="accordion-collapse collapse"
                     aria-labelledby="headingVentas" data-bs-parent="#dashboardAccordion">
                    <div class="accordion-body">
                                            
                        <div class="row g-3">
                            <!-- Órdenes de Venta -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-danger position-relative">
                                    <h5 class="text-danger">Órdenes de Venta</h5>
                                    <p class="small">Revisa pedidos y controla despacho.</p>
                                    <?php if ($pendientesOV > 0): ?>
                                        <span class="badge bg-danger position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesOV ?> por despachar
                                        </span>
                                    <?php endif; ?>
                                    <a href="ordenes_venta.php" class="btn btn-outline-danger btn-sm mt-2">Logística</a>
                                </div>
                            </div>
                     
                            <!-- Solicitud de Alta de Distribuidores -->
                        <?php if (in_array($rol, ['admin','gerente'])): ?>   
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-danger position-relative">
                                    <h5 class="text-danger">Solicitudes de Distribución</h5>
                                    <p class="small">Solicitudes desde pagina web para distrubuir productos nuestros.</p>
                                    <?php if ($pendientesSD > 0): ?>
                                        <span class="badge bg-danger position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesOV ?> por despachar
                                        </span>
                                    <?php endif; ?>
                                    <a href="channels_aprobaciones.php" class="btn btn-outline-danger btn-sm mt-2">Solicitudes Pendientes</a>
                            </div>
                      </div>
                 <?php endif; ?>
                            
                          <!-- Clientes -->
                          <?php if (in_array($rol, ['admin','gerente'])): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-info">
                                    <h5 class="text-info">Clientes</h5>
                                    <p class="small">Alta Distribuidores y Clientes finales.</p>
                                    <a href="clientes.php" class="btn btn-outline-info btn-sm mt-2">Ir a Clientes</a>
                                </div>
                            </div>
                            
                        <?php endif; ?>
                            
                            <!-- Recepción PT -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-info">
                                    <h5 class="text-info">Recepción de PT</h5>
                                    <p class="small">Verifica y firma productos terminados.</p>
                                    <a href="recepcion_prod.php" class="btn btn-outline-info btn-sm mt-2">Ir a Recepción</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- INVENTARIOS -->
        <?php if (in_array($rol, ['produccion', 'admin', 'gerente', 'logistica', 'operaciones'])): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingInv">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseInv" aria-expanded="false"
                            aria-controls="collapseInv">
                        INVENTARIOS
                    </button>
                </h2>
                <div id="collapseInv" class="accordion-collapse collapse"
                     aria-labelledby="headingInv" data-bs-parent="#dashboardAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <!-- MP: solo para todos menos logística -->
                            <?php if ($rol !== 'logistica'): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-secondary">
                                        <h5 class="text-secondary">Inventario de Materias Primas</h5>
                                        <p class="small">Ver, ajustar y registrar movimientos.</p>
                                        <a href="inventario_mp.php" class="btn btn-outline-secondary btn-sm mt-2">
                                            Ir a Materias Primas
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- IC: siempre para logística, admin y gerente -->
                            <?php if (in_array($rol, ['logistica', 'admin', 'gerente'])): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-secondary">
                                        <h5 class="text-secondary">Inventario de Insumos Comerciales</h5>
                                        <p class="small">Ver y registrar movimientos.</p>
                                        <a href="inventario_insumos.php" class="btn btn-outline-secondary btn-sm mt-2">
                                            Ir a Insumos
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php if (in_array($rol, ['logistica', 'admin', 'gerente', 'produccion'])): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-secondary">
                                        <h5 class="text-secondary">Inventario de Productos</h5>
                                        <p class="small">Productos terminados.</p>
                                        <a href="terminados.php" class="btn btn-outline-secondary btn-sm mt-2">
                                            Ir a Productos
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- COMPRAS -->
        <?php if (in_array($rol, ['admin', 'gerente', 'logistica','produccion'])): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingCompras">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseCompras" aria-expanded="false"
                            aria-controls="collapseCompras">
                        COMPRAS
                    </button>
                </h2>
                <div id="collapseCompras" class="accordion-collapse collapse"
                     aria-labelledby="headingCompras" data-bs-parent="#dashboardAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <?php if ($rol !== 'logistica'): // admin/gerente solo ?>
                                <!-- Catálogo Proveedores -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-primary position-relative">
                                        <h5 class="text-primary">Catálogo de Proveedores</h5>
                                        <p class="small">Mantén tus proveedores siempre al día.</p>
                                        <?php if ($proveedoresSinCatalogar > 0): ?>
                                            <span class="badge bg-primary position-absolute"
                                                  style="top:1rem; right:1rem;">
                                                <?= $proveedoresSinCatalogar ?> sin catalogar
                                            </span>
                                        <?php endif; ?>
                                        <a href="proveedores.php" class="btn btn-outline-primary btn-sm mt-2">
                                            Ir a Proveedores
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- Órdenes de Compra (todos los roles la ven) -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-success position-relative">
                                    <h5 class="text-success">Órdenes de Compra</h5>
                                    <p class="small">Crea y descarga tus OC en PDF.</p>
                                    <?php if ($ordenesCompraPendientes > 0): ?>
                                        <span class="badge bg-success position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $ordenesCompraPendientes ?> borradores
                                        </span>
                                    <?php endif; ?>
                                    <a href="ordenes_compra.php" class="btn btn-outline-success btn-sm mt-2">
                                        Ir a OC
                                    </a>
                                </div>
                            </div>
                            <?php if ($rol !== 'logistica'): // admin/gerente solo ?>
                                <!-- Autorizar OC -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="card p-3 border-warning position-relative">
                                        <h5 class="text-warning">Autorizar OC</h5>
                                        <p class="small">Aprueba y envía tu OC al proveedor.</p>
                                        <?php if ($ordenesCompraAutorizar > 0): ?>
                                            <span class="badge bg-warning position-absolute"
                                                  style="top:1rem; right:-0.5rem;">
                                                <?= $ordenesCompraAutorizar ?> por autorizar
                                            </span>
                                        <?php endif; ?>
                                        <a href="autorizar_compra.php" class="btn btn-outline-warning btn-sm mt-2">
                                            Autorizar OC
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- REPORTES -->
        <?php if (in_array($rol, ['admin', 'gerente'])): ?>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingRep">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseRep" aria-expanded="false"
                            aria-controls="collapseRep">
                        REPORTES
                    </button>
                </h2>
                <div id="collapseRep" class="accordion-collapse collapse"
                     aria-labelledby="headingRep" data-bs-parent="#dashboardAccordion">
                    <div class="accordion-body">
                        <div class="row g-3">
                            <!-- Resumen Producción -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-info">
                                    <h5 class="text-info">Resumen de Producción</h5>
                                    <p class="small">Métricas y subprocesos.</p>
                                    <a href="ordenes_produccion.php" class="btn btn-outline-info btn-sm mt-2">Ir</a>
                                </div>
                            </div>
                            <!-- Reportes Financieros -->
                            <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-secondary position-relative">
                                    <h5 class="text-secondary">Reportes Financieros</h5>
                                    <p class="small">Costo por lote, utilidad por cliente.</p>
                                    <?php if ($pendientesFinancieros > 0): ?>
                                        <span class="badge bg-secondary position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesFinancieros ?> sin costear
                                        </span>
                                    <?php endif; ?>
                                    <a href="reportes.php" class="btn btn-outline-secondary btn-sm mt-2">Ver</a>
                                </div>
                            </div>
                             <div class="col-md-6 col-lg-4">
                                <div class="card p-3 border-secondary position-relative">
                                    <h5 class="text-secondary">Costos Lotes</h5>
                                    <p class="small">Costo por lote.</p>
                                    <?php if ($pendientesFinancieros > 0): ?>
                                        <span class="badge bg-secondary position-absolute"
                                              style="top:1rem; right:1rem;">
                                            <?= $pendientesFinancieros ?> sin costear
                                        </span>
                                    <?php endif; ?>
                                    <a href="reportes_costos_lote.php" class="btn btn-outline-secondary btn-sm mt-2">Ver</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div> <!-- /.accordion -->
</div> <!-- /.container -->

<!-- Badge dinámico para 'Empaque' en el navbar (clona estilo del primer badge existente) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var count = <?= (int)$pendientesEmpaque ?>;
  if (!count) return;
  // Localiza el item "Empaque" por href o por texto
  var link = document.querySelector('nav a[href*="packaging_pendientes.php"]')
         || Array.from(document.querySelectorAll('nav a')).find(a => a.textContent.trim().toLowerCase() === 'empaque');
  if (!link) return;
  if (link.querySelector('.mpo-badge-empaque')) return;

  // Toma el PRIMER badge del navbar como referencia para copiar clases/estilos
  var refBadge = document.querySelector('nav .badge');
  var b = document.createElement('span');
  b.textContent = count;
  b.className = (refBadge ? refBadge.className : 'badge rounded-pill bg-danger');
  b.classList.add('mpo-badge-empaque');
  if (refBadge && refBadge.getAttribute('style')) {
    b.setAttribute('style', refBadge.getAttribute('style')); // copia inline style (posición/tamaño)
  } else {
    // fallback: coloca el badge como los otros (arribita a la derecha del link)
    link.style.position = 'relative';
    b.style.position = 'absolute';
    b.style.top = '-0.35rem';
    b.style.right = '-0.6rem';
    b.style.fontSize = '0.75rem';
    b.style.lineHeight = '1';
    b.style.padding = '0.35em 0.55em';
  }
  // Asegura que el contenedor permita posicionamiento absoluto
  if (getComputedStyle(link).position === 'static') link.style.position = 'relative';
  link.appendChild(b);
});
</script>

<?php include 'footer.php'; ?>