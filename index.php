<?php
/**
 * Hauptrouter für QR-Code Webapp
 */

require_once __DIR__ . '/db.php';

// Request-Informationen
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Basis-Pfad entfernen falls vorhanden
$basePath = '';
$path = substr($path, strlen($basePath)) ?: '/';

// Routing
$routes = [
    // Root Scanner
    '#^/$#' => 'handlers/home-scan.php',

    // Admin-Bereich
    '#^/admin/?$#' => 'handlers/admin/codes.php',
    '#^/admin/codes/?$#' => 'handlers/admin/codes.php',
    '#^/admin/generate/?$#' => 'handlers/admin/generate.php',
    '#^/admin/print/?$#' => 'handlers/admin/print.php',
    '#^/admin/scan/?$#' => 'handlers/admin/scan.php',
    '#^/admin/users/?$#' => 'handlers/admin/users.php',

    // API-Endpunkte
    '#^/api/codes/?$#' => 'handlers/api/codes.php',
    '#^/api/codes/([a-f0-9]+)/?$#' => 'handlers/api/code-single.php',
    '#^/api/program/?$#' => 'handlers/api/program.php',
    '#^/api/generate/?$#' => 'handlers/api/generate.php',
    '#^/api/resolve/([a-f0-9]{12})/?$#' => 'handlers/api/resolve.php',
    '#^/api/share/scan/([a-f0-9]{12})/?$#' => 'handlers/api/share-scan.php',
    '#^/api/share/overwrite/([a-f0-9]{12})/?$#' => 'handlers/api/share-overwrite.php',
    '#^/api/scan/([a-f0-9]{12})/?$#' => 'handlers/api/scan.php',
    '#^/api/qr/([a-f0-9]+)/?$#' => 'handlers/api/qr.php',
    '#^/api/qr-auth/([a-f0-9]+)/?$#' => 'handlers/api/qr-auth.php',

    // Auth
    '#^/auth/([a-f0-9]+)/?$#' => 'handlers/auth.php',
    '#^/login/?$#' => 'handlers/login.php',
    '#^/logout/?$#' => 'handlers/logout.php',
    '#^/share/?$#' => 'handlers/share.php',

    // Assets
    '#^/assets/(.+)$#' => 'assets/$1',

    // QR-Code Redirect (muss zuletzt sein - Catch-All für IDs)
    '#^/([a-f0-9]{12})/?$#' => 'handlers/redirect.php',
];

// Route finden und ausführen
foreach ($routes as $pattern => $handler) {
    if (preg_match($pattern, $path, $matches)) {
        // Parameter für den Handler verfügbar machen
        $routeParams = array_slice($matches, 1);

        // Asset-Dateien direkt ausliefern
        if (strpos($handler, 'assets/') === 0) {
            $assetPath = __DIR__ . '/' . $handler;
            if (file_exists($assetPath)) {
                $ext = pathinfo($assetPath, PATHINFO_EXTENSION);
                $mimeTypes = [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'svg' => 'image/svg+xml',
                ];
                header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
                readfile($assetPath);
                exit;
            }
        }

        // Handler laden
        $handlerPath = __DIR__ . '/' . $handler;
        if (file_exists($handlerPath)) {
            require $handlerPath;
            exit;
        }
    }
}

// 404 - Keine Route gefunden
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Nicht gefunden</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card error">
            <h1>404</h1>
            <p>Diese Seite existiert nicht.</p>
            <a href="/admin/" class="btn">Zur Verwaltung</a>
        </div>
    </div>
</body>
</html>
