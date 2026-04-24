<?php
require_once '../include/db.php';
check_login();
if (!is_admin()) {
    header("Location: ../login.php");
    die();
}

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$task_id) { header('Location: tasks.php'); exit; }

// Fetch users for assignment
$users_res = $conn->query("SELECT id, username FROM users WHERE role='employee'");

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_task'])) {
    $user_id = (int)$_POST['user_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $priority = $conn->real_escape_string($_POST['priority']);
    $due_date = $conn->real_escape_string($_POST['due_date']);
    $status = $conn->real_escape_string($_POST['status']);

    $sql = "UPDATE tasks SET user_id=$user_id, title='$title', description='$description', priority='$priority', due_date=" . ($due_date ? "'$due_date'" : "NULL") . ", status='$status' WHERE id=$task_id";
    $conn->query($sql);
    header('Location: tasks.php'); exit;
}

// Fetch the task
$res = $conn->query("SELECT * FROM tasks WHERE id=$task_id LIMIT 1");
$task = $res->fetch_assoc();
if (!$task) { header('Location: tasks.php'); exit; }

include __DIR__ . '/../include/admin_header.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Task</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">Edit Task</h1>
        <div class="text-right"><a class="btn secondary" href="tasks.php">Back to Tasks</a></div>
    </div>

    <div class="card">
    <form method="post" class="form-card">
            <div class="form-group">
                <label>Assign to:</label>
                <select name="user_id" required>
                    <?php while ($u = $users_res->fetch_assoc()) { ?>
                        <option value="<?php echo $u['id']; ?>" <?php if ($u['id'] == $task['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($task['title']); ?>">
            </div>
            <div class="form-group">
                <label>Description:</label>
                <textarea name="description"><?php echo htmlspecialchars($task['description']); ?></textarea>
            </div>
            <div class="row">
                <div class="col form-group">
                    <label>Priority:</label>
                    <select name="priority">
                        <option value="low" <?php if ($task['priority'] == 'low') echo 'selected'; ?>>Low</option>
                        <option value="medium" <?php if ($task['priority'] == 'medium') echo 'selected'; ?>>Medium</option>
                        <option value="high" <?php if ($task['priority'] == 'high') echo 'selected'; ?>>High</option>
                    </select>
                </div>
                <div class="col form-group">
                    <label>Due Date:</label>
                    <input type="date" name="due_date" value="<?php echo htmlspecialchars($task['due_date']); ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Status:</label>
                <select name="status">
                    <option value="pending" <?php if ($task['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="in_progress" <?php if ($task['status'] == 'in_progress') echo 'selected'; ?>>In Progress</option>
                    <option value="completed" <?php if ($task['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                </select>
            </div>
            <div class="form-group text-right">
                <button class="btn" type="submit" name="save_task">Update Task</button>
            </div>
        </form>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>

</body>
</html>
