<?php
require_once '../include/db.php';
check_login();

$user_id = $_SESSION['user_id'];

// Fetch salary for the logged-in employee
$sql = "SELECT * FROM salaries WHERE user_id = $user_id";
$salaries = $conn->query($sql);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Salary</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/user_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">My Salary</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card">
        <table class="table">
            <thead>
            <tr>
                <th>Amount</th>
                <th>Status</th>
                <th>Paid Date</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($salary = $salaries->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($salary['amount']); ?></td>
                    <td><?php echo htmlspecialchars($salary['status']); ?></td>
                    <td><?php echo htmlspecialchars($salary['paid_date']); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
