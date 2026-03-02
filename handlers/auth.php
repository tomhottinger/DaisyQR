<?php
/**
 * QR-Code Authentisierung
 * URL: /auth/{token}
 */

$authToken = $routeParams[0] ?? '';

if (empty($authToken)) {
    http_response_code(400);
    echo 'Ungültiger Auth-Token';
    exit;
}

// Benutzer mit diesem Token finden
$user = getUserByAuthToken($authToken);

if (!$user) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login fehlgeschlagen</title>
        <link rel="stylesheet" href="/assets/style.css">
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>Login fehlgeschlagen</h1>
                <p>Dieser Auth-Code ist ungültig oder wurde deaktiviert.</p>
                <a href="/admin/" class="btn">Zur Startseite</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Session erstellen
$sessionToken = createSession($user['id']);

// Cookie setzen (30 Tage)
setcookie('session_token', $sessionToken, [
    'expires' => time() + SESSION_LIFETIME,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Weiterleitung zur Code-Verwaltung
header('Location: /admin/codes');
exit;
