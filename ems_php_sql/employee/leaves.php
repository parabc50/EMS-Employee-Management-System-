<?php
require_once '../include/db.php';
check_login();

$user_id = $_SESSION['user_id'];

// Handle form submission (apply for leave)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Apply for a new leave
    if (isset($_POST['apply_leave'])) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $reason = $_POST['reason'];

        $stmt = $conn->prepare("INSERT INTO leaves (user_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('isss', $user_id, $start_date, $end_date, $reason);
            $stmt->execute();
            $stmt->close();
        }

        // Redirect to avoid form resubmission and show the updated list
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // Delete a pending leave request (only allowed for owner and pending status)
    if (isset($_POST['delete_leave'])) {
        $delete_id = (int)$_POST['delete_leave'];
        $del = $conn->prepare("DELETE FROM leaves WHERE id = ? AND user_id = ? AND status = 'pending'");
        if ($del) {
            $del->bind_param('ii', $delete_id, $user_id);
            $del->execute();
            $del->close();
        }
        // Redirect back to avoid resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

}

// Fetch all leaves for the logged-in employee (include pending)
$leaves = null;
$stmt = $conn->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY start_date DESC");
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        $leaves = $res; // mysqli_result usable with fetch_assoc()
    }
    $stmt->close();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Leaves</title>
    <link rel="stylesheet" type="text/css" href="../css/style.css">
</head>
<body>
<?php include __DIR__ . '/../include/user_header.php'; ?>

    <div class="page-header">
        <h1 class="h1">My Leaves</h1>
        <div class="text-right"><a class="btn secondary" href="dashboard.php">Back to Dashboard</a></div>
    </div>

    <div class="card section">
        <h3 class="small-muted">Apply for Leave</h3>
    <form method="post" class="form-card">
            <div class="row">
                <div class="col form-group">
                    <label>Start Date:</label>
                    <input type="date" name="start_date" required>
                </div>
                <div class="col form-group">
                    <label>End Date:</label>
                    <input type="date" name="end_date" required>
                </div>
            </div>
            <div class="form-group">
                <label>Reason:</label>
                <textarea name="reason"></textarea>
            </div>
            <div class="form-group text-right">
                <button class="btn" type="submit" name="apply_leave">Apply</button>
            </div>
        </form>
    </div>

    <div class="card section">
        <h3 class="small-muted">My Leave History</h3>
        <table class="table">
            <thead>
            <tr>
                <th>Start Date</th>
                <th>End Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($leave = $leaves->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($leave['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($leave['end_date']); ?></td>
                    <td><?php echo htmlspecialchars($leave['reason']); ?></td>
                    <td><?php echo htmlspecialchars($leave['status']); ?></td>
                    <td>
                        <?php if ($leave['status'] === 'pending') : ?>
                            <form method="post" style="display:inline" onsubmit="return confirm('Delete this leave request?');">
                                <input type="hidden" name="delete_leave" value="<?php echo (int)$leave['id']; ?>">
                                <button class="btn danger small" type="submit">Delete</button>
                            </form>
                        <?php else: ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>

<?php include __DIR__ . '/../include/admin_footer.php'; ?>
