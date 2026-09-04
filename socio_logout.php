<?php
require_once 'helpers.php';
require_once 'helpers_socios.php';

unset($_SESSION['socio']);
responder(['status' => 'success']);
