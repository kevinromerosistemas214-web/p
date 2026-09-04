<?php
// Ejecutar abriendo este archivo directamente en el navegador
// (ej. http://localhost/San_Carlos/seed.php).
// Ya NO es indispensable correrlo cada día: obtener_salidas.php llama a
// asegurarDiaVigente() en cada consulta y genera/traspasa automáticamente
// las salidas de Tabla 1 (hoy) y Tabla 2 (mañana). Este archivo sigue siendo
// útil para crear el usuario admin inicial la primera vez, o para forzar
// manualmente el traspaso/siembra del día actual.
require_once __DIR__ . '/helpers.php';

// Usuario admin inicial: kevin / 1221 (cámbiala luego desde el panel de Usuarios)
$hashAdmin = password_hash('1221', PASSWORD_BCRYPT);
$pdo->prepare("INSERT INTO users (username, password_hash, role)
               VALUES ('kevin', :h, 'admin')
               ON CONFLICT (username) DO NOTHING")
    ->execute(['h' => $hashAdmin]);

asegurarDiaVigente($pdo);

$hoy    = date('Y-m-d');
$manana = date('Y-m-d', strtotime($hoy . ' +1 day'));

header('Content-Type: text/plain; charset=utf-8');
echo "Listo.\n";
echo "Usuario admin: kevin / contraseña: 1221\n";
//echo "Usuario admin andres / contraseña: 1989\n";
echo "Tabla 1 (hoy: $hoy) y Tabla 2 (mañana: $manana) aseguradas, 40 hoyos c/u.\n";
echo "Nota: para otros días ya no hace falta correr este script; obtener_salidas.php\n";
echo "hace el traspaso y la siembra automáticamente la primera vez que alguien\n";
echo "abra el tablero ese día.\n";