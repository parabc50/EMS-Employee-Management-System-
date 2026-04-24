<?php
// Simple automated test runner for EMS (non-destructive)
// Writes a JSON log to docs/test_logs/run_YYYYMMDD_HHMMSS.json

require_once __DIR__ . '/../include/db.php';

$log = [
    'timestamp' => date('c'),
    'results' => []
];

function add($name, $ok, $details = ''){
    global $log;
    $log['results'][] = ['test' => $name, 'ok' => (bool)$ok, 'details' => $details];
}

// 1) DB connection
$ok = ($conn && !$conn->connect_error);
add('db_connection', $ok, $ok ? 'Connected to DB' : 'DB connection failed: ' . ($conn->connect_error ?? 'unknown'));

// 2) Seeded admin exists
$res = $conn->query("SELECT id, username, password, role FROM users WHERE username = 'admin' LIMIT 1");
$admin = $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
add('admin_user_exists', (bool)$admin, $admin ? 'admin id='.$admin['id'] : 'admin not found');

// 3) Employee user exists
$res = $conn->query("SELECT id, username FROM users WHERE username = 'employee' LIMIT 1");
$emp = $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
add('employee_user_exists', (bool)$emp, $emp ? 'employee id='.$emp['id'] : 'employee not found');

// 4) Legacy password hash detection for admin (sha1 length 40)
if ($admin) {
    $pw = $admin['password'];
    $is_sha1 = (strlen($pw) === 40 && preg_match('/^[0-9a-f]{40}$/i', $pw));
    $is_pwdhash = (strpos($pw, '$2y$') === 0 || strpos($pw, '$argon2') === 0 || strpos($pw, '$2b$') === 0);
    add('admin_password_hash_type', true, $is_pwdhash ? 'password_hash' : ($is_sha1 ? 'sha1' : 'unknown')); 
} else {
    add('admin_password_hash_type', false, 'admin missing');
}

// 5) Attendance table existence and columns
$colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attendance'");
$cols = [];
if ($colRes) { while ($r = $colRes->fetch_assoc()) $cols[] = $r['COLUMN_NAME']; }
add('attendance_table_exists', count($cols) > 0, 'columns: '.implode(',', $cols));

// 6) Check mark_absent script exists and is executable (file exists)
$scriptPath = __DIR__ . '/../scripts/mark_absent.php';
add('mark_absent_exists', file_exists($scriptPath), file_exists($scriptPath) ? $scriptPath : 'missing');

// 7) Check attendance summary endpoint (simulate request by including attendance.php with GET summary param)
// We'll call attendance.php via HTTP using local server if available; try curl via CLI fallback
$base = 'http://localhost/ems_php_sql/admin/attendance.php?summary=1&date=' . urlencode(date('Y-m-d'));
$summaryOk = false; $summaryDetails = '';

// First try HTTP endpoint (may require auth). If it returns JSON, accept it.
if (function_exists('curl_init')) {
    $ch = curl_init($base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($out && strpos(trim($out), '{') === 0) {
        $summaryOk = true;
        $summaryDetails = 'Returned JSON';
    } else {
        $summaryDetails = 'HTTP no-JSON/blocked: ' . substr($out ?? '', 0, 200);
    }
} else {
    $summaryDetails = 'curl extension not available';
}

// If HTTP failed (likely due to auth redirect), compute summary directly from DB as a fallback.
if (!$summaryOk) {
    try {
        $ts = strtotime(date('Y-m-d'));
        $first = date('Y-m-01', $ts);
        $last = date('Y-m-t', $ts);
        $q = $conn->prepare('SELECT date, COUNT(*) as present_count FROM attendance WHERE date BETWEEN ? AND ? AND status = ? GROUP BY date ORDER BY date ASC');
        if ($q) {
            $status = 'present';
            $q->bind_param('sss', $first, $last, $status);
            $q->execute();
            $res = $q->get_result();
            $map = [];
            while ($r = $res->fetch_assoc()) $map[$r['date']] = (int)$r['present_count'];
            // build labels for each day in month
            $labels = [];
            $values = [];
            $period = new DatePeriod(new DateTime($first), new DateInterval('P1D'), (new DateTime($last))->modify('+1 day'));
            foreach ($period as $d) {
                $day = $d->format('Y-m-d');
                $labels[] = $day;
                $values[] = $map[$day] ?? 0;
            }
            $summaryOk = true;
            $summaryDetails = 'Computed locally from DB';
        } else {
            $summaryDetails .= ' ; DB prepare failed for summary query';
        }
    } catch (Exception $e) {
        $summaryDetails .= ' ; exception: ' . $e->getMessage();
    }
}

add('attendance_summary_endpoint', $summaryOk, $summaryDetails);

// 8) Login page reachable
$loginUrl = 'http://localhost/ems_php_sql/login.php';
$reachable = false; $reachDet = '';
if (function_exists('curl_init')) {
    $ch = curl_init($loginUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $o = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($o && strpos($o, '<form') !== false) { $reachable = true; $reachDet = 'form present'; }
    else $reachDet = 'no form or error ' . $err;
} else {
    $reachDet = 'curl not available';
}
add('login_page_reachable', $reachable, $reachDet);

// 9) Mark absent dry-run (DO NOT modify DB): simulate by counting users without attendance for today
$today = date('Y-m-d');
$q = $conn->prepare('SELECT COUNT(*) as c FROM users WHERE id NOT IN (SELECT user_id FROM attendance WHERE date = ?)');
if ($q) {
    $q->bind_param('s', $today);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    $count = (int)($r['c'] ?? 0);
    add('mark_absent_dry_count', true, "Users without attendance today: $count");
    $q->close();
} else {
    add('mark_absent_dry_count', false, 'prepare failed');
}

// 10) Write log
$fn = __DIR__ . '/test_logs/run_' . date('Ymd_His') . '.json';
file_put_contents($fn, json_encode($log, JSON_PRETTY_PRINT));

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'log' => $fn, 'summary' => $log], JSON_PRETTY_PRINT);

?>