<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';
requiereSesionSocio();

$socio     = usuarioSesionSocio();
$body      = leerJson();
$teeTimeId = $body['tee_time_id'] ?? null;

if (!$teeTimeId) {
    error('Falta el tee_time_id.');
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "SELECT s.*, t.fecha, t.tabla_num, t.hoyo
         FROM tee_time_slots s JOIN tee_times t ON t.id = s.tee_time_id
         WHERE s.tee_time_id = :id FOR UPDATE"
    );
    $stmt->execute(['id' => $teeTimeId]);
    $fila = $stmt->fetch();

    if (!$fila) {
        $pdo->rollBack();
        error('Ese horario no existe.', 404);
    }

    // Busca el slot que le pertenece a ESTE socio; solo puede cancelar el
    // suyo, nunca el de otro jugador anotado en el mismo hoyo.
    $slotDelSocio = null;
    for ($i = 1; $i <= 5; $i++) {
        if ((int) ($fila["reservado_por_$i"] ?? 0) === (int) $socio['id']) {
            $slotDelSocio = $i;
            break;
        }
    }

    if ($slotDelSocio === null) {
        $pdo->rollBack();
        error('No tienes una reservación en este horario.', 404);
    }

    $nombreAnterior = $fila["jugador_$slotDelSocio"];

    $pdo->prepare(
        "UPDATE tee_time_slots
         SET jugador_$slotDelSocio = NULL, reservado_por_$slotDelSocio = NULL
         WHERE tee_time_id = :id"
    )->execute(['id' => $teeTimeId]);

    registrarCambio(
        $pdo, 'cancelacion_socio', $fila['fecha'], (int) $fila['tabla_num'], (int) $fila['hoyo'],
        ['jugador' => $nombreAnterior, 'slot' => $slotDelSocio], null,
        'socio: ' . $socio['username']
    );

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al cancelar: ' . $e->getMessage(), 500);
}

responder(['status' => 'success']);
