<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: usuarios.php');
    exit;
}

// Obtener datos del usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: usuarios.php');
    exit;
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    $ver_precios = isset($_POST['ver_precios']) ? 1 : 0;
    $ver_formulas = isset($_POST['ver_formulas']) ? 1 : 0;
    $ver_reportes = isset($_POST['ver_reportes']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, correo=?, rol=?, ver_precios=?, ver_formulas=?, ver_reportes=? WHERE id=?");
    $stmt->execute([$nombre, $correo, $rol, $ver_precios, $ver_formulas, $ver_reportes, $id]);

    header("Location: usuarios.php");
    exit;
}
?>

<?php include 'header.php'; ?>
<div class="container mt-5" style="max-width: 600px;">
    <h3 class="text-danger mb-3">Editar Usuario</h3>
    <form method="POST">
        <div class="mb-3">
            <label>Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Correo</label>
            <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
        </div>

        <div class="mb-3">
            <label>Rol</label>
            <select name="rol" class="form-select" required>
                <?php foreach (['admin','gerente','operaciones','produccion','logistica'] as $rol_opcion): ?>
                    <option value="<?= $rol_opcion ?>" <?= $usuario['rol'] === $rol_opcion ? 'selected' : '' ?>>
                        <?= ucfirst($rol_opcion) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ver_precios" id="ver_precios" <?= $usuario['ver_precios'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="ver_precios">Puede ver precios</label>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="ver_formulas" id="ver_formulas" <?= $usuario['ver_formulas'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="ver_formulas">Puede ver fórmulas confidenciales</label>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="ver_reportes" id="ver_reportes" <?= $usuario['ver_reportes'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="ver_reportes">Puede ver reportes financieros</label>
        </div>

        <button type="submit" class="btn btn-success">Guardar cambios</button>
        <a href="usuarios.php" class="btn btn-secondary ms-2">Cancelar</a>
    </form>
</div>
<?php include 'footer.php'; ?>
