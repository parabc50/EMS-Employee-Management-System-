<?php

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ems_php_sql';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

session_start();

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        die();
    }
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_hr() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'hr';
}

function is_manager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

function manager_manages($employee_id) {
    if (!is_manager() || !isset($_SESSION['user_id'])) return false;
    global $conn;
    $mgr = (int)$_SESSION['user_id'];
    $employee_id = (int)$employee_id;
    $stmt = $conn->prepare('SELECT COUNT(*) as cnt FROM users WHERE id = ? AND manager_id = ?');
    if (!$stmt) return false;
    $stmt->bind_param('ii', $employee_id, $mgr);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($res && $res['cnt'] > 0);
}

function manager_department_id() {
    if (!is_manager() || !isset($_SESSION['user_id'])) return null;
    global $conn;
    $mgr = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT department_id FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) return null;
    $stmt->bind_param('i', $mgr);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['department_id'] ?? null;
}

function table_has_column($table, $column) {
    global $conn;
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$t' AND column_name = '$c'";
    $res = $conn->query($sql);
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return isset($row['cnt']) && (int)$row['cnt'] > 0;
}

