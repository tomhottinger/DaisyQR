<?php
/**
 * API: Neue Codes generieren
 */

header('Content-Type: application/json');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$count = min(100, max(1, (int) ($input['count'] ?? 1)));

$user = getCurrentUser();
$codes = [];

for ($i = 0; $i < $count; $i++) {
    $id = generateUniqueCodeId();
    createCode($id, $user['id'] ?? null);
    $codes[] = [
        'id' => $id,
        'url' => BASE_URL . '/' . $id
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($codes),
    'codes' => $codes
]);
