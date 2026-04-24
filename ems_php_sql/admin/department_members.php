<?php
require_once '../include/db.php';
check_login();
if (!is_admin()) { header('Location: ../login.php'); exit; }

$dept_id = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
if ($dept_id <= 0) { echo "<p>Invalid department.</p>"; exit; }

$dept = $conn->prepare('SELECT id, name FROM departments WHERE id = ? LIMIT 1');
$dept->bind_param('i', $dept_id);
$dept->execute();
$deptRes = $dept->get_result();
if ($deptRes->num_rows === 0) { echo "<p>Department not found.</p>"; exit; }
$deptRow = $deptRes->fetch_assoc();

$stmt = $conn->prepare('SELECT u.id,u.username,u.first_name,u.last_name,u.email,u.role FROM users u WHERE u.department_id = ? ORDER BY u.username ASC');
$stmt->bind_param('i', $dept_id);
$stmt->execute();
$users = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Department Members - <?php echo htmlspecialchars($deptRow['name']); ?></title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Members — <?php echo htmlspecialchars($deptRow['name']); ?></h1>
        <div class="text-right"><a class="btn secondary" href="departments.php">Back to Departments</a></div>
    </div>

    <div class="card section">
        <table class="table">
            <thead>
                <tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php while ($u = $users->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($u['username']); ?></td>
                        <td><?php echo htmlspecialchars(trim($u['first_name'].' '.$u['last_name'])); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><?php echo htmlspecialchars($u['role']); ?></td>
                        <td>
                            <a class="btn small" href="edit_user.php?id=<?php echo $u['id']; ?>">Edit</a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
</body>
</html>
