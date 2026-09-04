<?php
require_once 'helpers.php';
requiereAdmin();

// GET obtener_historial.php?fecha=2026-07-25&tabla_num=1&limite=200
// fecha y tabla_num son opcionales (si se omiten, no se filtra por ellos).
$fecha    = $_GET['fecha'] ?? null;
$tablaNum = $_GET['tabla_num'] ?? null;
$limite   = min((int) ($_GET['limite'] ?? 200), 500);
if ($limite <= 0) { $limite = 200; }

$condiciones = [];
$parametros  = [];

if ($fecha) {
    $condiciones[] = 'fecha = :fecha';
    $parametros['fecha'] = $fecha;
}
if ($tablaNum !== null && in_array((int) $tablaNum, [1, 2])) {
    $condiciones[] = 'tabla_num = :tabla';
    $parametros['tabla'] = (int) $tablaNum;
}

$where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

$sql = "SELECT id, creado_en, usuario, accion, fecha, tabla_num, hoyo, valores_anteriores, valores_nuevos
        FROM historial_cambios
        $where
        ORDER BY creado_en DESC
        LIMIT :limite";

$stmt = $pdo->prepare($sql);
foreach ($parametros as $clave => $valor) {
    $stmt->bindValue($clave, $valor);
}
$stmt->bindValue('limite', $limite, PDO::PARAM_INT);
$stmt->execute();
$filas = $stmt->fetchAll();

// valores_anteriores/valores_nuevos vienen como texto JSON desde jsonb; se
// decodifican aquí para que el frontend reciba objetos ya listos.
foreach ($filas as &$fila) {
    $fila['valores_anteriores'] = $fila['valores_anteriores'] !== null ? json_decode($fila['valores_anteriores'], true) : null;
    $fila['valores_nuevos']     = $fila['valores_nuevos'] !== null ? json_decode($fila['valores_nuevos'], true) : null;
}

responder(['status' => 'success', 'data' => $filas]);