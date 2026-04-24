<?php
require_once '../include/db.php';
check_login();
if (!is_admin() && !is_manager()) { header('Location: ../login.php'); die(); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv'])) {
    $tmp = $_FILES['csv']['tmp_name'];
    if (!is_uploaded_file($tmp)) { $errors[] = 'Upload failed'; }
    else {
        $fh = fopen($tmp,'r');
        $hdr = fgetcsv($fh);
        // expected columns include at minimum: date,username,status (others optional)
        while ($row = fgetcsv($fh)) {
            if (!$hdr) continue;
            $data = count($hdr) === count($row) ? array_combine($hdr, $row) : null;
            if (!$data) {
                // fallback: try to map by position if common layout
                $data = [];
                $data['date'] = $row[0] ?? null;
                $data['username'] = $row[1] ?? null;
                $data['clock_in'] = $row[4] ?? null;
                $data['clock_out'] = $row[5] ?? null;
                $data['status'] = $row[6] ?? null;
                $data['notes'] = $row[7] ?? null;
            }
            // find user by username
            $uname = $data['username'] ?? null;
            $u = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $u->bind_param('s', $uname);
            $u->execute(); $res = $u->get_result(); $ur = $res ? $res->fetch_assoc() : null; $u->close();
            if (!$ur) { $errors[] = "Unknown user: $uname"; continue; }
            $uid = (int)$ur['id'];
            $date = $data['date'] ?? date('Y-m-d');
            $clock_in = $data['clock_in'] ?: null;
            $clock_out = $data['clock_out'] ?: null;
            $status = in_array($data['status'] ?? 'present',['present','absent','on_leave','leave']) ? $data['status'] : 'present';
            $notes = $data['notes'] ?? ($data['note'] ?? null);
            $has_clock = table_has_column('attendance','clock_in') && table_has_column('attendance','clock_out');
            if ($has_clock) {
                $ins = $conn->prepare('INSERT INTO attendance (user_id, date, clock_in, clock_out, status, notes) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE clock_in=VALUES(clock_in), clock_out=VALUES(clock_out), status=VALUES(status), notes=VALUES(notes)');
                $ins->bind_param('isssss',$uid,$date,$clock_in,$clock_out,$status,$notes);
            } else {
                $ins = $conn->prepare('INSERT INTO attendance (user_id, date, status, note) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note)');
                $ins->bind_param('isss',$uid,$date,$status,$notes);
            }
            $ins->execute(); $ins->close();
        }
        fclose($fh);
    }
}

?>
<!doctype html><html><head><meta charset="utf-8"><title>Import Attendance</title><link rel="stylesheet" href="../css/style.css"></head><body>
<?php include __DIR__ . '/../include/admin_header.php'; ?>
<div class="container"><div class="card"><h3 class="small-muted">Import Attendance CSV</h3>
<?php if (count($errors)>0): ?><div class="card"><ul><?php foreach($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <label>CSV File: <input type="file" name="csv" accept=".csv"></label>
    <p><button class="btn">Upload</button> <a class="btn ghost" href="attendance.php">Back</a></p>
</form>
<p class="muted">Expected columns: date,username,first_name,last_name,clock_in,clock_out,status,notes</p>
</div></div>
<?php include __DIR__ . '/../include/admin_footer.php'; ?></body></html>