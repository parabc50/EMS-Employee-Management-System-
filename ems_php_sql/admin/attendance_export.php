<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_manager()) { header('Location: ../login.php'); die(); }

$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build query similar to attendance.php
$has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
if ($has_clock) {
    $sql = 'SELECT a.date, u.username, u.first_name, u.last_name, a.clock_in, a.clock_out, a.status, a.notes FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ?';
} else {
    $sql = 'SELECT a.date, u.username, u.first_name, u.last_name, NULL as clock_in, NULL as clock_out, a.status, a.note as notes FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = ?';
}
$params = [$filter_date]; $types='s';
if ($filter_user>0) { $sql .= ' AND a.user_id = ?'; $types.='i'; $params[] = $filter_user; }
if (is_manager()) { $dept = manager_department_id(); $sql .= ' AND u.department_id = ?'; $types.='i'; $params[] = $dept; }
$sql .= ' ORDER BY u.username ASC';

$stmt = $conn->prepare($sql);
if ($stmt) {
    $bind_names = [$types];
    for ($i=0;$i<count($params);$i++) { $bn='b'.$i; $$bn = $params[$i]; $bind_names[] = &$$bn; }
    call_user_func_array([$stmt,'bind_param'],$bind_names);
    $stmt->execute();
    $res = $stmt->get_result();
} else { $res = null; }

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="attendance_'.urlencode($filter_date).'.csv"');
$out = fopen('php://output','w');
// write UTF-8 BOM so Excel recognizes UTF-8
fwrite($out, "\xEF\xBB\xBF");
// helper to format datetime from stored timezone to app timezone
$stored_tz = defined('STORED_TIMEZONE') ? STORED_TIMEZONE : 'UTC';
$app_tz = date_default_timezone_get();
function fmt_dt($val, $stored_tz, $app_tz) {
    if (empty($val)) return '';
    try {
        $d = new DateTime($val, new DateTimeZone($stored_tz));
        $d->setTimezone(new DateTimeZone($app_tz));
        return $d->format('Y-m-d H:i:s');
    } catch (Exception $e) { return $val; }
}
// header depends on schema
if ($has_clock) {
    fputcsv($out, ['date','username','first_name','last_name','clock_in','clock_out','status','notes']);
    if ($res) while ($r = $res->fetch_assoc()) {
        $cin = fmt_dt($r['clock_in'], $stored_tz, $app_tz);
        $cout = fmt_dt($r['clock_out'], $stored_tz, $app_tz);
        // date may be a plain date, keep as-is
        fputcsv($out, [$r['date'],$r['username'],$r['first_name'],$r['last_name'],$cin,$cout,$r['status'],$r['notes']]);
    }
} else {
    fputcsv($out, ['date','username','first_name','last_name','status','note']);
    if ($res) while ($r = $res->fetch_assoc()) {
        fputcsv($out, [$r['date'],$r['username'],$r['first_name'],$r['last_name'],$r['status'],$r['notes']]);
    }
}
fclose($out);
exit;