<?php
require_once 'helpers.php';
requiereSesion(); // Cualquier operador logueado puede usar el autocompletado al capturar

$q = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    responder(['status' => 'success', 'data' => []]);
}

// Combina coincidencia de subcadena (cuando escriben desde el inicio del
// nombre) con similitud por trigramas de pg_trgm (para tolerar que empiecen
// por el apellido o cometan una errata), priorizando primero las
// coincidencias exactas de subcadena y luego por score de similitud.
$stmt = $pdo->prepare(
    "SELECT id, nombre_completo, similarity(nombre_completo, :score_q) AS score
     FROM socios
     WHERE nombre_completo ILIKE :like OR similarity(nombre_completo, :sim_q) > 0.15
     ORDER BY (nombre_completo ILIKE :orden_like) DESC, score DESC
     LIMIT 8"
);
$like = '%' . $q . '%';
$stmt->execute([
    'score_q'    => $q,
    'like'       => $like,
    'sim_q'      => $q,
    'orden_like' => $like
]);

responder(['status' => 'success', 'data' => $stmt->fetchAll()]);