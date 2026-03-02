<?php
/**
 * QR-Code Bild generieren
 * URL: /api/qr.php?id=xxxxx&size=200
 * Für Auth: /api/qr.php?id=auth_TOKEN&size=200
 */

require_once __DIR__ . '/../db.php';

$codeId = $_GET['id'] ?? '';

if (empty($codeId)) {
    http_response_code(400);
    exit('Missing code ID');
}

$size = min(500, max(50, (int) ($_GET['size'] ?? 200)));

// Prüfen ob es ein Auth-Token ist
if (strpos($codeId, 'auth_') === 0) {
    $authToken = substr($codeId, 5);
    $url = BASE_URL . '/auth/' . $authToken;
} else {
    $url = BASE_URL . '/' . $codeId;
}

// QR-Code via goQR API (ohne Google-Abhängigkeit)
$chartUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
    'size' => $size . 'x' . $size,
    'data' => $url,
    'format' => 'png',
    'ecc' => 'M',
    'margin' => 2
]);

// Direkt weiterleiten: funktioniert auch wenn der Webserver keine Outbound-Requests darf.
header('Location: ' . $chartUrl, true, 302);
header('Cache-Control: no-store');
exit;
