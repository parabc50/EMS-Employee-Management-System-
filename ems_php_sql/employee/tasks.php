<?php
require_once '../include/db.php';
check_login();

$user_id = $_SESSION['user_id'];

// Fetch tasks for the logged-in employee using prepared statement to avoid injection
$stmt = $conn->prepare("SELECT id, title, description, priority, due_date, status FROM tasks WHERE user_id = ? ORDER BY due_date ASC");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $tasks = $stmt->get_result();
} else {
    // fallback: empty result
    $tasks = null;
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Tasks</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/user_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">My Tasks</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card">
        <table class="table">
            <thead>
            <tr>
                <th>Title</th>
                <th>Description</th>
                <th>Priority</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tasks && $tasks->num_rows > 0) {
                while ($task = $tasks->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                    <td><?php echo htmlspecialchars($task['description']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($task['priority'])); ?></td>
                    <td><?php echo htmlspecialchars($task['due_date']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_',' ', $task['status']))); ?></td>
                </tr>
            <?php }
            } else { ?>
                <tr><td colspan="5" style="text-align:center">No tasks found.</td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
