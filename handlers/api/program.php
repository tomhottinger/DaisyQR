<?php
/**
 * API: Code programmieren
 */

header('Content-Type: application/json');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$codeId = $input['code_id'] ?? '';
$url = trim($input['target_url'] ?? '');
$title = trim($input['title'] ?? '');
$force = $input['force'] ?? false;

// Validierung
if (empty($codeId)) {
    http_response_code(400);
    echo json_encode(['error' => 'code_id required']);
    exit;
}

if (empty($url)) {
    http_response_code(400);
    echo json_encode(['error' => 'target_url required']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    // Versuche mit https://
    if (filter_var('https://' . $url, FILTER_VALIDATE_URL)) {
        $url = 'https://' . $url;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid URL']);
        exit;
    }
}

// Code prüfen
$code = getCode($codeId);
$wasOverwritten = false;

if (!$code) {
    // Code existiert nicht - erstellen
    createCode($codeId);
} elseif (!empty($code['target_url']) && !$force) {
    // Bereits programmiert und kein Force
    http_response_code(409);
    echo json_encode([
        'error' => 'Code already programmed',
        'current_url' => $code['target_url'],
        'requires_force' => true
    ]);
    exit;
} else {
    $wasOverwritten = !empty($code['target_url']);
}

// Programmieren
$user = getCurrentUser();
if (programCode($codeId, $url, $title ?: null, $user['id'] ?? null)) {
    echo json_encode([
        'success' => true,
        'was_overwritten' => $wasOverwritten,
        'code' => [
            'id' => $codeId,
            'target_url' => $url,
            'title' => $title,
            'url' => BASE_URL . '/' . $codeId
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to program code']);
}
