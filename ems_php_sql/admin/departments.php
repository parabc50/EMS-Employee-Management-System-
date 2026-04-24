<?php
require_once '../include/db.php';
check_login();

// Only allow users with role 'admin' who are in the Admin department to manage departments
// (this enforces "admin department" restriction)
if (!is_admin()) {
    header("Location: ../login.php");
    die();
}

// Only allow admin-role users to manage departments (no longer restricted by user's department)
$curUid = (int)($_SESSION['user_id'] ?? 0);
if (!is_admin()) {
    header("Location: ../login.php");
    die();
}

// Seed default departments if missing
$defaults = ['Admin','Human Resource','Developer','Tester'];
foreach ($defaults as $dn) {
    $check = $conn->prepare('SELECT id FROM departments WHERE name = ? LIMIT 1');
    if ($check) { $check->bind_param('s', $dn); $check->execute(); $res = $check->get_result(); if ($res->num_rows === 0) { $ins = $conn->prepare('INSERT INTO departments (name) VALUES (?)'); if ($ins) { $ins->bind_param('s', $dn); $ins->execute(); $ins->close(); } } $check->close(); }
}

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $stmt = $conn->prepare('INSERT INTO departments (name) VALUES (?)');
        if ($stmt) { $stmt->bind_param('s', $name); $stmt->execute(); $stmt->close(); }
    } elseif (isset($_POST['update_department'])) {
        $id = (int)$_POST['dept_id'];
        $name = $conn->real_escape_string($_POST['name']);
        $stmt = $conn->prepare('UPDATE departments SET name = ? WHERE id = ?');
        if ($stmt) { $stmt->bind_param('si', $name, $id); $stmt->execute(); $stmt->close(); }
    } elseif (isset($_POST['delete_department'])) {
        $id = (int)$_POST['dept_id'];
        // Before delete, unset department_id from users who belong to it
        $stmt = $conn->prepare('UPDATE users SET department_id = NULL WHERE department_id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        $stmt = $conn->prepare('DELETE FROM departments WHERE id = ?');
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
    }
    header('Location: departments.php'); exit;
}

$has_manager_col = false;
$colCheck = $conn->query("SHOW COLUMNS FROM departments LIKE 'manager_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $has_manager_col = true;
}

if ($has_manager_col) {
    $departments = $conn->query('SELECT d.*, COUNT(u.id) AS user_count, m.username AS manager_username FROM departments d LEFT JOIN users u ON u.department_id = d.id LEFT JOIN users m ON d.manager_id = m.id GROUP BY d.id ORDER BY d.name ASC');
} else {
    // fallback if manager_id column not present
    $departments = $conn->query('SELECT d.*, COUNT(u.id) AS user_count FROM departments d LEFT JOIN users u ON u.department_id = d.id GROUP BY d.id ORDER BY d.name ASC');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Departments</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Manage Departments</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">Add Department</h3>
        <form method="post" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Name:</label>
                    <input type="text" name="name" required>
                </div>
                <div class="col form-group text-right" style="align-self:end">
                    <button class="btn" type="submit" name="add_department">Add</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card section">
        <h3 class="small-muted">Existing Departments</h3>
        <table class="table">
            <thead>
                <tr><th>Name</th><th>Users</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php while ($d = $departments->fetch_assoc()) { ?>
                <tr>
                    <td data-label="Name">
                        <?php echo htmlspecialchars($d['name']); ?>
                        <?php if (!empty($d['manager_username'])): ?>
                            <div class="small-muted">Manager: <strong><?php echo htmlspecialchars($d['manager_username']); ?></strong></div>
                        <?php endif; ?>
                    </td>
                    <td data-label="Users"><?php echo (int)($d['user_count'] ?? 0); ?> <a href="department_members.php?dept=<?php echo $d['id']; ?>" class="btn outline small">View members</a></td>
                    <td data-label="Actions">
                        <a class="btn small" href="department_leaves.php?dept=<?php echo $d['id']; ?>">Leaves</a>
                        <a class="btn small" href="department_salaries.php?dept=<?php echo $d['id']; ?>">Salaries</a>
                        <form method="post" style="display:inline-block;margin-left:8px;">
                            <input type="hidden" name="dept_id" value="<?php echo $d['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($d['name']); ?>">
                            <button class="btn small" type="submit" name="update_department">Save</button>
                        </form>
                        <form method="post" style="display:inline-block;" onsubmit="return confirm('Delete this department? This will unassign it from users.');">
                            <input type="hidden" name="dept_id" value="<?php echo $d['id']; ?>">
                            <button class="btn danger small" type="submit" name="delete_department">Delete</button>
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
