<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_hr() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

// Handle form submissions securely
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_leave'])) {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        $status = $conn->real_escape_string($_POST['status'] ?? 'pending');
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $check = $conn->query("SELECT u.department_id FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.id = $leave_id");
            $crow = $check ? $check->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $stmt = $conn->prepare('UPDATE leaves SET status = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $status, $leave_id);
            $stmt->execute();
            $stmt->close();
            // If approved, mark attendance rows for the leave range as on_leave
            if ($status === 'approved') {
                // fetch the leave details
                $lq = $conn->prepare('SELECT user_id, start_date, end_date FROM leaves WHERE id = ? LIMIT 1');
                if ($lq) {
                    $lq->bind_param('i', $leave_id);
                    $lq->execute();
                    $lr = $lq->get_result()->fetch_assoc();
                    $lq->close();
                    if ($lr) {
                        $uid = (int)$lr['user_id'];
                        $sd = $lr['start_date'];
                        $ed = $lr['end_date'];
                        try {
                            $start = new DateTime($sd);
                            $end = new DateTime($ed);
                            $end->modify('+1 day');
                            $interval = new DateInterval('P1D');
                            $period = new DatePeriod($start, $interval, $end);
                            $has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
                            foreach ($period as $d) {
                                $ad = $d->format('Y-m-d');
                                if ($has_clock) {
                                    $ins = $conn->prepare('INSERT INTO attendance (user_id, date, status, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes), clock_in = NULL, clock_out = NULL');
                                    $note = 'On approved leave';
                                    $stat = 'on_leave';
                                    $ins->bind_param('isss', $uid, $ad, $stat, $note);
                                    $ins->execute();
                                    $ins->close();
                                } else {
                                    $ins = $conn->prepare('INSERT INTO attendance (user_id, date, status, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note)');
                                    $note = 'On approved leave';
                                    $stat = 'on_leave';
                                    $ins->bind_param('isss', $uid, $ad, $stat, $note);
                                    $ins->execute();
                                    $ins->close();
                                }
                            }
                        } catch (Exception $e) {
                            // ignore date parsing errors
                        }
                    }
                }
            }
        }
        header('Location: leaves.php');
        exit;
    }
}

// Fetch leaves. HR/Admin see all; managers see leaves of employees they manage.
$baseSql = "SELECT leaves.*, users.username, users.manager_id FROM leaves JOIN users ON leaves.user_id = users.id";
$mgrDept = null;
if (is_manager()) {
    $mgrDept = manager_department_id();
    if ($mgrDept) $baseSql .= " WHERE users.department_id = $mgrDept";
}
$baseSql .= " ORDER BY leaves.id DESC";
$leaves = $conn->query($baseSql);

// output HTML head so stylesheet is loaded
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Leaves</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Manage Leaves</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">All Leave Applications</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($leave = $leaves->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($leave['username']); ?></td>
                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                    <td><?php echo htmlspecialchars($leave['status']); ?></td>
                    <td>
                        <form method="post" style="display:inline;margin:0;">
                            <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                            <select name="status" onchange="this.form.submit()">
                                <option value="pending" <?php if ($leave['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="approved" <?php if ($leave['status'] === 'approved') echo 'selected'; ?>>Approve</option>
                                <option value="rejected" <?php if ($leave['status'] === 'rejected') echo 'selected'; ?>>Reject</option>
                            </select>
                            <input type="hidden" name="update_leave" value="1">
                        </form>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>

</body>
</html>
