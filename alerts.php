<?php
// ─── alerts.php ─────────────────────────────────────────────────────────────
// GET  /alerts.php                      → all unresolved alerts
// GET  /alerts.php?resolved=true        → all alerts including resolved
// GET  /alerts.php?bin_id=1             → alerts for specific bin
// POST /alerts.php { action:"resolve", alert_id: 1 }  → resolve alert
// POST /alerts.php { action:"resolve_all" }            → resolve all
// NOTE: No DB — uses alerts_state.json for persistence
// ────────────────────────────────────────────────────────────────────────────

require_once 'config.php';

$alertsFile = __DIR__ . '/alerts_state.json';

// ─── Helper: load alerts from file ───────────────────────────────────────────
function loadAlerts($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?? [];
}

// ─── Helper: save alerts to file ─────────────────────────────────────────────
function saveAlerts($file, $alerts) {
    file_put_contents($file, json_encode(array_values($alerts)));
}

// ─── Bin location map (matches get_bins.php hardcoded data) ──────────────────
$binLocations = [
    1 => 'Main Entrance Gate, Block A',
    2 => 'Ground Floor Cafeteria',
    3 => 'Second Floor Library',
    4 => 'Parking Zone C',
];

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $bin_id          = isset($_GET['bin_id']) ? intval($_GET['bin_id']) : null;
    $includeResolved = isset($_GET['resolved']) && $_GET['resolved'] === 'true';

    $alerts = loadAlerts($alertsFile);

    // Also pull current fill % from bin_state.json to enrich alert data
    $stateFile = __DIR__ . '/bin_state.json';
    $binState  = file_exists($stateFile)
        ? (json_decode(file_get_contents($stateFile), true) ?? [])
        : [];

    $filtered = [];
    foreach ($alerts as $a) {
        if (!$includeResolved && $a['resolved']) continue;
        if ($bin_id && $a['bin_id'] !== $bin_id) continue;

        // Enrich with live bin data
        $bid = $a['bin_id'];
        $a['bin_location']   = $binLocations[$bid] ?? 'Unknown';
        $a['fill_percentage'] = $binState[$bid]['fill_percentage'] ?? 0;
        $a['bin_status']      = $binState[$bid]['status'] ?? 'normal';

        $filtered[] = $a;
    }

    // Sort newest first
    usort($filtered, fn($x, $y) => strcmp($y['created_at'], $x['created_at']));
    $filtered = array_slice($filtered, 0, 100);

    $unresolvedCount = count(array_filter($alerts, fn($a) => !$a['resolved']));

    sendResponse(true, 'Alerts fetched', [
        'alerts'           => $filtered,
        'unresolved_count' => $unresolvedCount,
    ]);

// ─── POST ─────────────────────────────────────────────────────────────────────
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body   = getRequestBody();
    $action = $body['action'] ?? '';
    $alerts = loadAlerts($alertsFile);

    if ($action === 'resolve') {
        $alert_id = intval($body['alert_id'] ?? 0);
        if ($alert_id <= 0) {
            sendResponse(false, 'alert_id is required', null, 400);
        }

        $found = false;
        foreach ($alerts as &$a) {
            if ($a['alert_id'] === $alert_id) {
                $a['resolved'] = true;
                $found = true;
                break;
            }
        }

        if (!$found) {
            sendResponse(false, 'Alert not found or already resolved', null, 404);
        }

        saveAlerts($alertsFile, $alerts);
        sendResponse(true, 'Alert resolved', ['alert_id' => $alert_id]);

    } elseif ($action === 'resolve_all') {
        $bin_id = intval($body['bin_id'] ?? 0);

        foreach ($alerts as &$a) {
            if ($bin_id > 0 && $a['bin_id'] !== $bin_id) continue;
            $a['resolved'] = true;
        }

        saveAlerts($alertsFile, $alerts);
        sendResponse(true, 'All alerts resolved');

    // ─── Internal action: add alert (called by update_bin_status.php) ─────────
    } elseif ($action === 'add') {
        $bin_id    = intval($body['bin_id']    ?? 0);
        $type      = $body['alert_type']       ?? 'bin_full';
        $message   = $body['message']          ?? "Bin #$bin_id triggered an alert.";

        if ($bin_id <= 0) {
            sendResponse(false, 'bin_id is required', null, 400);
        }

        // Avoid duplicate unresolved alerts of the same type for the same bin
        foreach ($alerts as $a) {
            if ($a['bin_id'] === $bin_id && $a['alert_type'] === $type && !$a['resolved']) {
                sendResponse(true, 'Alert already exists', ['alert_id' => $a['alert_id']]);
            }
        }

        $newAlert = [
            'alert_id'   => count($alerts) > 0
                ? (max(array_column($alerts, 'alert_id')) + 1)
                : 1,
            'bin_id'     => $bin_id,
            'alert_type' => $type,
            'message'    => $message,
            'resolved'   => false,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $alerts[] = $newAlert;
        saveAlerts($alertsFile, $alerts);
        sendResponse(true, 'Alert created', $newAlert);

    } else {
        sendResponse(false, 'Unknown action. Use: resolve, resolve_all, add', null, 400);
    }

} else {
    sendResponse(false, 'Method not allowed', null, 405);
}
?>