<?php
// ─── Database Configuration ────────────────────────────────────────────────
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function getConnection() {
    $connection = new mysqli('localhost', 'root', 'password', 'chairs_login', 4307);

    if ($connection->connect_error) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection failed: ' . $connection->connect_error
        ]);
        exit();
    }

    $connection->set_charset("utf8");
    return $connection;
}

function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    echo json_encode($response);
    exit();
}

function getRequestBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}
?>