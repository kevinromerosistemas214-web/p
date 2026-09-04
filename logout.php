<?php
require_once 'helpers.php';
$_SESSION = [];
session_destroy();
responder(['status' => 'success']);
