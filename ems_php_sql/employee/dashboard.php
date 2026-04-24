<?php
require_once '../include/db.php';
check_login();

// Get user id
$user_id = $_SESSION['user_id'] ?? 0;
// Pending tasks (MySQLi)
$pending_tasks = 0;
$tasks = $conn->prepare("SELECT COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status != 'completed'");
if ($tasks) {
    $tasks->bind_param('i', $user_id);
    $tasks->execute();
    $res = $tasks->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        $pending_tasks = isset($row['cnt']) ? (int)$row['cnt'] : 0;
    }
    $tasks->close();
}

// Leave status (MySQLi)
$leave_status = [];
$leaves = $conn->prepare("SELECT status, COUNT(*) as cnt FROM leaves WHERE user_id = ? GROUP BY status");
if ($leaves) {
    $leaves->bind_param('i', $user_id);
    $leaves->execute();
    $res = $leaves->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $leave_status[$r['status']] = (int)$r['cnt'];
        }
    }
    $leaves->close();
}

// Last salary (MySQLi)
$last_salary = null;
$salary = $conn->prepare("SELECT amount, paid_date FROM salaries WHERE user_id = ? ORDER BY paid_date DESC LIMIT 1");
if ($salary) {
    $salary->bind_param('i', $user_id);
    $salary->execute();
    $res = $salary->get_result();
    if ($res) {
        $row = $res->fetch_assoc();
        if ($row) {
            $last_salary = $row;
        }
    }
    $salary->close();
}

// Notifications
$notif = $conn->prepare("SELECT message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
if ($notif) {
    $notif->bind_param('i', $user_id);
    $notif->execute();
    $result = $notif->get_result();
    $notifications = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
} else {
    $notifications = [];
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Employee Dashboard</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/user_header.php'; ?>

<div class="page-header">
    <h1 class="h1">Employee Dashboard</h1>
</div>

<div class="container">
    <div class="dashboard-grid">
        <div>
            <div class="card">
                <h3 class="small-muted">Welcome</h3>
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong></p>
            </div>

            <div class="stats-grid">
                <div class="stat">
                    <div class="icon">📋</div>
                    <div class="meta"><div class="num"><?php echo $pending_tasks; ?></div><div class="label">Pending Tasks</div></div>
                </div>
                <div class="stat">
                    <div class="icon">🏖️</div>
                    <div class="meta"><div class="num"><?php echo $leave_status['pending'] ?? 0; ?></div><div class="label">Pending Leaves</div></div>
                </div>
                <div class="stat">
                    <div class="icon">💵</div>
                    <div class="meta"><div class="num"><?php echo is_array($last_salary) && isset($last_salary['amount']) ? number_format($last_salary['amount'],2) : '—'; ?></div><div class="label">Last Salary</div></div>
                </div>
                <div class="stat">
                    <div class="icon">🔔</div>
                    <div class="meta"><div class="num"><?php echo count($notifications); ?></div><div class="label">Notifications</div></div>
                </div>
            </div>

            <div class="card">
                <h3 class="small-muted">Quick Summary</h3>
                <ul>
                    <li>Pending Tasks: <strong><?php echo $pending_tasks; ?></strong> (<a href="tasks.php">View Tasks</a>)</li>
                    <li>Leaves: Approved: <?php echo $leave_status['approved'] ?? 0; ?> | Pending: <?php echo $leave_status['pending'] ?? 0; ?> | Rejected: <?php echo $leave_status['rejected'] ?? 0; ?></li>
                    <li>Last Salary: <strong><?php echo (is_array($last_salary) && isset($last_salary['amount'])) ? number_format($last_salary['amount'],2) : 'N/A'; ?></strong>
                        <?php if (is_array($last_salary) && isset($last_salary['paid_date'])) { echo '(Paid on ' . htmlspecialchars($last_salary['paid_date']) . ')'; } ?> <a href="salary.php">View Salary History</a>
                    </li>
                </ul>
            </div>

            <div class="card">
                <h3 class="small-muted">Quick Actions</h3>
                <p><a class="btn" href="leaves.php?action=apply">Apply for Leave</a> <a class="btn outline" href="tasks.php">View My Tasks</a> <a class="btn ghost" href="salary.php">View My Salary</a></p>
            </div>
        </div>

        <aside>
            <div class="card">
                <h3 class="small-muted">Recent Tasks</h3>
                <?php
                $rt = $conn->prepare("SELECT id, title, status, due_date FROM tasks WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                $recent_tasks = [];
                if ($rt) {
                    $rt->bind_param('i', $user_id);
                    $rt->execute();
                    $rres = $rt->get_result();
                    if ($rres) { while ($rr = $rres->fetch_assoc()) $recent_tasks[] = $rr; }
                }
                ?>
                <?php if (count($recent_tasks) > 0): ?>
                    <ul>
                        <?php foreach ($recent_tasks as $t): ?>
                            <li><?php echo htmlspecialchars($t['title']); ?> <small class="muted">(<?php echo htmlspecialchars(ucfirst(str_replace('_',' ',$t['status']))); ?><?php if ($t['due_date']) echo ' • Due ' . htmlspecialchars($t['due_date']); ?>)</small></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No recent tasks</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 class="small-muted">Notifications</h3>
                <?php if ($notifications && count($notifications) > 0): ?>
                    <ul id="notifications-list">
                        <?php foreach ($notifications as $note): ?>
                            <li data-id="<?php echo (int)$note['id']; ?>">
                                <?php echo htmlspecialchars($note['message']); ?> <small>(<?php echo htmlspecialchars($note['created_at']); ?>)</small>
                                <button class="btn small outline mark-read" data-id="<?php echo (int)$note['id']; ?>">Mark read</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No recent notifications</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

</div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.mark-read').forEach(function(btn){
        btn.addEventListener('click', function(e){
            var id = this.getAttribute('data-id');
            if (!confirm('Mark this notification as read?')) return;
            var fd = new FormData(); fd.append('id', id);
            fetch('/ems_php_sql/notifications_mark_read.php', {method:'POST', body: fd}).then(function(r){ return r.json(); }).then(function(j){
                if (j && j.success) {
                    var li = btn.closest('li'); if (li) li.remove();
                } else alert('Failed to mark read');
            }).catch(function(){ alert('Network error'); });
        });
    });
});
</script>
