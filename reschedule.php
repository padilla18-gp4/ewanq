<?php
// reschedule.php - pick a new date/time for an existing appointment (same course & service)
require_once __DIR__ . "/layout.php";
require_role(['student']);

$uid = (int)$_SESSION['user_id'];
$a = db_row($conn, appt_select_sql() . " WHERE a.id = ? AND a.user_id = ?", 'ii', [(int)($_GET['id'] ?? 0), $uid]);

if (!$a) { flash('error', 'Appointment not found.'); redirect('my_appointments.php'); }
if (!(int)setting($conn, 'allow_reschedule', 1)) { flash('error', 'Rescheduling is currently disabled.'); redirect('my_appointments.php'); }
if (!student_can_modify($conn, $a)) {
    flash('error', 'This appointment can no longer be rescheduled (it is too close to the schedule or already closed).');
    redirect('my_appointments.php');
}

page_header('Reschedule Appointment', 'my_appointments.php');
?>
<div class="card">
    <h3>Current appointment</h3>
    <p><strong><?php echo e($a['reference_no']); ?></strong> — <?php echo e($a['service_name']); ?> (<?php echo e($a['course_name']); ?>)<br>
    <?php echo e(fmt_date($a['appointment_date'])); ?> at <?php echo e(fmt_time($a['appointment_time'])); ?> <?php echo badge($a['status']); ?></p>
    <p class="muted">After rescheduling, the status returns to Pending until the department confirms the new time.</p>
</div>

<div class="stepper">
    <div class="step" data-step="3"><b>1</b>New Date</div>
    <div class="step" data-step="4"><b>2</b>New Time</div>
    <div class="step" data-step="6"><b>3</b>Confirm</div>
</div>

<div class="card">
    <section class="step-panel" data-step="3">
        <h3>Choose a new date <small>for <span id="pickedService"></span></small></h3>
        <div class="msg"></div><div class="choice-grid list">Loading...</div>
        <div class="step-actions"><a class="btn btn-light" href="my_appointments.php">← Cancel</a></div>
    </section>
    <section class="step-panel" data-step="4" hidden>
        <h3>Choose a new time <small>on <span id="pickedDate"></span></small></h3>
        <div class="msg"></div><div class="slot-grid list"></div>
        <div class="step-actions"><button type="button" class="btn btn-light" data-goto="3">← Back</button></div>
    </section>
    <section class="step-panel" data-step="6" hidden>
        <h3>Confirm the change</h3>
        <div class="msg"></div>
        <dl class="dl" id="finalSummary"></dl>
        <div class="step-actions">
            <button type="button" class="btn btn-light" id="back6">← Back</button>
            <button type="button" class="btn btn-ok" id="confirmBtn">Confirm Reschedule</button>
        </div>
    </section>
</div>
<span id="pickedCourse" hidden></span><textarea id="notes" hidden></textarea><input type="checkbox" id="agree" checked hidden>

<script>
window.BOOK = {
    mode: 'reschedule',
    csrf: <?php echo json_encode(csrf_token()); ?>,
    apptId: <?php echo (int)$a['id']; ?>,
    course: {id: <?php echo (int)$a['course_id']; ?>, name: <?php echo json_encode($a['course_name']); ?>},
    service: {id: <?php echo (int)$a['service_id']; ?>, name: <?php echo json_encode($a['service_name']); ?>},
    current: <?php echo json_encode(fmt_date($a['appointment_date']) . ' at ' . fmt_time($a['appointment_time'])); ?>
};
</script>
<?php page_footer(['assets/booking.js']); ?>
