<?php
// admin_dashboard.php
require_once __DIR__ . "/layout.php";
require_role(['admin']);

$today = date('Y-m-d');
$students = (int)db_val($conn, "SELECT COUNT(*) FROM users WHERE role = 'student'");
$supers = (int)db_val($conn, "SELECT COUNT(*) FROM users WHERE role = 'supervisor'");
$st = db_row($conn, "SELECT SUM(appointment_date = ?) today, SUM(status='pending') pending, SUM(status='confirmed') confirmed,
    SUM(status='completed') completed, SUM(status='cancelled') cancelled, SUM(status='no_show') no_show FROM appointments", 's', [$today]);

$todayRows = db_rows($conn, appt_select_sql() . " WHERE a.appointment_date = ? ORDER BY a.appointment_time", 's', [$today]);
$recent = db_rows($conn, appt_select_sql() . " ORDER BY a.id DESC LIMIT 8");

page_header('Admin Dashboard', 'admin_dashboard.php');
?>
<div class="stats">
    <div class="stat s-blue"><b><?php echo $students; ?></b><span>Total Students</span></div>
    <div class="stat s-purple"><b><?php echo $supers; ?></b><span>Total Supervisors</span></div>
    <div class="stat s-gray"><b><?php echo (int)$st['today']; ?></b><span>Today's Appointments</span></div>
    <div class="stat s-yellow"><b><?php echo (int)$st['pending']; ?></b><span>Pending</span></div>
    <div class="stat s-green"><b><?php echo (int)$st['confirmed']; ?></b><span>Confirmed</span></div>
    <div class="stat s-blue"><b><?php echo (int)$st['completed']; ?></b><span>Completed</span></div>
    <div class="stat s-red"><b><?php echo (int)$st['cancelled']; ?></b><span>Cancelled</span></div>
    <div class="stat s-gray"><b><?php echo (int)$st['no_show']; ?></b><span>No Shows</span></div>
</div>

<div class="card"><h3>Today's Appointments</h3>
    <?php render_appt_table($todayRows, 'admin', 'admin_dashboard.php', true); ?></div>

<div class="card"><h3>Latest Bookings</h3>
    <?php render_appt_table($recent, 'admin', 'admin_dashboard.php', true); ?>
    <p style="margin-top:10px"><a href="admin_appointments.php">Search & filter all appointments →</a></p></div>
<?php page_footer(); ?>
