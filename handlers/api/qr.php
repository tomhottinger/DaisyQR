<?php
/**
 * QR-Code Bild generieren
 * Nutzt goQR API als einfache Lösung
 */

// Code ID aus URL
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#/api/qr/([a-f0-9]+)#', $path, $matches);
$codeId = $matches[1] ?? '';

if (empty($codeId)) {
    http_response_code(400);
    exit('Missing code ID');
}

$size = min(500, max(50, (int) ($_GET['size'] ?? 200)));
$url = BASE_URL . '/' . $codeId;

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
