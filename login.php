<?php
require_once __DIR__ . '/db.php';
$target = BASE_PATH . '/login';
$query = $_SERVER['QUERY_STRING'] ?? '';
if ($query !== '') {
    $target .= '?' . $query;
}
header('Location: ' . $target, true, 307);
exit;
