<?php
require_once '../config/conexion.php';

// Obtener ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: listar.php?msg=ID+inválido&tipo=danger");
    exit;
}

// Obtener registro — CAMBIA 'registros' por tu tabla
$sql    = "SELECT * FROM registros WHERE id = $id";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    header("Location: listar.php?msg=Registro+no+encontrado&tipo=danger");
    exit;
}

$registro = $result->fetch_assoc();
$errores  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ---- VALIDACIONES ---- CAMBIA según tus campos
    $nombre      = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $estado      = $_POST['estado'] ?? 'activo';

    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio.";
    }
    if (strlen($nombre) > 100) {
        $errores[] = "El nombre no puede superar 100 caracteres.";
    }

    if (empty($errores)) {
        $n = $conn->real_escape_string($nombre);
        $d = $conn->real_escape_string($descripcion);
        $e = $conn->real_escape_string($estado);

        // CAMBIA según tu tabla y columnas
        $sql = "UPDATE registros SET nombre='$n', descripcion='$d', estado='$e' WHERE id=$id";

        if ($conn->query($sql)) {
            header("Location: listar.php?msg=Registro+actualizado+exitosamente&tipo=success");
            exit;
        } else {
            $errores[] = "Error al actualizar: " . $conn->error;
        }
    }
    // Si hay errores, mantenemos los valores del POST
    $registro = array_merge($registro, $_POST);
}

require_once '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-pencil-square me-2 text-warning"></i>Editar Registro #<?= $id ?></h2>
            <a href="listar.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>

        <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errores as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" novalidate>

                    <!-- CAMBIA estos campos según tu tabla -->
                    <div class="mb-3">
                        <label for="nombre" class="form-label fw-semibold">
                            Nombre <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nombre" name="nombre"
                               value="<?= htmlspecialchars($registro['nombre'] ?? '') ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion"
                                  rows="3"><?= htmlspecialchars($registro['descripcion'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="estado" class="form-label fw-semibold">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="activo"   <?= ($registro['estado'] ?? '') == 'activo'   ? 'selected' : '' ?>>Activo</option>
                            <option value="inactivo" <?= ($registro['estado'] ?? '') == 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="listar.php" class="btn btn-outline-secondary me-md-2">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-save me-1"></i>Actualizar Registro
                        </button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
