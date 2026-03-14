<?php
// ─── update_bin_status.php ──────────────────────────────────────────────────
// POST /update_bin_status.php
// Body: { "device_id": 1, "fill_percentage": 75 }
// Returns: { success, message, data: { bin_id, status, fill_percentage, alert_created } }
// NOTE: No DB — persists state to bin_state.json so get_bins.php stays in sync
// ────────────────────────────────────────────────────────────────────────────

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST method allowed', null, 405);
}

$body            = getRequestBody();
$device_id       = intval($body['device_id']       ?? 0);
$fill_percentage = intval($body['fill_percentage'] ?? 0);

if ($device_id <= 0) {
    sendResponse(false, 'device_id is required', null, 400);
}

if ($fill_percentage < 0 || $fill_percentage > 100) {
    sendResponse(false, 'fill_percentage must be between 0 and 100', null, 400);
}

// ─── Device ID → Bin ID map (matches hardcoded data in get_bins.php) ────────
$deviceToBin = [
    1 => 1, // Main Gate Bin
    2 => 2, // Cafeteria Bin
    3 => 3, // Library Bin
    4 => 4, // Parking Lot Bin
];

if (!isset($deviceToBin[$device_id])) {
    sendResponse(false, 'Unknown device_id', null, 404);
}

$bin_id = $deviceToBin[$device_id];

// ─── Determine status ────────────────────────────────────────────────────────
if ($fill_percentage >= 90) {
    $status = 'full';
} elseif ($fill_percentage >= 70) {
    $status = 'almost_full';
} else {
    $status = 'normal';
}

// ─── Persist to bin_state.json so get_bins.php reflects live data ───────────
$stateFile = __DIR__ . '/bin_state.json';
$state = file_exists($stateFile)
    ? (json_decode(file_get_contents($stateFile), true) ?? [])
    : [];

$prevStatus = $state[$bin_id]['status'] ?? 'normal';

$state[$bin_id] = [
    'fill_percentage' => $fill_percentage,
    'status'          => $status,
    'last_updated'    => date('Y-m-d H:i:s'),
];

file_put_contents($stateFile, json_encode($state));

// ─── Alert logic (same thresholds as original) ───────────────────────────────
$alert_created = false;

if ($status === 'full' && $prevStatus !== 'full') {
    $alert_created = true; // Monitor will see this via alerts.php
}
if ($status === 'almost_full' && $prevStatus === 'normal') {
    $alert_created = true;
}

sendResponse(true, 'Bin status updated', [
    'bin_id'          => $bin_id,
    'device_id'       => $device_id,
    'fill_percentage' => $fill_percentage,
    'status'          => $status,
    'alert_created'   => $alert_created,
]);
?>