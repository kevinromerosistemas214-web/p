<?php
// Incluir SIEMPRE al inicio de cada endpoint (login.php, obtener_salidas.php, etc.)
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

function responder($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function error($mensaje, $code = 400) {
    responder(["status" => "error", "message" => $mensaje], $code);
}

function usuarioSesion() {
    return $_SESSION['usuario'] ?? null;
}

// Cualquier operador logueado (Admin u Operador)
function requiereSesion() {
    $u = usuarioSesion();
    if (!$u) {
        error('Debes iniciar sesión.', 401);
    }
    return $u;
}

// Solo Administradores
function requiereAdmin() {
    $u = requiereSesion();
    if ($u['role'] !== 'admin') {
        error('No autorizado. Se requiere rol de administrador.', 403);
    }
    return $u;
}

function leerJson() {
    $data = json_decode(file_get_contents('php://input'), true);
    return $data ?? [];
}

// $usuarioForzado es opcional: si se manda, tiene prioridad sobre la sesión
// de staff ($_SESSION['usuario']). Necesario para acciones originadas por la
// app de socios, que usan una llave de sesión distinta ($_SESSION['socio']) y
// por lo tanto usuarioSesion() no las ve — sin esto, el historial registraría
// "desconocido" en cada reserva/cancelación hecha por un socio.
function registrarCambio($pdo, $accion, $fecha, $tablaNum, $hoyo, $antes, $despues, $usuarioForzado = null) {
    $usuarioActual = usuarioSesion();
    $nombreUsuario = $usuarioForzado ?? ($usuarioActual['username'] ?? 'desconocido');

    $stmt = $pdo->prepare(
        "INSERT INTO historial_cambios (usuario, accion, fecha, tabla_num, hoyo, valores_anteriores, valores_nuevos)
         VALUES (:usuario, :accion, :fecha, :tabla, :hoyo, :antes::jsonb, :despues::jsonb)"
    );
    $stmt->execute([
        'usuario' => $nombreUsuario,
        'accion'  => $accion,
        'fecha'   => $fecha,
        'tabla'   => $tablaNum,
        'hoyo'    => $hoyo,
        'antes'   => $antes !== null ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        'despues' => $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null
    ]);
}

function generarHorariosPredeterminados() {
    $horarios = [];
    $h = 7; $m = 0;
    for ($i = 0; $i < 40; $i++) {
        $horarios[] = sprintf('%02d:%02d', $h, $m);
        $m += 10;
        if ($m >= 60) {
            $m -= 60;
            $h += 1;
        }
    }
    return $horarios;
}

function asegurarSalidasParaFechaYTabla($pdo, $fecha, $tabla) {
    $horarios = generarHorariosPredeterminados();
    $totalEsperado = count($horarios);

    $contarStmt = $pdo->prepare(
        "SELECT COUNT(*) AS c FROM tee_times WHERE fecha = :fecha AND tabla_num = :tabla"
    );
    $contarStmt->execute(['fecha' => $fecha, 'tabla' => $tabla]);
    $existentes = (int) $contarStmt->fetch()['c'];

    if ($existentes >= $totalEsperado) {
        return;
    }

    $transaccionPropia = !$pdo->inTransaction();
    if ($transaccionPropia) {
        $pdo->beginTransaction();
    }

    try {
        $insertarTeeTime = $pdo->prepare(
            "INSERT INTO tee_times (fecha, tabla_num, hoyo, hora_salida)
             VALUES (:f, :t, :h, :hh)
             ON CONFLICT (fecha, tabla_num, hoyo) DO NOTHING
             RETURNING id"
        );

        $buscarIdExistente = $pdo->prepare(
            "SELECT id FROM tee_times WHERE fecha = :f AND tabla_num = :t AND hoyo = :h"
        );

        $insertarSlot = $pdo->prepare(
            "INSERT INTO tee_time_slots (tee_time_id, campo) VALUES (:id, 'Norte')
             ON CONFLICT (tee_time_id) DO NOTHING"
        );

        foreach ($horarios as $i => $hora) {
            $hoyo = $i + 1;
            $insertarTeeTime->execute(['f' => $fecha, 't' => $tabla, 'h' => $hoyo, 'hh' => $hora]);
            $filaNueva = $insertarTeeTime->fetch();

            if ($filaNueva) {
                $teeTimeId = $filaNueva['id'];
            } else {
                $buscarIdExistente->execute(['f' => $fecha, 't' => $tabla, 'h' => $hoyo]);
                $filaExistente = $buscarIdExistente->fetch();
                $teeTimeId = $filaExistente['id'] ?? null;
            }

            if ($teeTimeId) {
                $insertarSlot->execute(['id' => $teeTimeId]);
            }
        }

        if ($transaccionPropia) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($transaccionPropia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function asegurarDiaVigente($pdo) {
    $hoy    = date('Y-m-d');
    $manana = date('Y-m-d', strtotime($hoy . ' +1 day'));

    $pdo->beginTransaction();
    try {
        $pdo->exec("SELECT pg_advisory_xact_lock(778899)");

        $contarStmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM tee_times WHERE fecha = :fecha AND tabla_num = :tabla"
        );

        $contarStmt->execute(['fecha' => $hoy, 'tabla' => 1]);
        $tabla1CompletaHoy = (int) $contarStmt->fetch()['c'] >= 40;

        if (!$tabla1CompletaHoy) {
            $pdo->prepare(
                "UPDATE tee_times SET tabla_num = 1 WHERE fecha = :hoy AND tabla_num = 2"
            )->execute(['hoy' => $hoy]);

            asegurarSalidasParaFechaYTabla($pdo, $hoy, 1);
        }

        asegurarSalidasParaFechaYTabla($pdo, $manana, 2);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error('Error al asegurar el día vigente: ' . $e->getMessage(), 500);
    }
}
