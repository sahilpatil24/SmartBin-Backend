// ─── seed.php ────────────────────────────────────────────────────────────────
// Run this ONCE to create demo users and sample data
// Visit: http://localhost/smartbin/seed.php
// ─────────────────────────────────────────────────────────────────────────────

require_once 'config.php';

$conn = getConnection();
$log = [];

// ── 1. Create Users ──────────────────────────────────────────────────────────
$users = [
    ['Admin Monitor',  'monitor@smartbin.com', 'monitor123',  'monitor'],
    ['Device Phone 1', 'device1@smartbin.com', 'device123',   'device'],
    ['Device Phone 2', 'device2@smartbin.com', 'device456',   'device'],
];

foreach ($users as [$name, $email, $pass, $role]) {
    $hashed = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("ssss", $name, $email, $hashed, $role);
    $stmt->execute();
    $log[] = "User: $email / $pass [$role]" . ($stmt->affected_rows > 0 ? " ✓" : " (already exists)");
}

// ── 2. Create Devices ────────────────────────────────────────────────────────
$devices = [
    ['Main Gate Bin',   'Main Entrance Gate, Block A'],
    ['Cafeteria Bin',   'Ground Floor Cafeteria'],
    ['Library Bin',     'Second Floor Library'],
    ['Parking Lot Bin', 'Parking Zone C'],
];

$deviceIds = [];
foreach ($devices as [$name, $location]) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO devices (device_name, location, status) VALUES (?, ?, 'active')"
    );
    $stmt->bind_param("ss", $name, $location);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $deviceIds[] = ['id' => $conn->insert_id, 'location' => $location];
        $log[] = "Device: $name @ $location ✓";
    }
}

// ── 3. Create Bins ───────────────────────────────────────────────────────────
$binFills = [15, 73, 95, 42]; // mix of statuses
foreach ($deviceIds as $i => $device) {
    $fill = $binFills[$i] ?? 0;
    $status = $fill >= 90 ? 'full' : ($fill >= 70 ? 'almost_full' : 'normal');

    $stmt = $conn->prepare(
        "INSERT IGNORE INTO bins (device_id, location, fill_percentage, status) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isis", $device['id'], $device['location'], $fill, $status);
    $stmt->execute();
    $binId = $conn->insert_id;

    if ($binId > 0) {
        $log[] = "Bin #$binId: {$device['location']} → $fill% ($status) ✓";

        // Add history
        for ($h = 1; $h <= 5; $h++) {
            $hFill = max(0, $fill - ($h * 8));
            $hStmt = $conn->prepare(
                "INSERT INTO bin_history (bin_id, fill_percentage) VALUES (?, ?)"
            );
            $hStmt->bind_param("ii", $binId, $hFill);
            $hStmt->execute();
        }

        // Create alert if full
        if ($status === 'full') {
            $msg = "Bin #$binId at {$device['location']} is FULL ($fill%). Immediate collection required.";
            $type = 'bin_full';
            $aStmt = $conn->prepare(
                "INSERT INTO alerts (bin_id, alert_type, message) VALUES (?, ?, ?)"
            );
            $aStmt->bind_param("iss", $binId, $type, $msg);
            $aStmt->execute();
            $log[] = "  → Alert created for full bin ✓";
        }
    }
}

$conn->close();

echo "<h2>SmartBin Seed Complete</h2><pre>";
echo implode("\n", $log);
echo "\n\n--- LOGIN CREDENTIALS ---\n";
echo "Monitor: monitor@smartbin.com / monitor123\n";
echo "Device1: device1@smartbin.com / device123\n";
echo "Device2: device2@smartbin.com / device456\n";
echo "</pre>";
?>