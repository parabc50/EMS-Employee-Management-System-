<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_hr() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_salary'])) {
        $user_id = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $res = $conn->query("SELECT department_id FROM users WHERE id = $user_id");
            $crow = $res ? $res->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $stmt = $conn->prepare('INSERT INTO salaries (user_id, amount) VALUES (?, ?)');
        $stmt->bind_param('id', $user_id, $amount); $stmt->execute(); $stmt->close();
        header('Location: salaries.php'); exit;
    } elseif (isset($_POST['update_salary'])) {
        // status quick update from dropdown
        $salary_id = (int)$_POST['salary_id'];
        $status = $conn->real_escape_string($_POST['status']);
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $check = $conn->query("SELECT u.department_id FROM salaries s JOIN users u ON s.user_id = u.id WHERE s.id = $salary_id");
            $crow = $check ? $check->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $paid_date = ($status === 'paid') ? date('Y-m-d') : NULL;
        $stmt = $conn->prepare('UPDATE salaries SET status = ?, paid_date = ? WHERE id = ?');
        $stmt->bind_param('ssi', $status, $paid_date, $salary_id); $stmt->execute(); $stmt->close();
        header('Location: salaries.php'); exit;
    } elseif (isset($_POST['delete_salary'])) {
        $salary_id = (int)$_POST['salary_id'];
        if (is_manager()) {
            $mgrDept = manager_department_id();
            $check = $conn->query("SELECT u.department_id FROM salaries s JOIN users u ON s.user_id = u.id WHERE s.id = $salary_id");
            $crow = $check ? $check->fetch_assoc() : null;
            if (!($crow && $crow['department_id'] == $mgrDept)) die('Permission denied');
        }
        $stmt = $conn->prepare('DELETE FROM salaries WHERE id = ?');
        $stmt->bind_param('i', $salary_id); $stmt->execute(); $stmt->close();
        header('Location: salaries.php'); exit;
    }
}

// Fetch salaries. HR and Admin see all; managers see salaries of employees they manage.
$baseSql = "SELECT salaries.*, users.username, users.manager_id FROM salaries JOIN users ON salaries.user_id = users.id";
$mgrDept = null;
if (is_manager()) {
    $mgrDept = manager_department_id();
    if ($mgrDept) $baseSql .= " WHERE users.department_id = $mgrDept";
}
$salaries = $conn->query($baseSql);

// Fetch users for dropdown (HR/Admin see all employees; manager sees only their employees)
if (is_manager()) {
    $mgrDept = manager_department_id();
    // show all users in the manager's department (employees and others if you prefer)
    $users = $conn->query("SELECT id, username FROM users WHERE department_id = " . (int)$mgrDept);
} else {
    // admins/HR see all users (include managers/HR if you want to pay them)
    $users = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manage Salaries</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Manage Salaries</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">Add Salary</h3>
    <form method="post" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Employee:</label>
                    <select name="user_id" required>
                        <?php while ($user = $users->fetch_assoc()) { ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Amount:</label>
                    <input type="number" step="0.01" name="amount" required>
                </div>
            </div>
            <div class="form-group text-right">
                <button class="btn" type="submit" name="add_salary">Add Salary</button>
            </div>
        </form>
    </div>

    <div class="card section">
        <h3 class="small-muted">All Salaries</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Paid Date</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($salary = $salaries->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($salary['username']); ?></td>
                    <td><?php echo htmlspecialchars($salary['amount']); ?></td>
                    <td><?php echo htmlspecialchars($salary['status']); ?></td>
                    <td><?php echo htmlspecialchars($salary['paid_date']); ?></td>
                    <td>
                        <a class="btn small" href="edit_salary.php?id=<?php echo $salary['id']; ?>">Edit</a>
                        <form method="post" style="display:inline;margin:0 6px;" onsubmit="return confirm('Delete this salary record?');">
                            <input type="hidden" name="salary_id" value="<?php echo $salary['id']; ?>">
                            <button class="btn danger small" type="submit" name="delete_salary">Delete</button>
                        </form>
                        <form method="post" style="display:inline;margin-left:6px;">
                            <input type="hidden" name="salary_id" value="<?php echo $salary['id']; ?>">
                            <select name="status" onchange="this.form.submit()">
                                <option value="unpaid" <?php if ($salary['status'] === 'unpaid') echo 'selected'; ?>>Unpaid</option>
                                <option value="paid" <?php if ($salary['status'] === 'paid') echo 'selected'; ?>>Paid</option>
                            </select>
                            <input type="hidden" name="update_salary" value="1">
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
