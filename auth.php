<?php
require_once __DIR__ . '/db.php';

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo 'Ungueltiger Auth-Token';
    exit;
}

$target = BASE_PATH . '/auth/' . rawurlencode($token);
$query = $_GET;
unset($query['token']);
if (!empty($query)) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . $target, true, 302);
exit;
