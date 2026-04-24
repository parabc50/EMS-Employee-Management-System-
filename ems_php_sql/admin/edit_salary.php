<?php
require_once '../include/db.php';
check_login();
if (!is_admin()) {
    header("Location: ../login.php");
    die();
}

$salary_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$salary_id) { header('Location: salaries.php'); exit; }

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salary'])) {
    $amount = (float)$_POST['amount'];
    $sql = "UPDATE salaries SET amount = $amount WHERE id = $salary_id";
    $conn->query($sql);
    header('Location: salaries.php'); exit;
}

// Fetch salary
$sql = "SELECT salaries.*, users.username FROM salaries JOIN users ON salaries.user_id = users.id WHERE salaries.id = $salary_id LIMIT 1";
$res = $conn->query($sql);
$salary = $res->fetch_assoc();
if (!$salary) { header('Location: salaries.php'); exit; }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Salary</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
    <div class="container form-card">
        <h2>Edit Salary</h2>
        <a href="salaries.php">Back to Salaries</a>
        <form method="post" class="form-card">
            <label>Employee:</label>
            <input type="text" value="<?php echo htmlspecialchars($salary['username']); ?>" disabled>
            <label>Amount:</label>
            <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($salary['amount']); ?>" required>
            <button type="submit" name="save_salary">Save</button>
        </form>
    </div>
</body>
</html>
