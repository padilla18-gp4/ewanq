<?php
// appointment_history.php - all of the student's appointments (any status), paginated
require_once __DIR__ . "/layout.php";
require_role(['student']);

$uid = (int)$_SESSION['user_id'];
$status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 10;

$w = 'a.user_id = ?'; $t = 'i'; $p = [$uid];
if (isset(STATUS_LABELS[$status])) { $w .= ' AND a.status = ?'; $t .= 's'; $p[] = $status; }

$total = (int)db_val($conn, "SELECT COUNT(*) FROM appointments a WHERE $w", $t, $p);
$rows = db_rows($conn, appt_select_sql() . " WHERE $w ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT $per OFFSET " . (($page - 1) * $per), $t, $p);

page_header('Appointment History', 'appointment_history.php');
?>
<form method="get" class="filters card">
    <select name="status"><option value="">All statuses</option>
        <?php foreach (STATUS_LABELS as $k => $l): ?><option value="<?php echo e($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Filter</button>
    <a class="btn btn-light" href="appointment_history.php">Reset</a>
</form>

<div class="card">
    <?php if (!$rows): ?><div class="empty">No appointments found.</div><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Course</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?php echo e($r['reference_no']); ?></strong></td>
                <td><?php echo e($r['course_name']); ?></td>
                <td><?php echo e($r['service_name']); ?></td>
                <td><?php echo e(fmt_date($r['appointment_date'])); ?></td>
                <td><?php echo e(fmt_time($r['appointment_time'])); ?></td>
                <td><?php echo badge($r['status']); ?></td>
                <td><button type="button" class="btn btn-sm btn-light" data-view-appt="<?php echo (int)$r['id']; ?>">View</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php echo pager($total, $page, $per); endif; ?>
</div>
<?php page_footer(); ?>
