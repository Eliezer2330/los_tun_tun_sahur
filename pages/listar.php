<?php
require_once '../config/conexion.php';
require_once '../includes/header.php';

// Mensaje de éxito/error desde otras páginas
$msg = $_GET['msg'] ?? '';
$tipo = $_GET['tipo'] ?? 'success';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-list-ul me-2 text-primary"></i>Lista de Registros</h2>
    <a href="agregar.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Nuevo Registro
    </a>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= htmlspecialchars($tipo) ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- BÚSQUEDA -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-8">
                <input type="text" name="buscar" class="form-control"
                       placeholder="Buscar por nombre, descripción..."
                       value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="bi bi-search me-1"></i>Buscar
                </button>
            </div>
            <div class="col-md-2">
                <a href="listar.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x-circle me-1"></i>Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- TABLA -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <!-- CAMBIA ESTAS COLUMNAS según tu tabla -->
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                // CAMBIA la consulta según tu tabla y columnas
                $buscar = $_GET['buscar'] ?? '';
                if ($buscar) {
                    $b = $conn->real_escape_string($buscar);
                    $sql = "SELECT * FROM registros WHERE nombre LIKE '%$b%' OR descripcion LIKE '%$b%' ORDER BY id DESC";
                } else {
                    $sql = "SELECT * FROM registros ORDER BY id DESC";
                }

                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <!-- CAMBIA estos campos según tu tabla -->
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td><?= htmlspecialchars($row['descripcion'] ?? '—') ?></td>
                        <td>
                            <?php if (($row['estado'] ?? '') == 'activo'): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['fecha_creacion'] ?? '—' ?></td>
                        <td class="text-center">
                            <a href="editar.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning me-1" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a href="eliminar.php?id=<?= $row['id'] ?>"
                               class="btn btn-sm btn-danger"
                               title="Eliminar"
                               onclick="return confirm('¿Estás seguro de eliminar este registro?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-6 d-block mb-2"></i>
                            No hay registros<?= $buscar ? ' que coincidan con la búsqueda' : '' ?>.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
