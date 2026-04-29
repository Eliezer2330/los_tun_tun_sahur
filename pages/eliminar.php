<?php
require_once '../config/conexion.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    header("Location: listar.php?msg=ID+inválido&tipo=danger");
    exit;
}

// CAMBIA 'registros' por tu tabla
$sql = "DELETE FROM registros WHERE id = $id";

if ($conn->query($sql)) {
    if ($conn->affected_rows > 0) {
        header("Location: listar.php?msg=Registro+eliminado+correctamente&tipo=success");
    } else {
        header("Location: listar.php?msg=Registro+no+encontrado&tipo=warning");
    }
} else {
    header("Location: listar.php?msg=Error+al+eliminar:+" . urlencode($conn->error) . "&tipo=danger");
}
exit;
?>
