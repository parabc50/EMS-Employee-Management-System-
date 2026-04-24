<?php
require_once '../include/db.php';
check_login();
// Allow admins and managers (managers will be scoped to their department)
if (!is_admin() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

// Handle form submissions for adding or deleting a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = sha1($_POST['password']);
        $role = $conn->real_escape_string($_POST['role']);
        $email = $conn->real_escape_string($_POST['email']);
        $first_name = $conn->real_escape_string($_POST['first_name']);
        $last_name = $conn->real_escape_string($_POST['last_name']);
        $dob = $conn->real_escape_string($_POST['dob']);
        $phone = $conn->real_escape_string($_POST['phone']);

        // If current user is a manager, force role to 'employee' and assign department/manager to them
        if (is_manager()) {
            $role = 'employee';
            $dept_id = manager_department_id();
            $department_id = $dept_id !== null ? (int)$dept_id : 'NULL';
            $manager_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'NULL';
        } else {
            $department_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : 'NULL';
            $manager_id = isset($_POST['manager_id']) && $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : 'NULL';
        }

        $sql = "INSERT INTO users (username, password, role, email, first_name, last_name, dob, phone, department_id, manager_id) VALUES ('$username', '$password', '$role', '$email', '$first_name', '$last_name', '$dob', '$phone', " . ($department_id === 'NULL' ? "NULL" : $department_id) . ", " . ($manager_id === 'NULL' ? "NULL" : $manager_id) . ")";
        $conn->query($sql);

        // If admin requested this user to be the manager of the department, update departments.manager_id if column exists
        if (is_admin() && isset($_POST['set_as_dept_manager']) && $role === 'manager' && isset($_POST['department_id']) && $_POST['department_id'] !== '') {
            $new_user_id = $conn->insert_id;
            $dept = (int)$_POST['department_id'];
            // Check if departments.manager_id column exists
            $colCheck = $conn->query("SHOW COLUMNS FROM departments LIKE 'manager_id'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $conn->query("UPDATE departments SET manager_id = $new_user_id WHERE id = $dept");
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        $sql = "DELETE FROM users WHERE id = $user_id";
        $conn->query($sql);
    }
    header("Location: users.php"); // Redirect to avoid form resubmission
    die();
}

// Fetch users to display. Admins see all; managers only see their department
if (is_admin()) {
    $sql = "SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id";
    $users = $conn->query($sql);
} else {
    $mgrDept = manager_department_id();
    $sql = "SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.department_id = " . (int)$mgrDept;
    $users = $conn->query($sql);
}

// fetch departments and potential managers for the add form
if (is_admin()) {
    $departments = $conn->query("SELECT id, name FROM departments");
    $managers = $conn->query("SELECT id, username, department_id FROM users WHERE role = 'manager'");
} else {
    // managers can only add to their own department and can only be the manager for themselves
    $mgrDept = manager_department_id();
    $departments = $conn->query("SELECT id, name FROM departments WHERE id = " . (int)$mgrDept);
    // only list the current manager as an option
    $managers = $conn->query("SELECT id, username, department_id FROM users WHERE id = " . (int)$_SESSION['user_id']);
}

// admin header will be included after the HTML head so CSS loads correctly
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>
    <div class="page-header">
        <h1 class="h1">Manage Users</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">Add User</h3>
    <form method="post" action="users.php" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required maxlength="64">
                </div>
                <div class="col form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required minlength="6" maxlength="128">
                </div>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>Role:</label>
                    <select name="role">
                        <option value="admin">Admin</option>
                        <option value="hr">HR</option>
                        <option value="manager">Manager</option>
                        <option value="employee" selected>Employee</option>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>Department:</label>
                    <select name="department_id">
                        <option value="">-- None --</option>
                        <?php while ($d = $departments->fetch_assoc()) { ?>
                            <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Manager:</label>
                    <select name="manager_id" id="manager_select">
                        <option value="">-- None --</option>
                        <?php while ($m = $managers->fetch_assoc()) { ?>
                            <option value="<?php echo $m['id']; ?>" data-dept="<?php echo $m['department_id']; ?>"><?php echo htmlspecialchars($m['username']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php if (is_admin()): ?>
                <div class="col form-group">
                    <label><input type="checkbox" name="set_as_dept_manager" value="1"> Set as department manager</label>
                </div>
                <?php endif; ?>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name">
                </div>
                <div class="col form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name">
                </div>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>Date of Birth:</label>
                    <input type="date" name="dob">
                </div>
                <div class="col form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" pattern="[0-9]{10}" maxlength="10" placeholder="10 digits">
                </div>
            </div>
            <div class="form-group text-right">
                <button class="btn" type="submit" name="add_user">Add User</button>
            </div>
        </form>
    </div>

    <div class="card section">
        <h3 class="small-muted">All Users</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>DOB</th>
                    <th>Phone</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()) { ?>
                    <tr>
                        <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td data-label="Role"><?php echo htmlspecialchars($user['role']); ?></td>
                        <td data-label="Department"><?php echo htmlspecialchars($user['dept_name'] ?? ''); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td data-label="Name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td data-label="DOB"><?php echo htmlspecialchars($user['dob']); ?></td>
                        <td data-label="Phone"><?php echo htmlspecialchars($user['phone']); ?></td>
                        <td data-label="Actions">
                            <a class="btn outline small" href="edit_user.php?id=<?php echo $user['id']; ?>">Edit</a>
                            <form method="post" action="users.php" style="display: inline;margin-left:8px;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button class="btn danger small" type="submit" name="delete_user" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
<script>
// filter managers by department selection
document.addEventListener('DOMContentLoaded', function(){
    var dept = document.querySelector('select[name="department_id"]');
    var mgr = document.getElementById('manager_select');
    if (!dept || !mgr) return;
    function filter(){
        var val = dept.value;
        for (var i=0;i<mgr.options.length;i++){
            var opt = mgr.options[i];
            var d = opt.getAttribute('data-dept');
            if (!val || d === null || d === '' || d === val) { opt.style.display = ''; }
            else { opt.style.display = 'none'; }
        }
        // if currently selected manager hidden, reset selection
        if (mgr.selectedOptions.length && mgr.selectedOptions[0].style.display === 'none') mgr.value = '';
    }
    dept.addEventListener('change', filter);
    filter();
});
</script>
</body>
</html>
