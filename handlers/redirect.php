<?php
/**
 * QR-Code Redirect Handler
 * Wird aufgerufen wenn ein QR-Code gescannt wird
 */

$codeId = $routeParams[0] ?? '';
$user = getCurrentUser();
$editMode = isset($_GET['edit']) && $_GET['edit'] !== '0';

if ($editMode) {
    $base = BASE_PATH;
    $code = getCode($codeId);

    if (!$code) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Code nicht gefunden</title>
            <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h1>Code nicht gefunden</h1>
                    <p>Dieser QR-Code ist im System nicht bekannt.</p>
                    <a href="<?= $base ?>/admin/codes" class="btn primary">Zurueck zu Codes</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    if (!$user) {
        $next = $base . '/' . $codeId . '?edit=1';
        header('Location: ' . $base . '/login?next=' . urlencode($next), true, 302);
        exit;
    }

    if (!canAccessCode($code, $user)) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Keine Berechtigung</title>
            <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
        </head>
        <body>
            <div class="container">
                <div class="card">
                    <h1>Keine Berechtigung</h1>
                    <p>Du darfst diesen Code nicht bearbeiten.</p>
                    <a href="<?= $base ?>/admin/codes" class="btn primary">Zurueck zu Codes</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    $routeParams = [$codeId];
    require __DIR__ . '/program-form.php';
    exit;
}

$decision = resolveCodeAction($codeId, $user);
$action = $decision['action'];
$code = $decision['code'] ?? null;
$base = BASE_PATH;

if ($action === 'OPEN_CODE') {
    incrementScanCount($codeId);
    header('Location: ' . $code['target_url'], true, 302);
    exit;
}

if ($action === 'REDIRECT_TO_PROGRAMMING') {
    // Formular nur fuer Owner eines privaten, nicht programmierten Codes
    require __DIR__ . '/program-form.php';
    exit;
}

if ($action === 'PROMPT_LOGIN') {
    $next = $base . '/' . $codeId;
    header('Location: ' . $base . '/login?next=' . urlencode($next), true, 302);
    exit;
}

$status = 400;
$title = 'Fehler';
$message = 'Unbekannter Fehler.';

if ($action === 'ERROR_CODE_NOT_FOUND') {
    $status = 404;
    $title = 'Code nicht gefunden';
    $message = 'Dieser QR-Code ist im System nicht bekannt.';
} elseif ($action === 'ERROR_NOT_PROGRAMMED') {
    $status = 409;
    $title = 'Code nicht freigeschaltet';
    $message = 'Dieser QR-Code ist noch nicht programmiert.';
} elseif ($action === 'ERROR_NOT_OWNER') {
    $status = 403;
    $title = 'Keine Berechtigung';
    $message = 'Du bist nicht der Besitzer dieses QR-Codes.';
}

http_response_code($status);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <h1><?= htmlspecialchars($title) ?></h1>
            <p><?= htmlspecialchars($message) ?></p>
            <div class="actions">
                <a href="<?= $base ?>/" class="btn primary">Scanner starten</a>
                <a href="<?= $base ?>/login" class="btn">Login</a>
            </div>
        </div>
    </div>
</body>
</html>
