<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';

// Registro restringido: el socio debe existir YA en la tabla `socios`
// (dado de alta por el staff con "Guardar Socio"). Aquí solo se le permite
// asociar una cuenta (usuario/contraseña) a su propio registro — así se
// evita que cualquier persona en internet se registre como si fuera socio
// del club sin serlo. El id se obtiene de socio_buscar_para_registro.php.
$body     = leerJson();
$socioId  = $body['socio_id'] ?? null;
$username = strtolower(trim($body['username'] ?? ''));
$password = $body['password'] ?? '';

if (!$socioId || !$username || !$password) {
    error('Todos los campos son requeridos.');
}
if (strlen($password) < 8) {
    error('La contraseña debe tener al menos 8 caracteres.');
}
if (!preg_match('/^[a-z0-9_.]{3,50}$/', $username)) {
    error('El usuario solo puede tener letras minúsculas, números, punto y guión bajo.');
}

$stmt = $pdo->prepare("SELECT id, username FROM socios WHERE id = :id");
$stmt->execute(['id' => $socioId]);
$socio = $stmt->fetch();

if (!$socio) {
    error('Socio no encontrado. Contacta al club para verificar tu registro.', 404);
}
if ($socio['username'] !== null) {
    error('Este socio ya tiene una cuenta creada. Inicia sesión.', 409);
}

// El UNIQUE en socios.username protege contra carreras de dos personas
// registrando el mismo username al mismo tiempo; se captura el error 23505
// (unique_violation) de Postgres para dar un mensaje claro en vez de un 500.
try {
    $pdo->prepare("UPDATE socios SET username = :u, password_hash = :p WHERE id = :id")
        ->execute([
            'u'  => $username,
            'p'  => password_hash($password, PASSWORD_BCRYPT),
            'id' => $socioId
        ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        error('Ese nombre de usuario ya está en uso.', 409);
    }
    throw $e;
}

responder(['status' => 'success']);
