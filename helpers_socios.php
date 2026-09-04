<?php
// =============================================================================
// helpers_socios.php
// Funciones exclusivas para la app de socios. Se apoya en $pdo, leerJson(),
// responder() y error() ya definidas en helpers.php — SIEMPRE se incluye
// DESPUÉS de helpers.php:
//     require_once 'helpers.php';
//     require_once 'helpers_socios.php';
//
// La sesión de socio vive en $_SESSION['socio'], una llave DISTINTA a la
// del staff ($_SESSION['usuario']). Así ambos sistemas de login conviven en
// el mismo servidor sin poder mezclarse ni escalar permisos por accidente.
// =============================================================================

function usuarioSesionSocio() {
    return $_SESSION['socio'] ?? null;
}

function requiereSesionSocio() {
    if (!usuarioSesionSocio()) {
        error('Debes iniciar sesión.', 401);
    }
}

// --- Rate limiting de login (protección básica de fuerza bruta) ---
// Necesario porque decidiste exponer esto a internet: sin esto, cualquiera
// puede probar contraseñas sin límite contra una cuenta de socio.
const SOCIOS_MAX_INTENTOS = 5;
const SOCIOS_BLOQUEO_MINUTOS = 15;

function loginSocioBloqueado(PDO $pdo, string $identificador): bool {
    $stmt = $pdo->prepare("SELECT bloqueado_hasta FROM intentos_login_socios WHERE identificador = :id");
    $stmt->execute(['id' => $identificador]);
    $fila = $stmt->fetch();
    if (!$fila || !$fila['bloqueado_hasta']) {
        return false;
    }
    return strtotime($fila['bloqueado_hasta']) > time();
}

function registrarIntentoFallidoSocio(PDO $pdo, string $identificador): void {
    $stmt = $pdo->prepare("SELECT intentos FROM intentos_login_socios WHERE identificador = :id");
    $stmt->execute(['id' => $identificador]);
    $fila = $stmt->fetch();

    if (!$fila) {
        $pdo->prepare("INSERT INTO intentos_login_socios (identificador, intentos) VALUES (:id, 1)")
            ->execute(['id' => $identificador]);
        return;
    }

    $intentos = (int) $fila['intentos'] + 1;
    $bloqueadoHasta = null;
    if ($intentos >= SOCIOS_MAX_INTENTOS) {
        $bloqueadoHasta = date('Y-m-d H:i:s', time() + SOCIOS_BLOQUEO_MINUTOS * 60);
    }

    $pdo->prepare(
        "UPDATE intentos_login_socios
         SET intentos = :intentos, bloqueado_hasta = :bloqueado, actualizado_en = CURRENT_TIMESTAMP
         WHERE identificador = :id"
    )->execute(['intentos' => $intentos, 'bloqueado' => $bloqueadoHasta, 'id' => $identificador]);
}

function limpiarIntentosSocio(PDO $pdo, string $identificador): void {
    $pdo->prepare("DELETE FROM intentos_login_socios WHERE identificador = :id")->execute(['id' => $identificador]);
}
