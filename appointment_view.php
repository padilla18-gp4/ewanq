<?php
// appointment_view.php - printable appointment slip (all roles, with permission check)
require_once __DIR__ . "/layout.php";
require_login();

$actor = current_actor($conn);
$a = db_row($conn, appt_select_sql() . " WHERE a.reference_no = ?", 's', [$_GET['ref'] ?? '']);
if (!$a || !can_view_appt($actor, $a)) {
    flash('error', 'Unauthorized access.');
    redirect(dashboard_for($_SESSION['role']));
}
$u = db_row($conn, "SELECT email, phone FROM users WHERE id = ?", 'i', [$a['user_id']]);

page_header('Appointment Details', $_SESSION['role'] === 'student' ? 'my_appointments.php' : '');
?>
<div class="slip">
    <?php if (!empty($_GET['new'])): ?>
        <div class="success-box"><h2 style="color:#14532d">Appointment Successfully Booked!</h2>
        <p>Please keep your reference number.</p></div>
    <?php endif; ?>

    <div class="card">
        <p class="muted">Reference Number</p>
        <div class="ref"><?php echo e($a['reference_no']); ?></div>
        <hr style="margin:14px 0;border:0;border-top:1px solid #e2e8f0">
        <dl class="dl">
            <dt>Student</dt><dd><?php echo e($a['full_name']); ?> (<?php echo e($a['student_id']); ?>)</dd>
            <dt>Email</dt><dd><?php echo e($u['email']); ?></dd>
            <dt>Phone</dt><dd><?php echo e($u['phone']); ?></dd>
            <dt>Course</dt><dd><?php echo e($a['course_name']); ?></dd>
            <dt>Service</dt><dd><?php echo e($a['service_name']); ?></dd>
            <dt>Date</dt><dd><?php echo e(fmt_date($a['appointment_date'])); ?></dd>
            <dt>Time</dt><dd><?php echo e(fmt_time($a['appointment_time'])); ?></dd>
            <dt>Status</dt><dd><?php echo badge($a['status']); ?></dd>
            <?php if ($a['notes']): ?><dt>Notes</dt><dd><?php echo e($a['notes']); ?></dd><?php endif; ?>
            <?php if ($a['cancel_reason']): ?><dt>Cancel reason</dt><dd><?php echo e($a['cancel_reason']); ?></dd><?php endif; ?>
            <dt>Booked on</dt><dd><?php echo e(date('F j, Y h:i A', strtotime($a['created_at']))); ?></dd>
        </dl>
        <p class="muted" style="margin-top:14px">Please present this reference number at the department on your appointment date.</p>
    </div>

    <div class="toolbar no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨 Print / Save as PDF</button>
        <a class="btn btn-light" href="<?php echo e($_SESSION['role'] === 'student' ? 'my_appointments.php' : dashboard_for($_SESSION['role'])); ?>">Back</a>
        <?php if ($_SESSION['role'] === 'student'): ?>
            <?php echo appt_actions($a, 'student', 'appointment_view.php?ref=' . urlencode($a['reference_no'])); ?>
        <?php endif; ?>
    </div>
</div>
<?php page_footer(); ?>
