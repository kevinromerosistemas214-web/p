<?php
require_once 'helpers.php';
requiereAdmin();

// Body esperado:
// { "fecha_origen": "2026-07-20", "tabla_origen": 1, "tabla_destino": 2 }
//
// Copia (SIN borrar el origen) los jugadores/campo/detalles guardados en
// fecha_origen/tabla_origen hacia la tabla_destino del día que le corresponde
// actualmente (hoy si destino = 1, mañana si destino = 2), casando fila por
// fila por número de hoyo. Es de solo Administrador porque sobrescribe lo
// que haya actualmente en la tabla_destino.
//
// Cada hoyo que realmente cambia queda registrado en historial_cambios
// (accion = 'insertar_hoyo') con su valor anterior, por si hay que revertirlo.
$body         = leerJson();
$fechaOrigen  = $body['fecha_origen'] ?? null;
$tablaOrigen  = $body['tabla_origen'] ?? null;
$tablaDestino = $body['tabla_destino'] ?? null;

if (!$fechaOrigen || !in_array((int) $tablaOrigen, [1, 2]) || !in_array((int) $tablaDestino, [1, 2])) {
    error('fecha_origen, tabla_origen y tabla_destino (1 o 2) son requeridos.');
}
$tablaOrigen  = (int) $tablaOrigen;
$tablaDestino = (int) $tablaDestino;

// Asegura que la tabla/fecha destino (hoy o mañana, según corresponda) ya
// exista y esté completa antes de intentar escribir sobre ella.
asegurarDiaVigente($pdo);

$hoy    = date('Y-m-d');
$manana = date('Y-m-d', strtotime($hoy . ' +1 day'));
$fechaDestino = ($tablaDestino === 1) ? $hoy : $manana;

$sqlOrigen = "SELECT t.hoyo, s.campo, s.detalles, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
              FROM tee_times t JOIN tee_time_slots s ON t.id = s.tee_time_id
              WHERE t.fecha = :fecha AND t.tabla_num = :tabla
              ORDER BY t.hoyo";
$stmtOrigen = $pdo->prepare($sqlOrigen);
$stmtOrigen->execute(['fecha' => $fechaOrigen, 'tabla' => $tablaOrigen]);
$filasOrigen = $stmtOrigen->fetchAll();

if (!$filasOrigen) {
    error("No hay datos guardados para la Tabla $tablaOrigen en la fecha $fechaOrigen.", 404);
}

// Trae también los valores ACTUALES del destino (no solo el id) para poder
// registrar el "antes" en el historial antes de sobrescribirlos.
$sqlDestino = "SELECT t.id, t.hoyo, s.campo, s.detalles, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
               FROM tee_times t JOIN tee_time_slots s ON t.id = s.tee_time_id
               WHERE t.fecha = :fecha AND t.tabla_num = :tabla";
$stmtDestino = $pdo->prepare($sqlDestino);
$stmtDestino->execute(['fecha' => $fechaDestino, 'tabla' => $tablaDestino]);
$destinoPorHoyo = [];
foreach ($stmtDestino->fetchAll() as $fila) {
    $destinoPorHoyo[$fila['hoyo']] = $fila;
}

$actualizar = $pdo->prepare("UPDATE tee_time_slots
    SET campo = :campo, detalles = :detalles, jugador_1 = :j1, jugador_2 = :j2, jugador_3 = :j3, jugador_4 = :j4, jugador_5 = :j5
    WHERE tee_time_id = :id");

$pdo->beginTransaction();
try {
    $insertados = 0;
    foreach ($filasOrigen as $fila) {
        $filaDestino = $destinoPorHoyo[$fila['hoyo']] ?? null;
        if (!$filaDestino) continue; // no hay hoyo equivalente en la tabla destino, se ignora

        $actualizar->execute([
            'campo' => $fila['campo'],
            'detalles' => $fila['detalles'],
            'j1' => $fila['jugador_1'], 'j2' => $fila['jugador_2'], 'j3' => $fila['jugador_3'],
            'j4' => $fila['jugador_4'], 'j5' => $fila['jugador_5'],
            'id' => $filaDestino['id']
        ]);
        $insertados++;

        $antes = [
            'campo' => $filaDestino['campo'], 'detalles' => $filaDestino['detalles'],
            'jugadores' => [$filaDestino['jugador_1'], $filaDestino['jugador_2'], $filaDestino['jugador_3'], $filaDestino['jugador_4'], $filaDestino['jugador_5']]
        ];
        $despues = [
            'campo' => $fila['campo'], 'detalles' => $fila['detalles'],
            'jugadores' => [$fila['jugador_1'], $fila['jugador_2'], $fila['jugador_3'], $fila['jugador_4'], $fila['jugador_5']]
        ];

        if ($antes != $despues) {
            registrarCambio($pdo, 'insertar_hoyo', $fechaDestino, $tablaDestino, (int) $fila['hoyo'], $antes, $despues);
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al insertar: ' . $e->getMessage(), 500);
}

responder([
    'status' => 'success',
    'hoyos_insertados' => $insertados,
    'fecha_destino' => $fechaDestino
]);