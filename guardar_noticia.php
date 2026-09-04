<?php
require_once 'helpers.php';
$usuarioActual = requiereSesion(); // Cualquier operador logueado (Admin u Operador), igual que guardar_config_hora.php

// Body esperado: { "activa": true, "texto": "...", "clima_ciudad": "...", "clima_lat": 19.25, "clima_lon": -99.60 }
$body        = leerJson();
$activa      = isset($body['activa']) ? (bool) $body['activa'] : false;
$texto       = isset($body['texto']) ? trim((string) $body['texto']) : '';
$climaCiudad = isset($body['clima_ciudad']) ? trim((string) $body['clima_ciudad']) : 'San Francisco Coaxusco, Metepec, México';
$climaLat    = isset($body['clima_lat']) ? (float) $body['clima_lat'] : 19.25934;
$climaLon    = isset($body['clima_lon']) ? (float) $body['clima_lon'] : -99.60175;

if ($activa && $texto === '') {
    error('No puedes activar el footer sin escribir un texto.');
}

if (mb_strlen($texto) > 500) {
    error('El texto no puede superar los 500 caracteres.');
}

if ($climaCiudad === '' || mb_strlen($climaCiudad) > 150) {
    error('La ciudad del clima no puede estar vacía ni superar 150 caracteres.');
}

if ($climaLat < -90 || $climaLat > 90 || $climaLon < -180 || $climaLon > 180) {
    error('Las coordenadas del clima no son válidas.');
}

$stmt = $pdo->prepare("INSERT INTO config_noticia_footer (id, activa, texto, clima_ciudad, clima_lat, clima_lon, actualizado_por, actualizado_en)
                        VALUES (1, :activa, :texto, :clima_ciudad, :clima_lat, :clima_lon, :usuario, NOW())
                        ON CONFLICT (id)
                        DO UPDATE SET activa = EXCLUDED.activa,
                                      texto = EXCLUDED.texto,
                                      clima_ciudad = EXCLUDED.clima_ciudad,
                                      clima_lat = EXCLUDED.clima_lat,
                                      clima_lon = EXCLUDED.clima_lon,
                                      actualizado_por = EXCLUDED.actualizado_por,
                                      actualizado_en = NOW()");
$stmt->execute([
    // PDO envía booleanos PHP como "" (string vacío) para false, y Postgres
    // rechaza "" como boolean válido. Con 1/0 sí lo interpreta correctamente.
    'activa'       => $activa ? 1 : 0,
    'texto'        => $texto,
    'clima_ciudad' => $climaCiudad,
    'clima_lat'    => $climaLat,
    'clima_lon'    => $climaLon,
    'usuario'      => $usuarioActual['username'] ?? 'desconocido'
]);

responder([
    'status'       => 'success',
    'activa'       => $activa,
    'texto'        => $texto,
    'clima_ciudad' => $climaCiudad,
    'clima_lat'    => $climaLat,
    'clima_lon'    => $climaLon
]);