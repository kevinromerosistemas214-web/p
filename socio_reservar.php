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
    // FOR UPDATE bloquea esta fila hasta que termine la transacción. Si dos
    // socios reservan el mismo hoyo casi al mismo tiempo, el segundo espera
    // aquí (no falla, solo espera) hasta que el primero termine, y entonces
    // ve el estado YA actualizado — no el que tenía cuando cargó la pantalla.
    // Esto es lo que evita que dos personas terminen en el mismo slot.
    $stmt = $pdo->prepare(
        "SELECT s.*, t.fecha, t.tabla_num, t.hoyo
         FROM tee_time_slots s
         JOIN tee_times t ON t.id = s.tee_time_id
         WHERE s.tee_time_id = :id
         FOR UPDATE"
    );
    $stmt->execute(['id' => $teeTimeId]);
    $fila = $stmt->fetch();

    if (!$fila) {
        $pdo->rollBack();
        error('Ese horario no existe.', 404);
    }

    // Evita que el mismo socio quede anotado dos veces en el mismo hoyo.
    $yaAnotado = in_array($socio['nombre_completo'], [
        $fila['jugador_1'], $fila['jugador_2'], $fila['jugador_3'], $fila['jugador_4'], $fila['jugador_5']
    ], true);
    if ($yaAnotado) {
        $pdo->rollBack();
        error('Ya tienes una reservación en este horario.', 409);
    }

    $slotLibre = null;
    for ($i = 1; $i <= 5; $i++) {
        if ($fila["jugador_$i"] === null) {
            $slotLibre = $i;
            break;
        }
    }

    if ($slotLibre === null) {
        $pdo->rollBack();
        error('Este horario ya se llenó. Elige otro.', 409);
    }

    $pdo->prepare(
        "UPDATE tee_time_slots
         SET jugador_$slotLibre = :nombre, reservado_por_$slotLibre = :socio_id
         WHERE tee_time_id = :id"
    )->execute([
        'nombre'   => $socio['nombre_completo'],
        'socio_id' => $socio['id'],
        'id'       => $teeTimeId
    ]);

    // Aparece en el mismo Historial de Cambios que ya usa el staff, con una
    // acción nueva ('reserva_socio') para distinguirla de las ediciones
    // manuales. El frontend necesita agregar su etiqueta (ver nota abajo).
    registrarCambio(
        $pdo, 'reserva_socio', $fila['fecha'], (int) $fila['tabla_num'], (int) $fila['hoyo'],
        null, ['jugador' => $socio['nombre_completo'], 'slot' => $slotLibre],
        'socio: ' . $socio['username']
    );

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al reservar: ' . $e->getMessage(), 500);
}

responder(['status' => 'success', 'slot' => $slotLibre]);
