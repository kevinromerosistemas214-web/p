<?php
require_once 'helpers.php';

$body = leerJson();
$username = strtolower(trim($body['username'] ?? ''));
$password = $body['password'] ?? '';

if (!$username || !$password) {
    error('Usuario y contraseña son requeridos.');
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :u");
$stmt->execute(['u' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    error('Usuario o contraseña incorrectos.', 401);
}

$_SESSION['usuario'] = [
    'id'       => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role']
];

responder(['status' => 'success', 'user' => $_SESSION['usuario']]);