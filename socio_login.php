<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';

$body     = leerJson();
$username = strtolower(trim($body['username'] ?? ''));
$password = $body['password'] ?? '';

if (!$username || !$password) {
    error('Usuario y contraseña son requeridos.');
}

// username + IP: cada combinación tiene su propio contador de intentos.
$identificador = $username . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');

if (loginSocioBloqueado($pdo, $identificador)) {
    error('Demasiados intentos fallidos. Intenta de nuevo en unos minutos.', 429);
}

$stmt = $pdo->prepare(
    "SELECT id, username, password_hash, nombre_completo, cuenta_activa
     FROM socios WHERE username = :u"
);
$stmt->execute(['u' => $username]);
$socio = $stmt->fetch();

if (!$socio || !$socio['cuenta_activa'] || !password_verify($password, $socio['password_hash'])) {
    registrarIntentoFallidoSocio($pdo, $identificador);
    error('Usuario o contraseña incorrectos.', 401);
}

limpiarIntentosSocio($pdo, $identificador);

$_SESSION['socio'] = [
    'id'              => $socio['id'],
    'username'        => $socio['username'],
    'nombre_completo' => $socio['nombre_completo']
];

responder(['status' => 'success', 'socio' => $_SESSION['socio']]);
