<?php
/**
 * API: Shared URL auf gescannten Code anwenden
 */

header('Content-Type: application/json');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pending = $_SESSION['pending_share'] ?? null;
if (!$pending || empty($pending['url'])) {
    http_response_code(409);
    echo json_encode([
        'action' => 'ERROR_NO_SHARED_URL',
        'message' => 'Keine geteilte URL vorhanden.'
    ]);
    exit;
}

$sharedUrl = (string)$pending['url'];
if (!filter_var($sharedUrl, FILTER_VALIDATE_URL)) {
    unset($_SESSION['pending_share']);
    http_response_code(400);
    echo json_encode([
        'action' => 'ERROR_INVALID_SHARED_URL',
        'message' => 'Geteilte URL ist ungueltig.'
    ]);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'action' => 'PROMPT_LOGIN',
        'message' => 'Bitte zuerst einloggen.',
        'login_url' => BASE_PATH . '/login?next=' . urlencode(BASE_PATH . '/?share=1')
    ]);
    exit;
}

$codeId = $routeParams[0] ?? '';
$code = getCode($codeId);
if (!$code) {
    http_response_code(404);
    echo json_encode([
        'action' => 'ERROR_CODE_NOT_FOUND',
        'message' => 'Code ist unbekannt.'
    ]);
    exit;
}

$ownerId = isset($code['user_id']) ? (int)$code['user_id'] : 0;
$currentUserId = (int)$user['id'];
if ($ownerId !== $currentUserId) {
    http_response_code(403);
    echo json_encode([
        'action' => 'ERROR_NOT_OWNER',
        'message' => 'Dieser Code gehoert dir nicht.'
    ]);
    exit;
}

$isProgrammed = !empty($code['target_url']);
if (!$isProgrammed) {
    $sharedTitle = trim((string)($pending['title'] ?? ''));
    if (programCode($codeId, $sharedUrl, $sharedTitle ?: null, $currentUserId)) {
        unset($_SESSION['pending_share']);
        echo json_encode([
            'action' => 'SHARE_PROGRAMMED',
            'message' => 'Geteilte URL wurde auf den Code programmiert.',
            'code_id' => $codeId,
            'code_url' => $sharedUrl
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'action' => 'ERROR_PROGRAM_FAILED',
        'message' => 'Code konnte nicht programmiert werden.'
    ]);
    exit;
}

echo json_encode([
    'action' => 'SHARE_CONFIRM_OVERWRITE',
    'message' => 'Code ist bereits programmiert. Ueberschreiben?',
    'code_id' => $codeId,
    'current_url' => $code['target_url'],
    'new_url' => $sharedUrl,
]);
