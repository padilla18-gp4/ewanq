<?php
// my_appointments.php - the student's active (pending/confirmed, not yet past) appointments
require_once __DIR__ . "/layout.php";
require_role(['student']);

$uid = (int)$_SESSION['user_id'];
$rows = db_rows($conn, appt_select_sql() . " WHERE a.user_id = ? AND a.status IN ('pending','confirmed') AND a.appointment_date >= ?
    ORDER BY a.appointment_date, a.appointment_time", 'is', [$uid, date('Y-m-d')]);
$allowResched = (int)setting($conn, 'allow_reschedule', 1);
$hours = (int)setting($conn, 'cancel_cutoff_hours', 24);

page_header('My Appointments', 'my_appointments.php');
?>
<div class="toolbar"><a class="btn btn-primary" href="book_appointment.php">＋ Book Appointment</a>
<a class="btn btn-light" href="appointment_history.php">View History</a></div>

<div class="card">
    <p class="muted">You can cancel or reschedule up to <?php echo $hours; ?> hour(s) before your appointment.</p>
    <?php if (!$rows): ?>
        <div class="empty">You have no active appointments.</div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reference</th><th>Course</th><th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?php echo e($r['reference_no']); ?></strong></td>
                <td><?php echo e($r['course_name']); ?></td>
                <td><?php echo e($r['service_name']); ?></td>
                <td><?php echo e(fmt_date($r['appointment_date'])); ?></td>
                <td><?php echo e(fmt_time($r['appointment_time'])); ?></td>
                <td><?php echo badge($r['status']); ?></td>
                <td class="actions">
                    <?php echo appt_actions($r, 'student', 'my_appointments.php'); ?>
                    <?php if ($allowResched && student_can_modify($conn, $r)): ?>
                        <a class="btn btn-sm btn-gold" href="reschedule.php?id=<?php echo (int)$r['id']; ?>">Reschedule</a>
                    <?php endif; ?>
                    <a class="btn btn-sm btn-light" href="appointment_view.php?ref=<?php echo urlencode($r['reference_no']); ?>">Print</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
