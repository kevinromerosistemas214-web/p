<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';
requiereSesionSocio();

$fecha = $_GET['fecha'] ?? null;
if (!$fecha) {
    error('Falta el parámetro fecha.');
}

// Mismo patrón que el resto del sistema (obtener_salidas.php): garantiza que
// las 40 salidas de hoy/mañana existan antes de consultar, en vez de asumir
// que ya fueron sembradas por otra pantalla.
asegurarDiaVigente($pdo);

// tabla_num es opcional: si no se manda, se buscan ambas tablas (hoy/mañana).
$tablaNum = $_GET['tabla_num'] ?? null;

$sql = "SELECT t.id AS tee_time_id, t.tabla_num, t.hoyo, t.hora_salida,
               s.campo, s.detalles,
               s.jugador_1, s.jugador_2, s.jugador_3, s.jugador_4, s.jugador_5
        FROM tee_times t
        JOIN tee_time_slots s ON t.id = s.tee_time_id
        WHERE t.fecha = :fecha
          AND (s.jugador_1 IS NULL OR s.jugador_2 IS NULL OR s.jugador_3 IS NULL
               OR s.jugador_4 IS NULL OR s.jugador_5 IS NULL)";

$params = ['fecha' => $fecha];
if ($tablaNum) {
    $sql .= " AND t.tabla_num = :tabla_num";
    $params['tabla_num'] = $tablaNum;
}
$sql .= " ORDER BY t.tabla_num, t.hora_salida, t.hoyo";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll();

// Se agrega espacios_libres calculado, para que la app no lo recalcule.
$data = array_map(function ($f) {
    $jugadores = [$f['jugador_1'], $f['jugador_2'], $f['jugador_3'], $f['jugador_4'], $f['jugador_5']];
    $f['espacios_libres'] = count(array_filter($jugadores, fn($j) => $j === null));
    return $f;
}, $filas);

responder(['status' => 'success', 'data' => $data]);
