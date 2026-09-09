<?php
require_once 'helpers.php';
requiereSesion();

// Body esperado:
// { "filas": [ { "tee_time_id": 12, "campo": "Norte", "detalles": "Confirmado", "jugadores": ["a","b","","",""] }, ... ] }
//
// Antes de sobrescribir cada hoyo, lee su valor ACTUAL y lo compara con el
// nuevo. Solo si algo realmente cambió se registra en historial_cambios
// (accion = 'editar_hoyo'). Esto evita que el autoguardado silencioso de
// cada 18 segundos llene el historial con "cambios" cuando nadie editó nada.
$body  = leerJson();
$filas = $body['filas'] ?? [];

if (!$filas) {
    error('No se recibieron filas para guardar.');
}

// Convierte cadenas vacías (o solo espacios) a NULL. El frontend siempre manda
// texto para cada celda (nunca omite la clave, porque viene de textContent.trim()
// de un <span>), así que sin esta normalización una celda "vacía" se guardaría
// literal como '' en vez de NULL. Eso rompe cualquier consulta que dependa de
// IS NULL para decidir qué hoyo está libre (p.ej. socio_hoyos_disponibles.php).
function vacioANull($valor) {
    $valor = trim($valor ?? '');
    return $valor === '' ? null : $valor;
}

$stmtActual = $pdo->prepare(
    "SELECT t.fecha, t.tabla_num, t.hoyo, s.campo, s.detalles,
            s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
     FROM tee_times t JOIN tee_time_slots s ON t.id = s.tee_time_id
     WHERE s.tee_time_id = :id"
);

$stmtActualizar = $pdo->prepare(
    "UPDATE tee_time_slots
     SET campo = :campo,
         detalles = :detalles,
         jugador_1 = :j1, jugador_2 = :j2, jugador_3 = :j3, jugador_4 = :j4, jugador_5 = :j5
     WHERE tee_time_id = :id"
);

$pdo->beginTransaction();
try {
    foreach ($filas as $fila) {
        $j = $fila['jugadores'] ?? [];

        $campoNuevo     = vacioANull($fila['campo'] ?? null) ?? 'Norte';
        $detallesNuevo  = vacioANull($fila['detalles'] ?? null);
        $jugadoresNuevo = [
            vacioANull($j[0] ?? null),
            vacioANull($j[1] ?? null),
            vacioANull($j[2] ?? null),
            vacioANull($j[3] ?? null),
            vacioANull($j[4] ?? null),
        ];

        $stmtActual->execute(['id' => $fila['tee_time_id']]);
        $actual = $stmtActual->fetch();

        $stmtActualizar->execute([
            'campo' => $campoNuevo,
            'detalles' => $detallesNuevo,
            'j1' => $jugadoresNuevo[0], 'j2' => $jugadoresNuevo[1], 'j3' => $jugadoresNuevo[2],
            'j4' => $jugadoresNuevo[3], 'j5' => $jugadoresNuevo[4],
            'id' => $fila['tee_time_id']
        ]);

        if ($actual) {
            $antes = [
                'campo'     => $actual['campo'],
                'detalles'  => $actual['detalles'],
                'jugadores' => [$actual['jugador_1'], $actual['jugador_2'], $actual['jugador_3'], $actual['jugador_4'], $actual['jugador_5']]
            ];
            $despues = ['campo' => $campoNuevo, 'detalles' => $detallesNuevo, 'jugadores' => $jugadoresNuevo];

            if ($antes != $despues) {
                registrarCambio($pdo, 'editar_hoyo', $actual['fecha'], (int) $actual['tabla_num'], (int) $actual['hoyo'], $antes, $despues);
            }
        }
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al guardar: ' . $e->getMessage(), 500);
}

responder(['status' => 'success']);