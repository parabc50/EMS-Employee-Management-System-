<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_manager()) { header('Location: ../login.php'); die(); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// fetch record adaptively
$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($has_clock) {
    $stmt = $conn->prepare('SELECT a.*, u.username, u.department_id FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1');
} else {
    $stmt = $conn->prepare('SELECT a.id, a.user_id, a.date, a.status, a.note as notes, u.username, u.department_id FROM attendance a JOIN users u ON u.id = a.user_id WHERE a.id = ? LIMIT 1');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$rec = $res ? $res->fetch_assoc() : null;
$stmt->close();
if (!$rec) { echo 'Record not found'; exit; }
// manager can only edit users in their dept
if (is_manager()) {
    $dept = manager_department_id();
    if ((int)$rec['department_id'] !== (int)$dept) { echo 'Permission denied'; exit; }
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $status = in_array($_POST['status'], ['present','absent','on_leave']) ? $_POST['status'] : 'present';
    $notes = $_POST['notes'] ?? null;
    if ($has_clock) {
        $clock_in = $_POST['clock_in'] ?: null;
        $clock_out = $_POST['clock_out'] ?: null;
        $u = $conn->prepare('UPDATE attendance SET clock_in = ?, clock_out = ?, status = ?, notes = ? WHERE id = ?');
        $u->bind_param('ssssi', $clock_in, $clock_out, $status, $notes, $id);
    } else {
        $u = $conn->prepare('UPDATE attendance SET status = ?, note = ? WHERE id = ?');
        $u->bind_param('ssi', $status, $notes, $id);
    }
    $u->execute();
    $u->close();
    header('Location: attendance.php?date=' . urlencode($rec['date'])); die();
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Edit Attendance</title><link rel="stylesheet" href="../css/style.css"></head><body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>
<div class="container"><div class="card"><h3 class="small-muted">Edit Attendance for <?php echo htmlspecialchars($rec['username'].' on '.$rec['date']); ?></h3>
<form method="post">
    <label>Clock In: <input type="datetime-local" name="clock_in" value="<?php if(!empty($rec['clock_in'])) { $d=new DateTime($rec['clock_in'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $d->setTimezone(new DateTimeZone(date_default_timezone_get())); echo $d->format('Y-m-d\TH:i'); } else echo ''; ?>"></label>
    <label>Clock Out: <input type="datetime-local" name="clock_out" value="<?php if(!empty($rec['clock_out'])) { $d=new DateTime($rec['clock_out'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $d->setTimezone(new DateTimeZone(date_default_timezone_get())); echo $d->format('Y-m-d\TH:i'); } else echo ''; ?>"></label>
    <label>Status: <select name="status"><option value="present" <?php if($rec['status']=='present') echo 'selected'; ?>>Present</option><option value="absent" <?php if($rec['status']=='absent') echo 'selected'; ?>>Absent</option><option value="on_leave" <?php if($rec['status']=='on_leave') echo 'selected'; ?>>On Leave</option></select></label>
    <label>Notes: <textarea name="notes"><?php echo htmlspecialchars($rec['notes']); ?></textarea></label>
    <p><button class="btn">Save</button> <a class="btn ghost" href="attendance.php?date=<?php echo urlencode($rec['date']); ?>">Cancel</a></p>
</form>
</div></div>
<?php include __DIR__ . '/../include/admin_footer.php'; ?></body></html>