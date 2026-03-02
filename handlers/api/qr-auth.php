<?php
/**
 * QR-Code für Auth-Token generieren
 */

// Token aus URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#/api/qr-auth/([a-f0-9]+)#', $path, $matches);
$authToken = $matches[1] ?? '';

if (empty($authToken)) {
    http_response_code(400);
    exit('Missing auth token');
}

$size = min(500, max(50, (int) ($_GET['size'] ?? 200)));
$url = BASE_URL . '/auth/' . $authToken;

// QR-Code via goQR API (ohne Google-Abhängigkeit)
$chartUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
    'size' => $size . 'x' . $size,
    'data' => $url,
    'format' => 'png',
    'ecc' => 'M',
    'margin' => 2
]);

header('Location: ' . $chartUrl, true, 302);
header('Cache-Control: no-store');
exit;
