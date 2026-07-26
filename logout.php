<?php
require_once __DIR__ . '/includes/functions.php';
log_activity('logout', 'Déconnexion');
$_SESSION = [];
session_destroy();
redirect('login.php');
