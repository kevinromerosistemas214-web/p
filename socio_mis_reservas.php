<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';
requiereSesionSocio();

$socio = usuarioSesionSocio();

$stmt = $pdo->prepare(
    "SELECT t.id AS tee_time_id, t.fecha, t.tabla_num, t.hoyo, t.hora_salida,
            s.campo, s.detalles
     FROM tee_time_slots s
     JOIN tee_times t ON t.id = s.tee_time_id
     WHERE :socio_id IN (s.reservado_por_1, s.reservado_por_2, s.reservado_por_3, s.reservado_por_4, s.reservado_por_5)
     ORDER BY t.fecha, t.hora_salida"
);
$stmt->execute(['socio_id' => $socio['id']]);

responder(['status' => 'success', 'data' => $stmt->fetchAll()]);
