<?php
require_once '../include/db.php';
check_login();
// allow admin or manager (managers will be checked later for department scope)
if (!is_admin() && !is_manager()) {
    header("Location: ../login.php");
    die();
}

$user_id = (int)$_GET['id'];

// Handle form submission for updating a user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $username = $conn->real_escape_string($_POST['username']);
    $role = $conn->real_escape_string($_POST['role']);
    $email = $conn->real_escape_string($_POST['email']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $dob = $conn->real_escape_string($_POST['dob']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $department_id = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : NULL;
    $manager_id = isset($_POST['manager_id']) && $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : NULL;

    // If current user is a manager, ensure they only edit users in their department
    if (is_manager()) {
        $mgrDept = manager_department_id();
        $res = $conn->query("SELECT department_id FROM users WHERE id = $user_id");
        $urow = $res ? $res->fetch_assoc() : null;
        if (!($urow && $urow['department_id'] == $mgrDept)) {
            die('Permission denied: you can only edit users in your department.');
        }
        // additionally, prevent manager from changing role to admin or hr
        if ($role === 'admin' || $role === 'hr' || $role === 'manager') {
            die('Permission denied: cannot assign privileged roles.');
        }
    }

    $sql = "UPDATE users SET username = '$username', role = '$role', email = '$email', first_name = '$first_name', last_name = '$last_name', dob = '$dob', phone = '$phone', department_id = " . ($department_id === NULL ? "NULL" : $department_id) . ", manager_id = " . ($manager_id === NULL ? "NULL" : $manager_id) . " WHERE id = $user_id";
    
    // Update password only if a new one is provided
    if (!empty($_POST['password'])) {
        $password = sha1($_POST['password']);
        $sql_pass = "UPDATE users SET password = '$password' WHERE id = $user_id";
        $conn->query($sql_pass);
    }

    $conn->query($sql);

    // If admin set this user as the manager for the department, update departments.manager_id if column exists
    if (is_admin() && isset($_POST['set_as_dept_manager']) && $manager_id !== NULL && $role === 'manager' && $department_id !== NULL) {
        // Check if the departments table has a manager_id column
        $colCheck = $conn->query("SHOW COLUMNS FROM departments LIKE 'manager_id'");
        if ($colCheck && $colCheck->num_rows > 0) {
            $conn->query("UPDATE departments SET manager_id = " . (int)$user_id . " WHERE id = " . (int)$department_id);
        }
    }

    header("Location: users.php");
    die();
}

// Fetch the user's current details
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// fetch departments and managers
$departments = $conn->query("SELECT id, name FROM departments");
$managers = $conn->query("SELECT id, username, department_id FROM users WHERE role = 'manager'");

include __DIR__ . '/../include/admin_header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit User</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Edit User</h1>
        <div class="text-right"><a class="btn secondary" href="users.php">Back to User List</a></div>
    </div>

    <div class="card">
    <form method="post" action="edit_user.php?id=<?php echo $user_id; ?>" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Username:</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required maxlength="64">
                </div>
                <div class="col form-group">
                    <label>Password (leave blank to keep current password):</label>
                    <input type="password" name="password" minlength="6" maxlength="128">
                </div>
            </div>

            <div class="row">
                <div class="col form-group">
                    <label>Role:</label>
                    <select name="role">
                        <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                        <option value="hr" <?php if ($user['role'] === 'hr') echo 'selected'; ?>>HR</option>
                        <option value="manager" <?php if ($user['role'] === 'manager') echo 'selected'; ?>>Manager</option>
                        <option value="employee" <?php if ($user['role'] === 'employee') echo 'selected'; ?>>Employee</option>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col form-group">
                    <label>First Name:</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                </div>
                <div class="col form-group">
                    <label>Last Name:</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                </div>
                <div class="col form-group">
                    <label>Department:</label>
                    <select name="department_id">
                        <option value="">-- None --</option>
                        <?php while ($d = $departments->fetch_assoc()) { ?>
                            <option value="<?php echo $d['id']; ?>" <?php if ($user['department_id'] == $d['id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Manager:</label>
                    <select name="manager_id" id="manager_select">
                        <option value="">-- None --</option>
                        <?php while ($m = $managers->fetch_assoc()) { ?>
                            <option value="<?php echo $m['id']; ?>" data-dept="<?php echo $m['department_id']; ?>" <?php if ($user['manager_id'] == $m['id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['username']); ?></option>
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
                    <label>Date of Birth:</label>
                    <input type="date" name="dob" value="<?php echo htmlspecialchars($user['dob']); ?>">
                </div>
                <div class="col form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" pattern="[0-9]{10}" maxlength="10" placeholder="10 digits">
                </div>
            </div>

            <div class="form-group text-right">
                <button class="btn" type="submit" name="update_user">Update User</button>
            </div>
        </form>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
<script>
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
        if (mgr.selectedOptions.length && mgr.selectedOptions[0].style.display === 'none') mgr.value = '';
    }
    dept.addEventListener('change', filter);
    filter();
});
</script>

</body>
</html>
