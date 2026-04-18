<?php
//login.php

require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$mensaje_error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo']);
    $contrasena = $_POST['contrasena'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
    $stmt->execute(['correo' => $correo]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($contrasena, $usuario['contrasena'])) {
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['nombre'] = $usuario['nombre'];
        $_SESSION['rol'] = $usuario['rol'];
        header("Location: dashboard.php");
        exit;
    } else {
        $mensaje_error = "Correo o contraseña incorrectos.";
    }
}
?>

<?php include 'header.php'; ?>
<div class="full-bg">
  <div class="card-login">
    <div class="logo-box">
      <img src="assets/images/logo.png" alt="Logo A4 Paints">
    </div>

    <h2 class="text-center mb-4 text-danger">Iniciar Sesión</h2>

    <?php if ($mensaje_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($mensaje_error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="correo">Correo electrónico</label>
      <input type="email" name="correo" id="correo" required>

      <label for="contrasena">Contraseña</label>
      <input type="password" name="contrasena" id="contrasena" required>

      <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>
  </div>
</div>
<?php include 'footer.php'; ?>
