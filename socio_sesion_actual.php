<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';

$socio = usuarioSesionSocio();
if (!$socio) {
    error('No hay sesión activa.', 401);
}

responder(['status' => 'success', 'socio' => $socio]);
