<?php
// ============================================================
// config/db.php
// Conexión centralizada a MySQL.
// Incluir este archivo en todos los scripts PHP del proyecto.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3308');
define('DB_NAME', 'sigmea');
define('DB_USER', 'root');       // ← cambiar en producción
define('DB_PASS', '');           // ← cambiar en producción
define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una instancia PDO reutilizable (singleton).
 * Lanza una excepción si la conexión falla.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

// ── Headers comunes para todos los endpoints ──
function setJsonHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    // Permitir peticiones desde el mismo origen (ajustar en prod)
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// ── Respuestas estandarizadas ──
function jsonSuccess(array $data = [], string $message = 'OK', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
    exit;
}

function jsonError(string $message = 'Error', int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message, 'data' => null]);
    exit;
}
