<?php
require_once 'include/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password_plain = $_POST['password'] ?? '';

    // Fetch user by username using a prepared statement
    $stmt = $conn->prepare('SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $user = $res->fetch_assoc();
            $stored = $user['password'];

            $authenticated = false;
            // Prefer modern password hashing if available
            if (password_verify($password_plain, $stored)) {
                $authenticated = true;
            } elseif (sha1($password_plain) === $stored) {
                // Legacy SHA1 match: rehash to a stronger algorithm
                $authenticated = true;
                $newHash = password_hash($password_plain, PASSWORD_DEFAULT);
                if ($newHash) {
                    $ustmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    if ($ustmt) { $ustmt->bind_param('si', $newHash, $user['id']); $ustmt->execute(); $ustmt->close(); }
                }
            }

            if ($authenticated) {
                // Regenerate session id to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Admins and managers go to the admin dashboard (managers will see a scoped view)
                if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: employee/dashboard.php");
                }
                exit;
            }
        }
        $stmt->close();
    }

    $error = "Invalid username or password";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMS - Login</title>
    <link rel="stylesheet" type="text/css" href="css/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
    <div class="form-card" style="width:100%;max-width:400px; padding: 40px;">
        <form method="post" action="login.php">
            <div style="text-align:center; font-size:40px; margin-bottom:10px">🔐</div>
            <h2>Sign In</h2>
            <p style="text-align:center;color:var(--muted);font-size:14px;margin-top:-12px; margin-bottom:30px">Enter your credentials to access your account</p>
            <?php if (isset($error)) { echo "<div class='error'>$error</div>"; } ?>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required placeholder="Enter your username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn" style="width:100%; padding:14px; margin-top:10px">Login</button>
            <div style="text-align:center; margin-top:20px">
                <a href="index.php" style="color:var(--muted); font-size:13px; text-decoration:none">← Back to home</a>
            </div>
        </form>
    </div>
</body>
</html>
