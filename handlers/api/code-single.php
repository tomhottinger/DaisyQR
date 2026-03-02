<?php
/**
 * API: Einzelner Code - CRUD
 */

header('Content-Type: application/json');

$codeId = $routeParams[0] ?? '';
$user = getCurrentUser();

switch ($method) {
    case 'GET':
        $code = getCode($codeId);
        if (!$code) {
            http_response_code(404);
            echo json_encode(['error' => 'Code not found']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'code' => [
                'id' => $code['id'],
                'target_url' => $code['target_url'],
                'title' => $code['title'],
                'scan_count' => (int) $code['scan_count'],
                'created_at' => $code['created_at'],
                'updated_at' => $code['updated_at'],
                'url' => BASE_URL . '/' . $code['id']
            ]
        ]);
        break;

    case 'PUT':
    case 'PATCH':
        $input = json_decode(file_get_contents('php://input'), true);
        $code = getCode($codeId);

        if (!$code) {
            http_response_code(404);
            echo json_encode(['error' => 'Code not found']);
            exit;
        }

        $url = $input['target_url'] ?? $code['target_url'];
        $title = $input['title'] ?? $code['title'];

        if (programCode($codeId, $url, $title, $user['id'] ?? null)) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update']);
        }
        break;

    case 'DELETE':
        $code = getCode($codeId);
        if (!$code) {
            http_response_code(404);
            echo json_encode(['error' => 'Code not found']);
            exit;
        }

        // Nur Programmierung löschen oder komplett?
        $input = json_decode(file_get_contents('php://input'), true);
        $deleteCompletely = $input['complete'] ?? false;

        if ($deleteCompletely) {
            deleteCode($codeId);
        } else {
            unprogramCode($codeId);
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
