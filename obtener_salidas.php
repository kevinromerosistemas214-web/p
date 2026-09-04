<?php
require_once __DIR__ . '/helpers.php';

// Endpoint público (sin sesión): la pantalla de salidas debe cargar aunque
// nadie haya iniciado sesión en ese dispositivo, igual que obtener_config_hora.php.

// GET obtener_salidas.php?fecha=2026-07-28&tabla_num=1
$fecha    = $_GET['fecha'] ?? null;
$tablaNum = $_GET['tabla_num'] ?? null;

if (!$fecha || !$tablaNum || !in_array((int) $tablaNum, [1, 2])) {
    error('fecha y tabla_num (1 o 2) son requeridos.');
}

// Asegura que el día esté vigente antes de leer: Tabla 1 = hoy, Tabla 2 = mañana.
// Si cruzó la medianoche desde la última consulta, aquí se hace el traspaso
// automático (re-etiquetado) de Tabla 2 -> Tabla 1 y se completa/siembra el
// nuevo "mañana" en Tabla 2. No depende de qué tabla/fecha se esté pidiendo:
// se ejecuta siempre para que ambas tablas queden consistentes.
asegurarDiaVigente($pdo);

$sql = "SELECT t.id, t.fecha, t.tabla_num, t.hoyo, TO_CHAR(t.hora_salida, 'HH24:MI') AS hora_salida,
               s.campo, s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5, s.detalles
        FROM tee_times t
        LEFT JOIN tee_time_slots s ON t.id = s.tee_time_id
        WHERE t.fecha = :fecha AND t.tabla_num = :tabla
        ORDER BY t.hoyo ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['fecha' => $fecha, 'tabla' => $tablaNum]);
$filas = $stmt->fetchAll();

responder(['status' => 'success', 'data' => $filas]);