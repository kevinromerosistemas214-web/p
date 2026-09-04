<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';

// Endpoint público (sin sesión): se usa SOLO en la pantalla de registro,
// para que el socio encuentre su propio nombre en la lista que el staff ya
// capturó. Por eso NO se expone email/teléfono aquí, solo el nombre — y
// solo de socios que todavía no tienen cuenta creada (username IS NULL).
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    responder(['status' => 'success', 'data' => []]);
}

$stmt = $pdo->prepare(
    "SELECT id, nombre_completo
     FROM socios
     WHERE username IS NULL
       AND nombre_completo ILIKE :q
     ORDER BY nombre_completo ASC
     LIMIT 10"
);
$stmt->execute(['q' => '%' . $q . '%']);

responder(['status' => 'success', 'data' => $stmt->fetchAll()]);
