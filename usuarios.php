<?php
require_once 'helpers.php';
requiereAdmin();

$metodo = $_SERVER['REQUEST_METHOD'];

// Roles válidos del sistema: admin (control total), operator (edita tablas),
// viewer (solo visualiza, sin edición ni herramientas administrativas).
$ROLES_VALIDOS = ['admin', 'operator', 'viewer'];

// ---- Listar usuarios ----
if ($metodo === 'GET') {
    $stmt = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY id");
    responder(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

// ---- Crear usuario ----
if ($metodo === 'POST') {
    $body     = leerJson();
    $username = strtolower(trim($body['username'] ?? ''));
    $password = $body['password'] ?? '';
    $role     = $body['role'] ?? 'operator';

    if (!$username || !$password) {
        error('Usuario y contraseña son requeridos.');
    }
    if (!in_array($role, $ROLES_VALIDOS)) {
        error('Rol inválido.');
    }

    $existe = $pdo->prepare("SELECT id FROM users WHERE username = :u");
    $existe->execute(['u' => $username]);
    if ($existe->fetch()) {
        error('El usuario ya existe.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (:u, :p, :r)");
    $stmt->execute(['u' => $username, 'p' => $hash, 'r' => $role]);

    responder(['status' => 'success']);
}

// ---- Actualizar rol y/o contraseña ----
if ($metodo === 'PUT') {
    $body     = leerJson();
    $id       = $body['id'] ?? null;
    $role     = $body['role'] ?? null;
    $password = $body['password'] ?? null;

    if (!$id) {
        error('id es requerido.');
    }

    if ($role !== null) {
        if (!in_array($role, $ROLES_VALIDOS)) {
            error('Rol inválido.');
        }
        // No permitir degradar al último administrador
        if ($role !== 'admin') {
            $actual = $pdo->prepare("SELECT role FROM users WHERE id = :id");
            $actual->execute(['id' => $id]);
            $filaActual = $actual->fetch();
            if ($filaActual && $filaActual['role'] === 'admin') {
                $total = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'")->fetch()['c'];
                if ($total <= 1) {
                    error('Debe existir al menos un administrador.');
                }
            }
        }
        $pdo->prepare("UPDATE users SET role = :r WHERE id = :id")->execute(['r' => $role, 'id' => $id]);
    }

    if ($password) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id")->execute(['p' => $hash, 'id' => $id]);
    }

    responder(['status' => 'success']);
}

// ---- Eliminar usuario ----
if ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        error('id es requerido.');
    }

    $fila = $pdo->prepare("SELECT role FROM users WHERE id = :id");
    $fila->execute(['id' => $id]);
    $u = $fila->fetch();

    if ($u && $u['role'] === 'admin') {
        $total = $pdo->query("SELECT COUNT(*) c FROM users WHERE role = 'admin'")->fetch()['c'];
        if ($total <= 1) {
            error('Debe existir al menos un administrador.');
        }
    }

    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute(['id' => $id]);
    responder(['status' => 'success']);
}

error('Método no soportado.', 405);