<?php
header('Content-Type: application/json');
require_once 'conexion.php';

// Público (sin sesión): las pantallas en modo kiosco necesitan mostrar el
// footer y el clima aunque nadie haya iniciado sesión en ese dispositivo.
try {
    $stmt = $pdo->query("SELECT activa, texto, clima_ciudad, clima_lat, clima_lon FROM config_noticia_footer WHERE id = 1");
    $fila = $stmt->fetch();

    // Si por alguna razón la fila no existe todavía, se devuelve apagado
    // con la ubicación por defecto (San Francisco Coaxusco, Metepec).
    $resultado = $fila
        ? [
            'activa'       => (bool) $fila['activa'],
            'texto'        => $fila['texto'],
            'clima_ciudad' => $fila['clima_ciudad'],
            'clima_lat'    => (float) $fila['clima_lat'],
            'clima_lon'    => (float) $fila['clima_lon']
          ]
        : [
            'activa'       => false,
            'texto'        => '',
            'clima_ciudad' => 'San Francisco Coaxusco, Metepec, México',
            'clima_lat'    => 19.25934,
            'clima_lon'    => -99.60175
          ];

    echo json_encode(["status" => "success", "data" => $resultado]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}