<?php
require_once 'helpers.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// Ver la lista: cualquier operador logueado (les sirve de referencia y para
// el bloque "Socios registrados" dentro del modal, aunque solo el admin
// pueda agregar/eliminar).
if ($metodo === 'GET') {
    requiereSesion();
    $stmt = $pdo->query("SELECT id, primer_nombre, segundo_nombre, apellidos, nombre_completo
                          FROM socios ORDER BY nombre_completo ASC");
    responder(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

// Crear y eliminar socios: solo Administradores.
requiereAdmin();

if ($metodo === 'POST') {
    // Body esperado: { "socios": [ {primer_nombre, segundo_nombre, apellidos}, ... ] }
    $body   = leerJson();
    $socios = $body['socios'] ?? [];

    if (!is_array($socios) || !count($socios)) {
        error('Debes enviar al menos un socio.');
    }

    $usuarioActual = usuarioSesion();
    $insertar = $pdo->prepare(
        "INSERT INTO socios (primer_nombre, segundo_nombre, apellidos, nombre_completo, creado_por)
         VALUES (:pn, :sn, :ap, :nc, :usuario)"
    );

    $insertados = 0;
    $pdo->beginTransaction();
    try {
        foreach ($socios as $s) {
            $primerNombre  = trim((string) ($s['primer_nombre'] ?? ''));
            $segundoNombre = trim((string) ($s['segundo_nombre'] ?? ''));
            $apellidos     = trim((string) ($s['apellidos'] ?? ''));

            // Fila incompleta o totalmente vacía: se ignora en silencio (así
            // se puede dejar filas de sobra sin llenar en la captura masiva).
            if ($primerNombre === '' || $apellidos === '') {
                continue;
            }

            // nombre_completo se arma aquí (ya no es columna GENERATED en la
            // BD), uniendo solo las piezas que sí vienen con texto.
            $piezas = array_filter([$primerNombre, $segundoNombre, $apellidos], fn($p) => $p !== '');
            $nombreCompleto = implode(' ', $piezas);

            $insertar->execute([
                'pn'      => $primerNombre,
                'sn'      => $segundoNombre !== '' ? $segundoNombre : null,
                'ap'      => $apellidos,
                'nc'      => $nombreCompleto,
                'usuario' => $usuarioActual['username'] ?? 'desconocido'
            ]);
            $insertados++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error('Error al guardar socios: ' . $e->getMessage(), 500);
    }

    if ($insertados === 0) {
        error('Ninguna fila tenía nombre y apellido completos.');
    }

    responder(['status' => 'success', 'insertados' => $insertados]);
}

if ($metodo === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        error('Falta el id del socio.');
    }
    $pdo->prepare("DELETE FROM socios WHERE id = :id")->execute(['id' => $id]);
    responder(['status' => 'success']);
}

error('Método no soportado.', 405);