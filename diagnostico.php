<?php
echo "<h2>Archivo php.ini en uso:</h2>";
echo php_ini_loaded_file();

echo "<h2>Drivers PDO disponibles:</h2>";
print_r(PDO::getAvailableDrivers());

echo "<h2>¿Extensión pgsql cargada?</h2>";
var_dump(extension_loaded('pgsql'));

echo "<h2>¿Extensión pdo_pgsql cargada?</h2>";
var_dump(extension_loaded('pdo_pgsql'));
