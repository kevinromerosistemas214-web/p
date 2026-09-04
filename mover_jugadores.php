<?php
require_once 'helpers.php';
requiereSesion();

// Body esperado: { "fecha": "2026-07-22" }
$body  = leerJson();
$fecha = $body['fecha'] ?? null;

if (!$fecha) {
    error('Fecha es requerida.');
}

// Datos actuales de la Tabla 2
$sql = "SELECT t.id, t.hoyo, s.campo, s.detalles, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
        FROM tee_times t JOIN tee_time_slots s ON t.id = s.tee_time_id
        WHERE t.fecha = :fecha AND t.tabla_num = 2
        ORDER BY t.hoyo";
$stmt = $pdo->prepare($sql);
$stmt->execute(['fecha' => $fecha]);
$filasOrigen = $stmt->fetchAll();

// Mapa hoyo -> tee_time_id de la Tabla 1
$sqlDestino = "SELECT id, hoyo FROM tee_times WHERE fecha = :fecha AND tabla_num = 1";
$stmtDestino = $pdo->prepare($sqlDestino);
$stmtDestino->execute(['fecha' => $fecha]);
$destinoPorHoyo = [];
foreach ($stmtDestino->fetchAll() as $fila) {
    $destinoPorHoyo[$fila['hoyo']] = $fila['id'];
}

$actualizar = $pdo->prepare("UPDATE tee_time_slots
    SET campo = :campo, detalles = :detalles, jugador_1 = :j1, jugador_2 = :j2, jugador_3 = :j3, jugador_4 = :j4, jugador_5 = :j5
    WHERE tee_time_id = :id");

$limpiar = $pdo->prepare("UPDATE tee_time_slots
    SET jugador_1 = NULL, jugador_2 = NULL, jugador_3 = NULL, jugador_4 = NULL, jugador_5 = NULL, detalles = NULL
    WHERE tee_time_id = :id");

$pdo->beginTransaction();
try {
    foreach ($filasOrigen as $fila) {
        $idDestino = $destinoPorHoyo[$fila['hoyo']] ?? null;
        if (!$idDestino) continue; // no hay hoyo equivalente en Tabla 1, se ignora

        $actualizar->execute([
            'campo' => $fila['campo'],
            'detalles' => $fila['detalles'],
            'j1' => $fila['jugador_1'], 'j2' => $fila['jugador_2'], 'j3' => $fila['jugador_3'],
            'j4' => $fila['jugador_4'], 'j5' => $fila['jugador_5'],
            'id' => $idDestino
        ]);
        $limpiar->execute(['id' => $fila['id']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Error al mover jugadores: ' . $e->getMessage(), 500);
}

responder(['status' => 'success']);