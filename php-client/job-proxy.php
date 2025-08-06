<?php
/**
 * Job Creation Proxy
 * ジョブ作成リクエストをGoサーバーに転送
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action']) || $input['action'] !== 'create') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

$serverUrl = $input['serverUrl'] ?? 'http://localhost:8080';
$ids = $input['ids'] ?? [];

if (empty($ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'IDs cannot be empty']);
    exit;
}

// Goサーバーにリクエスト転送
$url = rtrim($serverUrl, '/') . '/api/job';
$data = json_encode(['ids' => $ids]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ],
        'content' => $data,
        'timeout' => 10
    ]
]);

$response = @file_get_contents($url, false, $context);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create job on server']);
    exit;
}

// レスポンスをそのまま返す
echo $response;