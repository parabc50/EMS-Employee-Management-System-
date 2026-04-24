<?php
// Usage: php mark_absent.php [YYYY-MM-DD]
require_once __DIR__ . '/../include/db.php';

$date = $argv[1] ?? date('Y-m-d');

// select users who don't have an attendance record for the given date
$sql = "SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM attendance WHERE date = ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $conn->error . "\n"; exit(1);
}
$stmt->bind_param('s', $date);
$stmt->execute();
$res = $stmt->get_result();
$ids = [];
while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
$stmt->close();

if (count($ids) === 0) {
    echo "No users to mark absent for $date\n";
    exit(0);
}

$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($has_clock) {
    $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), clock_in = NULL, clock_out = NULL');
} else {
    $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
}
foreach ($ids as $uid) {
    $status = 'absent';
    $stmt->bind_param('iss', $uid, $date, $status);
    $stmt->execute();
}
$stmt->close();

echo "Marked " . count($ids) . " users absent for $date\n";
