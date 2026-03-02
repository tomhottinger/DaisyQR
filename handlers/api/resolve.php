<?php
/**
 * API: Entscheidungslogik fuer gescannte Codes
 */

header('Content-Type: application/json');

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$codeId = $routeParams[0] ?? '';
$user = getCurrentUser();
$decision = resolveCodeAction($codeId, $user);
$action = $decision['action'];
$code = $decision['code'] ?? null;

$response = [
    'action' => $action,
    'code_id' => $codeId,
];

if ($action === 'OPEN_CODE') {
    incrementScanCount($codeId);
    $response['code_url'] = $code['target_url'];
    echo json_encode($response);
    exit;
}

if ($action === 'REDIRECT_TO_PROGRAMMING') {
    $response['programming_url'] = BASE_PATH . '/' . $codeId;
    echo json_encode($response);
    exit;
}

if ($action === 'PROMPT_LOGIN') {
    $response['login_url'] = BASE_PATH . '/login?next=' . urlencode(BASE_PATH . '/' . $codeId);
    $response['message'] = 'Bitte einloggen.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if ($action === 'ERROR_CODE_NOT_FOUND') {
    $response['message'] = 'Code ist unbekannt.';
    http_response_code(404);
    echo json_encode($response);
    exit;
}

if ($action === 'ERROR_NOT_PROGRAMMED') {
    $response['message'] = 'Code ist nicht programmiert.';
    http_response_code(409);
    echo json_encode($response);
    exit;
}

if ($action === 'ERROR_NOT_OWNER') {
    $response['message'] = 'Keine Berechtigung fuer diesen Code.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

http_response_code(500);
echo json_encode([
    'action' => 'ERROR_INTERNAL',
    'message' => 'Interner Fehler.'
]);
