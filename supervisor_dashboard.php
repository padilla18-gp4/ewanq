<?php
// supervisor_dashboard.php - supervisor home (only their own course/department)
require_once __DIR__ . "/layout.php";
require_role(['supervisor']);

$course = staff_course($conn);
if (!$course) {
    page_header('Dashboard', 'supervisor_dashboard.php');
    echo '<div class="alert alert-error">Your account is not assigned to a course yet. Please contact the administrator.</div>';
    page_footer();
    exit;
}
$courseName = db_val($conn, "SELECT name FROM courses WHERE id = ?", 'i', [$course]);
$today = date('Y-m-d');

$st = db_row($conn, "SELECT
    SUM(appointment_date = ? AND status <> 'cancelled') today,
    SUM(status = 'pending') pending,
    SUM(status = 'confirmed' AND appointment_date >= ?) upcoming,
    SUM(status = 'completed') completed
    FROM appointments WHERE course_id = ?", 'ssi', [$today, $today, $course]);

$todayRows = db_rows($conn, appt_select_sql() . " WHERE a.course_id = ? AND a.appointment_date = ? ORDER BY a.appointment_time", 'is', [$course, $today]);
$pendingRows = db_rows($conn, appt_select_sql() . " WHERE a.course_id = ? AND a.status = 'pending' AND a.appointment_date >= ? ORDER BY a.appointment_date, a.appointment_time LIMIT 10", 'is', [$course, $today]);

page_header('Supervisor Dashboard', 'supervisor_dashboard.php');
?>
<div class="card welcome">
    <div class="avatar"><?php echo e(mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1))); ?></div>
    <div><small>Department / Course</small><h2 style="color:#062d58"><?php echo e($courseName); ?></h2>
    <small><?php echo e(fmt_date($today)); ?></small></div>
</div>

<div class="stats">
    <div class="stat s-blue"><b><?php echo (int)$st['today']; ?></b><span>Today's Appointments</span></div>
    <div class="stat s-yellow"><b><?php echo (int)$st['pending']; ?></b><span>Pending Requests</span></div>
    <div class="stat s-green"><b><?php echo (int)$st['upcoming']; ?></b><span>Upcoming Confirmed</span></div>
    <div class="stat s-purple"><b><?php echo (int)$st['completed']; ?></b><span>Completed</span></div>
</div>

<div class="card"><h3>Today's Appointments</h3>
    <?php render_appt_table($todayRows, 'supervisor', 'supervisor_dashboard.php'); ?></div>

<div class="card"><h3>Pending Requests (needs your action)</h3>
    <?php render_appt_table($pendingRows, 'supervisor', 'supervisor_dashboard.php'); ?>
    <p style="margin-top:10px"><a href="supervisor_appointments.php">See all appointments →</a></p></div>
<?php page_footer(); ?>
