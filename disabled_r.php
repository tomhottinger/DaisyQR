<?php
require_once __DIR__ . '/db.php';

$codeId = trim((string)($_GET['id'] ?? ''));
if ($codeId === '' || !preg_match('/^[a-f0-9]{12}$/', $codeId)) {
    http_response_code(400);
    echo 'Code-ID fehlt oder ist ungueltig.';
    exit;
}

$target = BASE_PATH . '/' . $codeId;
$query = $_GET;
unset($query['id']);
if (!empty($query)) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . $target, true, 302);
exit;
