<?php
/**
 * Share-Target Handler fuer PWA (Android "Teilen")
 */

$base = BASE_PATH;

if ($method !== 'POST') {
    header('Location: ' . $base . '/', true, 302);
    exit;
}

if (!empty($_POST['clear_share'])) {
    unset($_SESSION['pending_share']);
    header('Location: ' . $base . '/', true, 302);
    exit;
}

$sharedUrl = trim((string)($_POST['url'] ?? ''));
$sharedTitle = trim((string)($_POST['title'] ?? ''));
$sharedText = trim((string)($_POST['text'] ?? ''));

if ($sharedUrl === '' && $sharedText !== '') {
    if (preg_match('#https?://[^\s]+#i', $sharedText, $match)) {
        $sharedUrl = $match[0];
    }
}

if (!filter_var($sharedUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ungültige URL</title>
        <link rel="stylesheet" href="<?= $base ?>/assets/style.css">
    </head>
    <body>
        <div class="container">
            <div class="card">
                <h1>Ungültige URL</h1>
                <p>Die geteilte URL konnte nicht verarbeitet werden.</p>
                <a href="<?= $base ?>/" class="btn primary">Zum Scanner</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$_SESSION['pending_share'] = [
    'url' => $sharedUrl,
    'title' => $sharedTitle,
    'text' => $sharedText,
    'created_at' => time(),
];

$target = $base . '/?share=1';
$user = getCurrentUser();
if (!$user) {
    header('Location: ' . $base . '/login?next=' . urlencode($target), true, 302);
    exit;
}

header('Location: ' . $target, true, 302);
exit;
