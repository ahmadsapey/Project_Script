<?php
header('Content-Type: application/json; charset=utf-8');

$path = __DIR__ . '/../files.json';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($path)) {
        http_response_code(200);
        echo file_get_contents($path);
        exit;
    }
    http_response_code(200);
    echo json_encode([]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple protection: require ?secret=... query param. Change 'changeme' to a stronger secret.
    $secret = isset($_GET['secret']) ? $_GET['secret'] : '';
    if ($secret !== 'changeme') {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid_json']);
        exit;
    }

    $ok = file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['error' => 'write_failed']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
