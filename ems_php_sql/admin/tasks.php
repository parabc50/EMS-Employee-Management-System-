<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

// admin header will be included after the HTML head so CSS loads correctly

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        $uid = (int)$_POST['user_id'];
        // if manager, ensure the assigned user is in manager's department
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $res = $conn->query("SELECT department_id FROM users WHERE id = $uid");
            $row = $res ? $res->fetch_assoc() : null;
            if (!($row && $row['department_id'] == $mgrDept)) {
                die('Permission denied: you can only assign tasks to users in your department.');
            }
        }
        $stmt = $conn->prepare('INSERT INTO tasks (user_id, title, description, priority, due_date) VALUES (?, ?, ?, ?, ?)');
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $pri = $_POST['priority'];
        $dd = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $stmt->bind_param('issss', $uid, $title, $desc, $pri, $dd);
        $stmt->execute(); $stmt->close();
        header('Location: tasks.php'); exit;
    } elseif (isset($_POST['update_task'])) {
        $status = $_POST['status']; $tid = (int)$_POST['task_id'];
        if (is_manager()) {
            // ensure task belongs to a user in manager's department
            $mgrDept = manager_department_id();
            $check = $conn->query("SELECT u.department_id FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = $tid");
            $crow = $check ? $check->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $stmt = $conn->prepare('UPDATE tasks SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $tid); $stmt->execute(); $stmt->close();
        header('Location: tasks.php'); exit;
    } elseif (isset($_POST['delete_task'])) {
        $tid = (int)$_POST['task_id'];
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $check = $conn->query("SELECT u.department_id FROM tasks t JOIN users u ON t.user_id = u.id WHERE t.id = $tid");
            $crow = $check ? $check->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $stmt = $conn->prepare('DELETE FROM tasks WHERE id = ?');
        $stmt->bind_param('i',$tid); $stmt->execute(); $stmt->close();
        header('Location: tasks.php'); exit;
    }
}

// filters
$filters = [];
$params = [];
$where = '';
if (!empty($_GET['status'])) { $filters[] = 'tasks.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['priority'])) { $filters[] = 'tasks.priority = ?'; $params[] = $_GET['priority']; }
if (!empty($_GET['assigned'])) { $filters[] = 'tasks.user_id = ?'; $params[] = (int)$_GET['assigned']; }
if ($filters) { $where = 'WHERE ' . implode(' AND ', $filters); }

// pagination
$perPage = 15; $page = max(1, (int)($_GET['p'] ?? 1)); $offset = ($page-1)*$perPage;

// build query with prepared statements
$baseSql = "SELECT SQL_CALC_FOUND_ROWS tasks.*, users.username FROM tasks JOIN users ON tasks.user_id = users.id";
// if manager, limit to managed employees
if (is_manager()) {
    $mgr = (int)$_SESSION['user_id'];
    $baseSql .= " WHERE users.manager_id = $mgr";
    if ($where) { $baseSql .= ' AND ' . substr($where, 6); } // attach additional filters (remove leading WHERE)
} else {
    if ($where) { $baseSql .= ' ' . $where; }
}
$baseSql .= " ORDER BY tasks.id DESC LIMIT ?, ?";
$stmt = $conn->prepare($baseSql);
// bind params dynamically
$types = '';
$binds = [];
foreach ($params as $p) { $types .= is_int($p)?'i':'s'; $binds[] = $p; }
$types .= 'ii'; $binds[] = $offset; $binds[] = $perPage;
if ($types) {
    $stmt->bind_param($types, ...$binds);
}
$stmt->execute();
$tasks = $stmt->get_result();
$totalRes = $conn->query('SELECT FOUND_ROWS() as cnt'); $total = $totalRes->fetch_assoc()['cnt'];

// Fetch users for dropdown
$usersSql = "SELECT id, username FROM users WHERE role = 'employee'";
if (is_manager()) {
    $mgrDept = manager_department_id();
    if ($mgrDept) $usersSql .= " AND department_id = $mgrDept";
}
$users = $conn->query($usersSql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Tasks</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>
    <div class="page-header">
        <h1 class="h1">Manage Tasks</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">Add Task</h3>
    <form method="post" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Assign to:</label>
                    <select name="user_id" required>
                        <?php while ($user = $users->fetch_assoc()) { ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Priority:</label>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="description"></textarea>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>Due Date:</label>
                    <input type="date" name="due_date">
                </div>
                <div class="col form-group text-right" style="align-self:end">
                    <button class="btn" type="submit" name="add_task">Add Task</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card section">
        <h3 class="small-muted">All Tasks</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Title</th>
                <th>Assigned To</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($task = $tasks->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                    <td><?php echo htmlspecialchars($task['username']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($task['priority'])); ?></td>
                    <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                    <td>
                        <?php echo htmlspecialchars(ucfirst(str_replace('_',' ', $task['status']))); ?>
                    </td>
                    <td>
                        <a class="btn small" href="edit_task.php?id=<?php echo $task['id']; ?>">Edit</a>
                        <form method="post" style="display:inline;margin:0 6px;" onsubmit="return confirm('Delete this task?');">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <button class="btn danger small" type="submit" name="delete_task">Delete</button>
                        </form>
                        <form method="post" style="display:inline;margin-left:6px;">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <select name="status" onchange="this.form.submit()">
                                <option value="pending" <?php if ($task['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="in_progress" <?php if ($task['status'] === 'in_progress') echo 'selected'; ?>>In Progress</option>
                                <option value="completed" <?php if ($task['status'] === 'completed') echo 'selected'; ?>>Completed</option>
                            </select>
                            <input type="hidden" name="update_task" value="1">
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
