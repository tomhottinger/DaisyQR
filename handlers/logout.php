<?php
/**
 * Logout
 */

// Session-Cookie löschen
setcookie('session_token', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Zur Startseite
header('Location: /admin/');
exit;
