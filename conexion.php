<?php
// Fija la zona horaria del servidor a la del Estado de México / CDMX (UTC-6, sin
// horario de verano desde 2022). Esto evita que PHP calcule "hoy" en UTC y quede
// desfasado un día respecto a lo que pide el navegador (que usa la hora local).
date_default_timezone_set('America/Mexico_City');

$host     = 'localhost';
$port     = '5432';
$dbname   = 'san_carlos_db';
$user     = 'postgres';
$password = 'Kevinj12'; // La clave que configuraste en pgAdmin

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Error de conexión: " . $e->getMessage()]);
    exit;
}