<?php
// entrega_pedido.php
require 'config.php';
if (!isset($_SESSION['user_id'])||!in_array($_SESSION['rol'],['admin','gerente','logistica'])){
  header('Location: dashboard.php');exit;
}

$id = intval($_GET['id'] ?? 0);

// 1) Guardar entrega
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['cajas'])){
  $c = max(0,intval($_POST['cajas']));
  $stmt = $pdo->prepare("
    UPDATE ordenes_venta
       SET cajas_usadas=?, estado='entregado'
     WHERE id=?
  ");
  $stmt->execute([$c,$id]);
  header("Location: entrega_pedido.php?id=$id");
  exit;
}

// 2) Leer datos
$ov = $pdo->prepare("SELECT * FROM ordenes_venta WHERE id=?");
$ov->execute([$id]); $o = $ov->fetch();
if (!$o){ die("Pedido no encontrado"); }

// 3) Mostrar
include 'header.php'; ?>
<div class="container mt-4">
  <h3 class="text-success mb-3">Entrega Pedido #<?=$o['id']?></h3>
  <p><strong>Cliente:</strong> <?=$o['cliente_id']?> &middot;
     <strong>Surtido:</strong> <?=$o['avance']?>%</p>
  <form method="POST" class="row g-3">
    <div class="col-md-3">
      <label>Cajas utilizadas</label>
      <input type="number" name="cajas" min="0" class="form-control"
             value="<?=$o['cajas_usadas']?>">
    </div>
    <div class="col-auto align-self-end">
      <button class="btn btn-success">Registrar entrega</button>
    </div>
  </form>
</div>
<?php include 'footer.php';
