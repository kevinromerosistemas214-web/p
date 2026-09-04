<?php
require_once 'helpers.php';
requiereSesion();

// Body esperado: { "fecha": "2026-07-22", "tabla_num": 1 }
//
// Antes de vaciar, guarda en historial_cambios un snapshot ('vaciar_hoyo')
// de cada hoyo que SÍ tenía algo capturado, para poder consultarlo o
// recuperarlo manualmente después (vía insertar_jugadores.php).
$body     = leerJson();
$fecha    = $body['fecha'] ?? null;
$tablaNum = $body['tabla_num'] ?? null;

if (!$fecha || !$tablaNum) {
    error('Fecha y tabla_num son requeridos.');
}

$stmtActual = $pdo->prepare(
    "SELECT t.hoyo, s.campo, s.detalles, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
     FROM tee_times t JOIN tee_time_slots s ON t.id = s.tee_time_id
     WHERE t.fecha = :fecha AND t.tabla_num = :tabla"
);
$stmtActual->execute(['fecha' => $fecha, 'tabla' => $tablaNum]);
$filasActuales = $stmtActual->fetchAll();

$sql = "UPDATE tee_time_slots s
        SET jugador_1 = NULL, jugador_2 = NULL, jugador_3 = NULL, jugador_4 = NULL, jugador_5 = NULL, detalles = NULL
        FROM tee_times t
        WHERE s.tee_time_id = t.id AND t.fecha = :fecha AND t.tabla_num = :tabla";

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['fecha' => $fecha, 'tabla' => $tablaNum]);

    foreach ($filasActuales as $fila) {
        $teniaDatos = $fila['detalles'] || $fila['jugador_1'] || $fila['jugador_2'] || $fila['jugador_3'] || $fila['jugador_4'] || $fila['jugador_5'];
        if (!$teniaDatos) {
            continue; // ya estaba vacío, no vale la pena registrarlo
        }

        $antes = [
            'campo'     => $fila['campo'],
            'detalles'  => $fila['detalles'],
            'jugadores' => [$fila['jugador_1'], $fila['jugador_2'], $fila['jugador_3'], $fila['jugador_4'], $fila['jugador_5']]
        ];
        $despues = ['campo' => $fila['campo'], 'detalles' => null, 'jugadores' => [null, null, null, null, null]];

        registrarCambio($pdo, 'vaciar_hoyo', $fecha, (int) $tablaNum, (int) $fila['hoyo'], $antes, $despues);
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al vaciar: ' . $e->getMessage(), 500);
}

responder(['status' => 'success']);