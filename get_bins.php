<?php
// ─── get_bins.php ───────────────────────────────────────────────────────────
// GET  /get_bins.php              → all bins
// GET  /get_bins.php?bin_id=1     → single bin with history
// GET  /get_bins.php?status=full  → filtered by status
// NOTE: Hardcoded demo data — no DB required
// ────────────────────────────────────────────────────────────────────────────

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Only GET method allowed', null, 405);
}

// ─── Hardcoded bin data (matches your seed.php locations) ──────────────────
// Fill percentages are read from a shared JSON file so device updates
// are reflected here. Falls back to defaults if file doesn't exist.

$stateFile = __DIR__ . '/bin_state.json';

$defaultBins = [
    1 => ['bin_id' => 1, 'device_id' => 1, 'location' => 'Main Entrance Gate, Block A',  'fill_percentage' => 15, 'status' => 'normal',      'device_name' => 'Main Gate Bin',   'device_status' => 'active'],
    2 => ['bin_id' => 2, 'device_id' => 2, 'location' => 'Ground Floor Cafeteria',        'fill_percentage' => 73, 'status' => 'almost_full', 'device_name' => 'Cafeteria Bin',   'device_status' => 'active'],
    3 => ['bin_id' => 3, 'device_id' => 3, 'location' => 'Second Floor Library',          'fill_percentage' => 95, 'status' => 'full',        'device_name' => 'Library Bin',     'device_status' => 'active'],
    4 => ['bin_id' => 4, 'device_id' => 4, 'location' => 'Parking Zone C',                'fill_percentage' => 42, 'status' => 'normal',      'device_name' => 'Parking Lot Bin', 'device_status' => 'active'],
];

// Load live state if device updates have been written
if (file_exists($stateFile)) {
    $saved = json_decode(file_get_contents($stateFile), true) ?? [];
    foreach ($saved as $id => $data) {
        if (isset($defaultBins[$id])) {
            $defaultBins[$id]['fill_percentage'] = $data['fill_percentage'];
            $defaultBins[$id]['status']          = $data['status'];
            $defaultBins[$id]['last_updated']    = $data['last_updated'] ?? null;
        }
    }
}

$bin_id       = isset($_GET['bin_id']) ? intval($_GET['bin_id']) : null;
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : null;

// ─── Single bin ─────────────────────────────────────────────────────────────
if ($bin_id) {
    if (!isset($defaultBins[$bin_id])) {
        sendResponse(false, 'Bin not found', null, 404);
    }

    $bin = $defaultBins[$bin_id];
    $bin['history'] = []; // No history without DB — empty array is safe
    sendResponse(true, 'Bin fetched', $bin);
}

// ─── All bins ────────────────────────────────────────────────────────────────
$bins = array_values($defaultBins);

// Optional status filter
if ($status_filter && in_array($status_filter, ['normal', 'almost_full', 'full'])) {
    $bins = array_values(array_filter($bins, fn($b) => $b['status'] === $status_filter));
}

// Summary counts
$summary = ['normal' => 0, 'almost_full' => 0, 'full' => 0, 'total' => count($defaultBins)];
foreach ($defaultBins as $b) {
    if (isset($summary[$b['status']])) {
        $summary[$b['status']]++;
    }
}

sendResponse(true, 'Bins fetched', [
    'bins'    => $bins,
    'summary' => $summary,
]);
?>