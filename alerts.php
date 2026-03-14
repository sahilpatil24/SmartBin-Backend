<?php
// ─── alerts.php ─────────────────────────────────────────────────────────────
// GET  /alerts.php                      → all unresolved alerts
// GET  /alerts.php?resolved=true        → all alerts including resolved
// GET  /alerts.php?bin_id=1             → alerts for specific bin
// POST /alerts.php { action:"resolve", alert_id: 1 }  → resolve alert
// ────────────────────────────────────────────────────────────────────────────

require_once 'config.php';

$conn = getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $bin_id   = isset($_GET['bin_id'])   ? intval($_GET['bin_id'])   : null;
    $resolved = isset($_GET['resolved']) && $_GET['resolved'] === 'true';

    $sql = "SELECT a.alert_id, a.bin_id, a.alert_type, a.message, a.resolved, a.created_at,
                   b.location AS bin_location, b.fill_percentage, b.status AS bin_status
            FROM alerts a
            JOIN bins b ON a.bin_id = b.bin_id
            WHERE 1=1";

    if (!$resolved) {
        $sql .= " AND a.resolved = FALSE";
    }

    if ($bin_id) {
        $sql .= " AND a.bin_id = " . intval($bin_id);
    }

    $sql .= " ORDER BY a.created_at DESC LIMIT 100";

    $result = $conn->query($sql);
    $alerts = [];

    while ($row = $result->fetch_assoc()) {
        $row['resolved'] = (bool)$row['resolved'];
        $alerts[] = $row;
    }

    // Unresolved count
    $countResult = $conn->query("SELECT COUNT(*) as cnt FROM alerts WHERE resolved = FALSE");
    $countRow = $countResult->fetch_assoc();

    sendResponse(true, 'Alerts fetched', [
        'alerts'           => $alerts,
        'unresolved_count' => intval($countRow['cnt']),
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $body   = getRequestBody();
    $action = $body['action'] ?? '';

    if ($action === 'resolve') {
        $alert_id = intval($body['alert_id'] ?? 0);
        if ($alert_id <= 0) {
            sendResponse(false, 'alert_id is required', null, 400);
        }

        $stmt = $conn->prepare("UPDATE alerts SET resolved = TRUE WHERE alert_id = ?");
        $stmt->bind_param("i", $alert_id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            sendResponse(false, 'Alert not found or already resolved', null, 404);
        }

        sendResponse(true, 'Alert resolved', ['alert_id' => $alert_id]);

    } elseif ($action === 'resolve_all') {
        $bin_id = intval($body['bin_id'] ?? 0);
        if ($bin_id > 0) {
            $stmt = $conn->prepare("UPDATE alerts SET resolved = TRUE WHERE bin_id = ? AND resolved = FALSE");
            $stmt->bind_param("i", $bin_id);
            $stmt->execute();
            sendResponse(true, 'All alerts for bin resolved', ['affected' => $stmt->affected_rows]);
        } else {
            $conn->query("UPDATE alerts SET resolved = TRUE WHERE resolved = FALSE");
            sendResponse(true, 'All alerts resolved');
        }
    } else {
        sendResponse(false, 'Unknown action. Use: resolve, resolve_all', null, 400);
    }

} else {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn->close();
?>