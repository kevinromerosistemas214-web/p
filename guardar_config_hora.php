<?php
require_once 'helpers.php';
requiereSesion();

// Body esperado: { "tabla_num": 1, "fecha": "2026-07-25", "hora": "14:30" }
$body     = leerJson();
$tablaNum = $body['tabla_num'] ?? null;
$fecha    = $body['fecha'] ?? null;
$hora     = $body['hora'] ?? null;

if (!$tablaNum || !in_array((int) $tablaNum, [1, 2]) || !$fecha || !$hora) {
    error('tabla_num, fecha y hora son requeridos.');
}

// El offset se calcula con la hora del SERVIDOR (no la del navegador que lo configuró),
// para que cualquier dispositivo que lo lea después obtenga el mismo punto en el tiempo,
// sin importar el reloj local de cada quien.
$timestampObjetivo = strtotime("$fecha $hora:00");
if ($timestampObjetivo === false) {
    error('Fecha u hora inválida.');
}

$offsetMs = ($timestampObjetivo - time()) * 1000;

$stmt = $pdo->prepare("INSERT INTO config_hora_tablas (tabla_num, offset_ms, actualizado_en)
                        VALUES (:tabla, :offset, NOW())
                        ON CONFLICT (tabla_num)
                        DO UPDATE SET offset_ms = EXCLUDED.offset_ms, actualizado_en = NOW()");
$stmt->execute(['tabla' => $tablaNum, 'offset' => $offsetMs]);

responder(['status' => 'success', 'offset_ms' => $offsetMs]);