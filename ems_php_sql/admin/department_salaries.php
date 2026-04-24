<?php
require_once '../include/db.php';
check_login();
if (!is_admin()) { header('Location: ../login.php'); exit; }

$curUid = (int)($_SESSION['user_id'] ?? 0);
$row = $conn->query("SELECT d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = $curUid")->fetch_assoc();
if (!($row && strtolower($row['dept_name'] ?? '') === 'admin')) { echo "<p>Access denied.</p>"; exit; }

$dept_id = isset($_GET['dept']) ? (int)$_GET['dept'] : 0;
if ($dept_id <= 0) {
    echo "<p>Please select a department from <a href='departments.php'>Manage Departments</a>.</p>";
    exit;
}

// Fetch salaries for department
$sql = "SELECT s.*, u.username FROM salaries s JOIN users u ON s.user_id = u.id WHERE u.department_id = ? ORDER BY s.id DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $dept_id);
$stmt->execute();
$salaries = $stmt->get_result();

$dept = $conn->query("SELECT name FROM departments WHERE id = $dept_id")->fetch_assoc();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Department Salaries</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>
    <div class="page-header">
        <h1 class="h1">Salaries — Department: <?php echo htmlspecialchars($dept['name'] ?? ''); ?></h1>
        <div class="text-right"><a class="btn secondary" href="departments.php">Back to Departments</a></div>
    </div>

    <div class="card section">
        <table class="table">
            <thead><tr><th>Employee</th><th>Amount</th><th>Status</th><th>Paid Date</th></tr></thead>
            <tbody>
            <?php while ($s = $salaries->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($s['username']); ?></td>
                    <td><?php echo htmlspecialchars($s['amount']); ?></td>
                    <td><?php echo htmlspecialchars($s['status']); ?></td>
                    <td><?php echo htmlspecialchars($s['paid_date']); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
</body>
</html>
