<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_manager()) {
    header('Location: ../login.php'); die();
}

// Filters: date (yyyy-mm-dd) and user_id
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// If manager, limit to department users
$users = [];
if (is_manager()) {
    $dept = manager_department_id();
    $stmt = $conn->prepare('SELECT id, username, first_name, last_name FROM users WHERE department_id = ?');
    $stmt->bind_param('i', $dept);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $users[] = $r;
    $stmt->close();
} else {
    $res = $conn->query('SELECT id, username, first_name, last_name FROM users');
    while ($r = $res->fetch_assoc()) $users[] = $r;
}

// If summary requested, return JSON of present counts for each day in the month of filter_date
if (isset($_GET['summary']) && $_GET['summary']) {
    // compute first/last day of month from filter_date
    $ts = strtotime($filter_date);
    $first = date('Y-m-01', $ts);
    $last = date('Y-m-t', $ts);

    $sql = 'SELECT date, COUNT(*) as present_count FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date BETWEEN ? AND ? AND a.status = ?';
    $params = [$first, $last, 'present']; $types = 'sss';
    if ($filter_user > 0) { $sql .= ' AND a.user_id = ?'; $types .= 'i'; $params[] = $filter_user; }
    if (is_manager()) { $dept = manager_department_id(); $sql .= ' AND u.department_id = ?'; $types .= 'i'; $params[] = $dept; }
    $sql .= ' GROUP BY date ORDER BY date ASC';

    $stmt = $conn->prepare($sql);
    $labels = []; $values = [];
    if ($stmt) {
        // dynamic bind
        $bind_names = [$types];
        for ($i=0;$i<count($params);$i++) { $bind_name = 'b'.$i; $$bind_name = $params[$i]; $bind_names[] = &$$bind_name; }
        call_user_func_array([$stmt,'bind_param'],$bind_names);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) $map[$r['date']] = (int)$r['present_count'];
        $stmt->close();
        // build labels for each day in month
        $period = new DatePeriod(new DateTime($first), new DateInterval('P1D'), (new DateTime($last))->modify('+1 day'));
        foreach ($period as $d) {
            $day = $d->format('Y-m-d');
            $labels[] = $day;
            $values[] = $map[$day] ?? 0;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['labels'=>$labels,'values'=>$values]);
    exit;
}

// Build attendance query
$params = [];
// adapt to schema variations: older schema may have only status and note columns
$params[] = $filter_date;
$types = 's';
$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($has_clock) {
    $sql = 'SELECT a.id, a.user_id, a.date, a.clock_in, a.clock_out, a.status, a.notes, u.username, u.first_name, u.last_name FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ?';
} else {
    // older schema names
    $sql = 'SELECT a.id, a.user_id, a.date, NULL as clock_in, NULL as clock_out, a.status, a.note as notes, u.username, u.first_name, u.last_name FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ?';
}
if ($filter_user > 0) { $sql .= ' AND a.user_id = ?'; $types .= 'i'; $params[] = $filter_user; }

// Manager department enforcement: if manager, ensure we only select users in their department
if (is_manager()) {
    $dept = manager_department_id();
    $sql .= ' AND u.department_id = ?'; $types .= 'i'; $params[] = $dept;
}
$sql .= ' ORDER BY u.username ASC';

    $stmt = $conn->prepare($sql);
if ($stmt) {
    // dynamic bind
    $bind_names[] = $types;
    for ($i=0;$i<count($params);$i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    $stmt->execute();
    $res = $stmt->get_result();
    $att = [];
    while ($r = $res->fetch_assoc()) $att[] = $r;
    $stmt->close();
} else {
    $att = [];
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Attendance</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

<div class="page-header"><h1 class="h1">Attendance</h1></div>

<div class="container">
    <div class="card">
        <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <label>Date: <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>"></label>
            <label>User:
                <select name="user_id">
                    <option value="0">All users</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo (int)$u['id']; ?>" <?php if ($filter_user==(int)$u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['username'].' ('.trim($u['first_name'].' '.$u['last_name']).')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn">Filter</button>
            <a class="btn outline" href="attendance_export.php?date=<?php echo urlencode($filter_date); ?><?php if($filter_user) echo '&user_id='.(int)$filter_user; ?>">Export CSV</a>
            <a class="btn ghost" href="attendance_import.php">Import CSV</a>
        </form>
    </div>

    <div class="card recent-table">
        <h3 class="small-muted">Attendance for <?php echo htmlspecialchars($filter_date); ?></h3>
        <table class="table">
            <thead><tr><th>User</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php if (count($att) === 0): ?>
                <tr><td colspan="5" class="muted">No attendance records found</td></tr>
            <?php else: ?>
                <?php foreach ($att as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['username'].' ('.trim($r['first_name'].' '.$r['last_name']).')'); ?></td>
                        <td><?php if (!empty($r['clock_in'])) { $dt=new DateTime($r['clock_in'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else echo '—'; ?></td>
                        <td><?php if (!empty($r['clock_out'])) { $dt=new DateTime($r['clock_out'], new DateTimeZone(defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC')); $dt->setTimezone(new DateTimeZone(date_default_timezone_get())); echo htmlspecialchars($dt->format('Y-m-d H:i:s')); } else echo '—'; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($r['status'])); ?></td>
                        <td><?php echo htmlspecialchars($r['notes']); ?></td>
                        <td><a class="btn small" href="attendance_edit.php?id=<?php echo (int)$r['id']; ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="container">
    <div class="card">
        <h3 class="small-muted">Monthly Summary (present days)</h3>
        <canvas id="attendanceChart" width="600" height="200"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// fetch summary data via small AJAX endpoint (same page accepts JSON via ?summary=1)
fetch('attendance.php?summary=1&date=<?php echo urlencode($filter_date); ?><?php if($filter_user) echo '&user_id='.(int)$filter_user; ?>')
    .then(r=>r.json()).then(function(data){
        var ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: { labels: data.labels, datasets: [{ label: 'Present', data: data.values, backgroundColor: '#4caf50' }] },
            options: { responsive: true }
        });
    }).catch(function(){ /* ignore */ });
</script>
</div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
</body>
</html>