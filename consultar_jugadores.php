<?php
require_once 'helpers.php';
requiereAdmin();

// GET consultar_jugadores.php?fecha=2026-07-25
$fecha = $_GET['fecha'] ?? null;

if (!$fecha) {
    error('Fecha es requerida.');
}

$sql = "SELECT t.id, t.fecha, t.tabla_num, t.hoyo, TO_CHAR(t.hora_salida, 'HH24:MI') AS hora_salida,
               s.campo, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5, s.detalles
        FROM tee_times t
        LEFT JOIN tee_time_slots s ON t.id = s.tee_time_id
        WHERE t.fecha = :fecha
        ORDER BY t.tabla_num ASC, t.hoyo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['fecha' => $fecha]);
$filas = $stmt->fetchAll();

$resultado = ['1' => [], '2' => []];
foreach ($filas as $fila) {
    $resultado[(string) $fila['tabla_num']][] = $fila;
}

responder(['status' => 'success', 'data' => $resultado]);