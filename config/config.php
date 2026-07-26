<?php
/**
 * Configuration globale de la plateforme GRIPO TOURNAMENT
 *
 * La base de données est configurable via variables d'environnement
 * (compatible Railway / conteneurs), avec repli sur les valeurs locales.
 */

/**
 * Récupère une variable d'environnement (getenv ou $_ENV/$_SERVER).
 */
function env_val(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    if ($v === false || $v === '') {
        $v = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($v === null || $v === '') ? $default : $v;
}

// ---- Paramètres base de données ----
// Priorité : DATABASE_URL / MYSQL_URL (URL complète) > variables MYSQL*/DB_* > valeurs locales
$dbUrl = env_val('DATABASE_URL') ?: env_val('MYSQL_URL');
if ($dbUrl && ($p = parse_url($dbUrl)) && !empty($p['host'])) {
    define('DB_HOST', $p['host']);
    define('DB_PORT', (string)($p['port'] ?? 3306));
    define('DB_USER', urldecode($p['user'] ?? 'root'));
    define('DB_PASS', urldecode($p['pass'] ?? ''));
    define('DB_NAME', ltrim($p['path'] ?? '/railway', '/') ?: 'railway');
} else {
    define('DB_HOST', env_val('MYSQLHOST', env_val('DB_HOST', '127.0.0.1')));
    define('DB_PORT', env_val('MYSQLPORT', env_val('DB_PORT', '3306')));
    define('DB_NAME', env_val('MYSQLDATABASE', env_val('DB_NAME', 'tournoi_fifa')));
    define('DB_USER', env_val('MYSQLUSER', env_val('DB_USER', 'root')));
    define('DB_PASS', env_val('MYSQLPASSWORD', env_val('DB_PASS', '')));
}
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
