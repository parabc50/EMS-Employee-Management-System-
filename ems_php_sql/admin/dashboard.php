<?php
require_once '../include/db.php';
check_login();
// Allow admins and managers to access the admin dashboard; managers will see a scoped view
if (!is_admin() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

$is_manager_view = is_manager();
$mgr_dept = null;
if ($is_manager_view) {
    $mgr_dept = manager_department_id();
    if ($mgr_dept === null) $mgr_dept = 0; // safe fallback
}

// helper to get department name
function getDepartmentName($id) {
    global $conn;
    $id = (int)$id;
    if ($id <= 0) return '—';
    $res = $conn->query("SELECT name FROM departments WHERE id = $id LIMIT 1");
    if ($res) {
        $row = $res->fetch_assoc();
        return $row['name'] ?? '—';
    }
    return '—';
}

// compute counts using mysqli connection ($conn)
// Compute counts scoped by admin or manager view
$counts = [];
if ($is_manager_view) {
    // Users in manager's department
    $uRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE department_id = " . (int)$mgr_dept);
    $counts['users'] = $uRes ? (int)($uRes->fetch_assoc()['c'] ?? 0) : 0;

    $empRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'employee' AND department_id = " . (int)$mgr_dept);
    $counts['employees'] = $empRes ? (int)($empRes->fetch_assoc()['c'] ?? 0) : 0;

    // Active approved leaves for department users
    $leavesRes = $conn->query("SELECT COUNT(*) as c FROM leaves l JOIN users u ON l.user_id = u.id WHERE u.department_id = " . (int)$mgr_dept . " AND l.status = 'approved' AND l.start_date <= CURDATE() AND (l.end_date IS NULL OR l.end_date >= CURDATE())");
    $counts['leaves'] = $leavesRes ? (int)($leavesRes->fetch_assoc()['c'] ?? 0) : 0;

    // Salaries for department users (paid this month)
    $salRes = $conn->query("SELECT COUNT(*) as c FROM salaries s JOIN users u ON s.user_id = u.id WHERE u.department_id = " . (int)$mgr_dept . " AND s.status = 'paid' AND MONTH(s.paid_date) = MONTH(CURDATE()) AND YEAR(s.paid_date) = YEAR(CURDATE())");
    $activeSalariesCount = $salRes ? (int)($salRes->fetch_assoc()['c'] ?? 0) : 0;
    if ($activeSalariesCount > 0) {
        $counts['salaries'] = $activeSalariesCount;
    } else {
        $tot = $conn->query("SELECT COUNT(*) as c FROM salaries s JOIN users u ON s.user_id = u.id WHERE u.department_id = " . (int)$mgr_dept);
        $counts['salaries'] = $tot ? (int)($tot->fetch_assoc()['c'] ?? 0) : 0;
    }
} else {
    // Admin - original global counts
    $uRes = $conn->query("SELECT COUNT(*) as c FROM users");
    $counts['users'] = $uRes ? (int)($uRes->fetch_assoc()['c'] ?? 0) : 0;

    $empRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'employee'");
    $counts['employees'] = $empRes ? (int)($empRes->fetch_assoc()['c'] ?? 0) : 0;

    $leavesRes = $conn->query("SELECT COUNT(*) as c FROM leaves WHERE status = 'approved' AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())");
    $counts['leaves'] = $leavesRes ? (int)($leavesRes->fetch_assoc()['c'] ?? 0) : 0;

    $activeSalariesRes = $conn->query("SELECT COUNT(*) as c FROM salaries WHERE status = 'paid' AND MONTH(paid_date) = MONTH(CURDATE()) AND YEAR(paid_date) = YEAR(CURDATE())");
    $activeSalariesCount = $activeSalariesRes ? (int)($activeSalariesRes->fetch_assoc()['c'] ?? 0) : 0;
    if ($activeSalariesCount > 0) {
        $counts['salaries'] = $activeSalariesCount;
    } else {
        $tot = $conn->query("SELECT COUNT(*) as c FROM salaries");
        $counts['salaries'] = $tot ? (int)($tot->fetch_assoc()['c'] ?? 0) : 0;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Dashboard</h1>
    </div>

    <div class="stats-grid">
        <div class="stat">
            <div class="icon">👥</div>
            <div class="meta">
                <div class="num"><?php echo $counts['users']; ?></div>
                <div class="label">Total Users</div>
            </div>
        </div>
        <div class="stat">
            <div class="icon">👤</div>
            <div class="meta">
                <div class="num"><?php echo $counts['employees']; ?></div>
                <div class="label">Employees</div>
            </div>
        </div>
        <div class="stat">
            <div class="icon">📅</div>
            <div class="meta">
                <div class="num"><?php echo $counts['leaves']; ?></div>
                <div class="label">Leaves</div>
            </div>
        </div>
        <div class="stat">
            <div class="icon">💰</div>
            <div class="meta">
                <div class="num"><?php echo isset($counts['salaries']) ? $counts['salaries'] : '—'; ?></div>
                <div class="label">Salaries</div>
            </div>
        </div>
    </div>

        <?php
        // fetch a few recent users and tasks for quick view (scoped to manager's department if needed)
        if ($is_manager_view) {
            $recentUsers = $conn->query("SELECT id, username, first_name, last_name FROM users WHERE department_id = " . (int)$mgr_dept . " ORDER BY id DESC LIMIT 6");
            $recentTasks = $conn->query("SELECT tasks.id, tasks.title, users.username, tasks.status FROM tasks JOIN users ON tasks.user_id=users.id WHERE users.department_id = " . (int)$mgr_dept . " ORDER BY tasks.id DESC LIMIT 6");
        } else {
            $recentUsers = $conn->query("SELECT id, username, first_name, last_name FROM users ORDER BY id DESC LIMIT 6");
            $recentTasks = $conn->query("SELECT tasks.id, tasks.title, users.username, tasks.status FROM tasks JOIN users ON tasks.user_id=users.id ORDER BY tasks.id DESC LIMIT 6");
        }
        ?>

        <div class="dashboard-grid">
            <div>
                <div class="card recent-table">
                    <h3 class="small-muted">Recent Users</h3>
                    <table class="table">
                        <thead>
                        <tr><th>Username</th><th>Name</th></tr>
                        </thead>
                        <tbody>
                        <?php while($u = $recentUsers->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars(trim($u['first_name'].' '.$u['last_name'])); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>

                <div class="card recent-table">
                    <h3 class="small-muted">Recent Tasks</h3>
                    <table class="table">
                        <thead><tr><th>Title</th><th>Assigned</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php while($t = $recentTasks->fetch_assoc()) { 
                            $status = $t['status'];
                            $status_label = ucfirst(str_replace('_',' ',$status));
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><?php echo htmlspecialchars($t['username']); ?></td>
                                <td><span class="badge status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_label); ?></span></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="card">
                    <h3 class="small-muted">Welcome</h3>
                    <?php if ($is_manager_view): ?>
                        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></p>
                        <p class="small-muted">Viewing department: <strong><?php echo htmlspecialchars(getDepartmentName($mgr_dept)); ?></strong></p>
                    <?php else: ?>
                        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></p>
                    <?php endif; ?>
                </div>
                <div class="card quick-links">
                    <h3 class="small-muted">Quick Links</h3>
                    <p><a class="btn" href="departments.php">Manage Departments</a></p>
                    <p><a class="btn ghost" href="users.php">Manage Users</a></p>
                    <p><a class="btn ghost" href="tasks.php">View Tasks</a></p>
                </div>
            </aside>
        </div>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>

</body>
</html>
