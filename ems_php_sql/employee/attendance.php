<?php
require_once '../include/db.php';
check_login();
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Handle clock in/out POST actions, adapt to schema
$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && in_array($_POST['action'], ['clock_in','clock_out','mark_absent'])) {
        $action = $_POST['action'];
        $today = date('Y-m-d');

        if ($action === 'clock_in') {
            if ($has_clock) {
                $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, clock_in, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in), status = VALUES(status)');
                $now = date('Y-m-d H:i:s');
                $status = 'present';
                $stmt->bind_param('isss', $user_id, $today, $now, $status);
                $stmt->execute();
                $stmt->close();
            } else {
                // older schema: just set status to present
                $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
                $status = 'present';
                $stmt->bind_param('iss', $user_id, $today, $status);
                $stmt->execute(); $stmt->close();
            }
        } elseif ($action === 'clock_out') {
            if ($has_clock) {
                $now = date('Y-m-d H:i:s');
                $stmt = $conn->prepare('UPDATE attendance SET clock_out = ? WHERE user_id = ? AND date = ?');
                $stmt->bind_param('sis', $now, $user_id, $today);
                $stmt->execute(); $stmt->close();
            }
        } elseif ($action === 'mark_absent') {
            if ($has_clock) {
                $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), clock_in = NULL, clock_out = NULL');
                $status = 'absent';
                $stmt->bind_param('iss', $user_id, $today, $status);
                $stmt->execute(); $stmt->close();
            } else {
                $stmt = $conn->prepare('INSERT INTO attendance (user_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)');
                $status = 'absent';
                $stmt->bind_param('iss', $user_id, $today, $status);
                $stmt->execute(); $stmt->close();
            }
        }
        header('Location: attendance.php'); die();
    }
}

// Fetch recent attendance records for this user (last 30 days), adapt to schema
$records = [];
$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($has_clock) {
    $sth = $conn->prepare('SELECT id, date, clock_in, clock_out, status, notes FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 60');
} else {
    $sth = $conn->prepare('SELECT id, date, status, note as notes FROM attendance WHERE user_id = ? ORDER BY date DESC LIMIT 60');
}
if ($sth) {
    $sth->bind_param('i', $user_id);
    $sth->execute();
    $res = $sth->get_result();
    if ($res) { while ($r = $res->fetch_assoc()) $records[] = $r; }
    $sth->close();
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Attendance</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/user_header.php'; ?>

<div class="page-header"><h1 class="h1">My Attendance</h1></div>

<div class="container">
    <div class="card">
        <h3 class="small-muted">Today</h3>
        <?php
        $today = date('Y-m-d');
        $stmt = $conn->prepare('SELECT clock_in, clock_out, status FROM attendance WHERE user_id = ? AND date = ? LIMIT 1');
        $ci=null;$co=null;$st='present';
        if ($stmt) {
            $stmt->bind_param('is', $user_id, $today);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            if ($r) { $ci = $r['clock_in']; $co = $r['clock_out']; $st = $r['status']; }
            $stmt->close();
        }
        ?>
        <p>Status: <strong><?php echo htmlspecialchars(ucfirst($st)); ?></strong></p>
    <p>Clock In: <strong><?php if ($ci) { $dt = new DateTime($ci, new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else { echo '—'; } ?></strong></p>
    <p>Clock Out: <strong><?php if ($co) { $dt = new DateTime($co, new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else { echo '—'; } ?></strong></p>

        <form method="post">
            <?php if (!$ci): ?>
                <button class="btn" name="action" value="clock_in">Clock In</button>
            <?php endif; ?>
            <?php if ($ci && !$co): ?>
                <button class="btn outline" name="action" value="clock_out">Clock Out</button>
            <?php endif; ?>
            <?php if (!$ci && !$co): ?>
                <button class="btn ghost" name="action" value="mark_absent" onclick="return confirm('Mark today as absent?')">Mark Absent</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="card recent-table">
        <h3 class="small-muted">Recent Attendance</h3>
        <table class="table">
            <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo htmlspecialchars($r['date']); ?></td>
                    <td><?php if (!empty($r['clock_in'])) { $dt = new DateTime($r['clock_in'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else { echo '—'; } ?></td>
                    <td><?php if (!empty($r['clock_out'])) { $dt = new DateTime($r['clock_out'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else { echo '—'; } ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($r['status'])); ?></td>
                    <td><?php echo htmlspecialchars($r['notes']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>

</body>
</html>