<?php
/**
 * Konfiguration für QR-Code Webapp
 */

/**
 * Absoluter URL-Pfad zur App-Basis (z.B. "", "/d6")
 */
function basePath(): string {
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if ($script === '') {
        $base = '';
        return $base;
    }

    if (preg_match('#^(.*)/(?:admin|api|handlers)/[^/]+\.php$#', $script, $m)) {
        $base = $m[1];
    } elseif (preg_match('#^(.*)/(?:index|login|logout|auth|r)\.php$#', $script, $m)) {
        $base = $m[1];
    } else {
        $base = rtrim(dirname($script), '/');
    }

    if ($base === '/' || $base === '.') {
        $base = '';
    }

    return $base;
}

/**
 * Absolute URL zur App-Basis (für QR-Codes die extern gescannt werden)
 */
function absUrl(): string {
    static $url = null;
    if ($url === null) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url = $scheme . '://' . $host . basePath();
    }
    return $url;
}

// Konstanten für Kompatibilität
define('BASE_PATH', basePath());
define('BASE_URL', absUrl());
define('DB_PATH', __DIR__ . '/data/qrcodes.db');
define('ID_LENGTH', 12);
define('SESSION_LIFETIME', 86400 * 30); // 30 Tage

// Sicherstellen dass data-Verzeichnis existiert
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0755, true);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('Europe/Zurich');
