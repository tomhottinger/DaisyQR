<?php
/**
 * API: Codes auflisten
 */

header('Content-Type: application/json');

$user = getCurrentUser();

switch ($method) {
    case 'GET':
        // Alle Codes des Benutzers (oder alle, wenn kein User)
        $codes = getUserCodes($user['id'] ?? null);

        echo json_encode([
            'success' => true,
            'codes' => array_map(function($code) {
                return [
                    'id' => $code['id'],
                    'target_url' => $code['target_url'],
                    'title' => $code['title'],
                    'scan_count' => (int) $code['scan_count'],
                    'created_at' => $code['created_at'],
                    'updated_at' => $code['updated_at'],
                    'url' => BASE_URL . '/' . $code['id']
                ];
            }, $codes)
        ]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
