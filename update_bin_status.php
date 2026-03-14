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
// ⚠ TESTING THRESHOLDS — alert fires at 50%
// TODO: restore to 90/70 for production
if ($fill_percentage >= 70) {
    $status = 'full';
} elseif ($fill_percentage >= 50) {
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

// ─── Alert logic ──────────────────────────────────────────────────────────────
// ⚠ TESTING — alert triggers at 50% (almost_full transition from normal)
// TODO: restore to checking 'full' for production alerts
$alert_created = false;

function writeAlert($bin_id, $type, $message, $alertsFile) {
    $alerts = file_exists($alertsFile)
        ? (json_decode(file_get_contents($alertsFile), true) ?? [])
        : [];

    // Avoid duplicate unresolved alert of same type for same bin
    foreach ($alerts as $a) {
        if ($a['bin_id'] === $bin_id && $a['alert_type'] === $type && !$a['resolved']) {
            return false;
        }
    }

    $newId = count($alerts) > 0 ? (max(array_column($alerts, 'alert_id')) + 1) : 1;
    $alerts[] = [
        'alert_id'   => $newId,
        'bin_id'     => $bin_id,
        'alert_type' => $type,
        'message'    => $message,
        'resolved'   => false,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    file_put_contents($alertsFile, json_encode(array_values($alerts)));
    return true;
}

$alertsFile = __DIR__ . '/alerts_state.json';

if ($status === 'full' && $prevStatus !== 'full') {
    $msg = "Bin #$bin_id at device #$device_id is FULL ($fill_percentage%). Immediate collection required.";
    $alert_created = writeAlert($bin_id, 'bin_full', $msg, $alertsFile);
}
if ($status === 'almost_full' && $prevStatus === 'normal') {
    $msg = "Bin #$bin_id at device #$device_id has reached $fill_percentage%. Schedule collection soon.";
    $alert_created = writeAlert($bin_id, 'bin_almost_full', $msg, $alertsFile);
}

// Auto-resolve alerts when bin goes back to normal
if ($status === 'normal' && ($prevStatus === 'full' || $prevStatus === 'almost_full')) {
    $alerts = file_exists($alertsFile)
        ? (json_decode(file_get_contents($alertsFile), true) ?? [])
        : [];
    foreach ($alerts as &$a) {
        if ($a['bin_id'] === $bin_id && !$a['resolved']) {
            $a['resolved'] = true;
        }
    }
    file_put_contents($alertsFile, json_encode(array_values($alerts)));
}

sendResponse(true, 'Bin status updated', [
    'bin_id'          => $bin_id,
    'device_id'       => $device_id,
    'fill_percentage' => $fill_percentage,
    'status'          => $status,
    'alert_created'   => $alert_created,
]);
?>