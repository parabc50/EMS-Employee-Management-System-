<?php
require_once 'include/db.php';
check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$notif_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($notif_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid id']);
    exit;
}

// Ensure the notification belongs to the logged-in user
$user_id = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
if ($stmt) {
    $stmt->bind_param('ii', $notif_id, $user_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['success' => (bool)$affected]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
