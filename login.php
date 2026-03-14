<?php
// ─── login.php ─────────────────────────────────────────────────────────────
// POST /login.php
// Body: { "email": "...", "password": "..." }
// Returns: { success, message, data: { user_id, name, email, role } }
// ───────────────────────────────────────────────────────────────────────────

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method allowed', null, 405);
}

$body     = getRequestBody();
$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

if (empty($email) || empty($password)) {
    sendResponse(false, 'Email and password are required', null, 400);
}

// ─── Hardcoded users (no DB needed) ────────────────────────────────────────
// Format: email => [ password, user_id, name, role ]
$hardcodedUsers = [
    'monitor@smartbin.com' => [
        'password' => 'monitor123',
        'user_id'  => 1,
        'name'     => 'Admin Monitor',
        'role'     => 'monitor',
    ],
    'device1@smartbin.com' => [
        'password' => 'device123',
        'user_id'  => 2,
        'name'     => 'Device Phone 1',
        'role'     => 'device',
        'device_id'   => 1,
        'device_name' => 'Main Gate Bin',
    ],
    'device2@smartbin.com' => [
        'password' => 'device456',
        'user_id'  => 3,
        'name'     => 'Device Phone 2',
        'role'     => 'device',
        'device_id'   => 2,
        'device_name' => 'Cafeteria Bin',
    ],
];

// ─── Check credentials ──────────────────────────────────────────────────────
if (!isset($hardcodedUsers[$email])) {
    sendResponse(false, 'Invalid email or password', null, 401);
}

$user = $hardcodedUsers[$email];

if ($password !== $user['password']) {
    sendResponse(false, 'Invalid email or password', null, 401);
}

// ─── Build response data (matches what Flutter AuthProvider expects) ─────────
$responseData = [
    'user_id' => $user['user_id'],
    'name'    => $user['name'],
    'email'   => $email,
    'role'    => $user['role'],
];

// If device role, include device info
if ($user['role'] === 'device') {
    $responseData['device_id']   = $user['device_id'];
    $responseData['device_name'] = $user['device_name'];
}

sendResponse(true, 'Login successful', $responseData);
?>