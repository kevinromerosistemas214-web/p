<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== ANTES de aplicar date_default_timezone_set ===\n";
echo "Zona horaria por defecto (php.ini): " . date_default_timezone_get() . "\n";
echo "Fecha/hora actual: " . date('Y-m-d H:i:s') . "\n\n";

date_default_timezone_set('America/Mexico_City');

echo "=== DESPUÉS de forzar America/Mexico_City ===\n";
echo "Zona horaria activa: " . date_default_timezone_get() . "\n";
echo "Fecha/hora actual: " . date('Y-m-d H:i:s') . "\n\n";

echo "=== Info adicional ===\n";
echo "php.ini en uso: " . php_ini_loaded_file() . "\n";
echo "Valor date.timezone en php.ini: " . ini_get('date.timezone') . "\n";
echo "Zend OPcache activo: " . (extension_loaded('Zend OPcache') ? 'SI' : 'NO') . "\n";
