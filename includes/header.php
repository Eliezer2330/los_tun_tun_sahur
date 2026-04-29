<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Nombre del sistema — CAMBIA ESTO cuando sepas el problema
$NOMBRE_SISTEMA = "Sistema Web"; // Ej: "Sistema de Gestión de Biblioteca"
$NOMBRE_CORTO   = "SisWeb";      // Ej: "BiblioTec"
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $NOMBRE_SISTEMA ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- CSS propio -->
    <link rel="stylesheet" href="<?= str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2) ?>css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">
            <i class="bi bi-grid-3x3-gap-fill me-2"></i><?= $NOMBRE_CORTO ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php"><i class="bi bi-house me-1"></i>Inicio</a>
                </li>
                <!-- AGREGA MÁS MÓDULOS AQUÍ según el problema -->
                <li class="nav-item">
                    <a class="nav-link" href="../pages/listar.php"><i class="bi bi-list-ul me-1"></i>Registros</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../pages/agregar.php"><i class="bi bi-plus-circle me-1"></i>Nuevo</a>
                </li>
            </ul>
            <?php if (isset($_SESSION['usuario'])): ?>
            <div class="d-flex align-items-center">
                <span class="text-white me-3"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['usuario']) ?></span>
                <a href="../pages/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Salir
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- CONTENIDO PRINCIPAL -->
<main class="container mt-4">
