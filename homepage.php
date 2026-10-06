<?php
// homepage.php - STUDENT DASHBOARD
require_once __DIR__ . "/layout.php";
require_role(['student']);

$uid = (int)$_SESSION['user_id'];
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'mark_read') {
    db_exec($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?", 'i', [$uid]);
    redirect('homepage.php');
}

$st = db_row($conn, "SELECT
    SUM(status IN ('pending','confirmed') AND appointment_date >= ?) upcoming,
    SUM(status = 'pending') pending, SUM(status = 'completed') completed, SUM(status = 'cancelled') cancelled
    FROM appointments WHERE user_id = ?", 'si', [$today, $uid]);

$next = db_row($conn, appt_select_sql() . " WHERE a.user_id = ? AND a.status IN ('pending','confirmed') AND a.appointment_date >= ?
    ORDER BY a.appointment_date, a.appointment_time LIMIT 1", 'is', [$uid, $today]);

$course = db_val($conn, "SELECT c.name FROM users u LEFT JOIN courses c ON c.id = u.course_id WHERE u.id = ?", 'i', [$uid]);
$notifs = db_rows($conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6", 'i', [$uid]);

page_header('Dashboard', 'homepage.php');
?>
<div class="card welcome">
    <div class="avatar"><?php echo e(mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1))); ?></div>
    <div>
        <small>Welcome back,</small>
        <h2 style="color:#062d58"><?php echo e($_SESSION['full_name']); ?>!</h2>
        <small>Student ID: <?php echo e($_SESSION['studentID']); ?><?php echo $course ? ' &nbsp;|&nbsp; ' . e($course) : ' &nbsp;|&nbsp; <a href="profile.php">Set your course in Profile</a>'; ?></small>
    </div>
    <div style="margin-left:auto"><a class="btn btn-primary" href="book_appointment.php">＋ Book Appointment</a></div>
</div>

<div class="stats">
    <div class="stat s-blue"><b><?php echo (int)$st['upcoming']; ?></b><span>Upcoming Appointments</span></div>
    <div class="stat s-yellow"><b><?php echo (int)$st['pending']; ?></b><span>Pending Appointments</span></div>
    <div class="stat s-green"><b><?php echo (int)$st['completed']; ?></b><span>Completed Appointments</span></div>
    <div class="stat s-red"><b><?php echo (int)$st['cancelled']; ?></b><span>Cancelled Appointments</span></div>
</div>

<div class="two-col">
    <div class="card">
        <h3>Next Appointment</h3>
        <?php if ($next): ?>
            <dl class="dl">
                <dt>Reference</dt><dd><strong><?php echo e($next['reference_no']); ?></strong></dd>
                <dt>Course</dt><dd><?php echo e($next['course_name']); ?></dd>
                <dt>Service</dt><dd><?php echo e($next['service_name']); ?></dd>
                <dt>Date</dt><dd><?php echo e(fmt_date($next['appointment_date'])); ?></dd>
                <dt>Time</dt><dd><?php echo e(fmt_time($next['appointment_time'])); ?></dd>
                <dt>Status</dt><dd><?php echo badge($next['status']); ?></dd>
            </dl>
            <div class="toolbar" style="margin-top:14px">
                <a class="btn btn-light" href="appointment_view.php?ref=<?php echo urlencode($next['reference_no']); ?>">View / Print</a>
                <a class="btn btn-primary" href="my_appointments.php">My Appointments</a>
            </div>
        <?php else: ?>
            <div class="empty">You have no upcoming appointments.<br><br><a class="btn btn-primary" href="book_appointment.php">Book one now</a></div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Notifications</h3>
        <?php if (!$notifs): ?><div class="empty">No notifications yet.</div><?php endif; ?>
        <?php foreach ($notifs as $n): ?>
            <div class="notif <?php echo $n['is_read'] ? '' : 'new'; ?>"><?php echo e($n['message']); ?><br><small><?php echo e(date('M j, h:i A', strtotime($n['created_at']))); ?></small></div>
        <?php endforeach; ?>
        <?php if ($notifs): ?>
            <form method="post" style="margin-top:10px"><?php echo csrf_field(); ?><input type="hidden" name="action" value="mark_read"><button class="btn btn-sm btn-light">Mark all as read</button></form>
        <?php endif; ?>
    </div>
</div>
<?php page_footer(); ?>
