<?php
/**
 * API: Scan-Pruefung fuer QR-Codes
 */

header('Content-Type: application/json');
requireLogin();

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$codeId = $routeParams[0] ?? '';
$user = getCurrentUser();
$code = getCode($codeId);

if (!$code) {
    http_response_code(404);
    echo json_encode([
        'error' => 'unknown_code',
        'message' => 'Code ist unbekannt.'
    ]);
    exit;
}

if (empty($code['target_url'])) {
    http_response_code(409);
    echo json_encode([
        'error' => 'not_activated',
        'message' => 'Code ist nicht freigeschaltet.'
    ]);
    exit;
}

if (!canOpenCode($code, $user)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'forbidden',
        'message' => 'Keine Berechtigung fuer diesen Code.'
    ]);
    exit;
}

incrementScanCount($codeId);

echo json_encode([
    'success' => true,
    'code' => [
        'id' => $code['id'],
        'title' => $code['title'],
        'target_url' => $code['target_url']
    ]
]);
