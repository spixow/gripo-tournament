<?php
/**
 * Configuration globale de la plateforme Tournoi FIFA 1v1
 */

// ---- Paramètres base de données ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'tournoi_fifa');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Paramètres application ----
define('APP_NAME', 'GRIPO TOURNAMENT — FC 26 1v1');
define('APP_DEADLINE', '2026-07-27');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', 'uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 Mo

// Nombre de qualifiés directs (demi-finales) et places play-offs
define('DIRECT_QUALIFIED', 2);

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Fuseau horaire ----
date_default_timezone_set('Africa/Casablanca');

// ---- Affichage des erreurs (mettre à false en production) ----
$DEBUG = true;
if ($DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
