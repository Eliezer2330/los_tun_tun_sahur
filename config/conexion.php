<?php
// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// Cambia estos valores según tu MySQL en XAMPP
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // En XAMPP normalmente está vacío
define('DB_NAME', 'sigmea');     // Cambia por el nombre de tu BD
define('DB_PORT', 3308);         // Puerto personalizado de MySQL

// Crear conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Verificar conexión
if ($conn->connect_error) {
    die("<div style='color:red; padding:20px; font-family:sans-serif;'>
        <h3>❌ Error de conexión a la base de datos</h3>
        <p>" . $conn->connect_error . "</p>
        <p>Verifica que XAMPP esté corriendo y que la base de datos exista.</p>
    </div>");
}

// Configurar charset
$conn->set_charset("utf8");
?>
