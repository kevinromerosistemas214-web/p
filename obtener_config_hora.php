<?php
header('Content-Type: application/json');
require_once 'conexion.php';

// Público (sin sesión): la pantalla de salidas necesita conocer la hora configurada
// aunque nadie haya iniciado sesión en ese dispositivo.
try {
    $stmt = $pdo->query("SELECT tabla_num, offset_ms FROM config_hora_tablas");
    $filas = $stmt->fetchAll();

    // Si una tabla nunca se ha configurado manualmente, el offset es 0 (usa la hora real).
    $resultado = ['1' => 0, '2' => 0];
    foreach ($filas as $fila) {
        $resultado[(string) $fila['tabla_num']] = (int) $fila['offset_ms'];
    }

    echo json_encode(["status" => "success", "data" => $resultado]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}