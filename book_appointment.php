<?php
// book_appointment.php - 6-step DFA-style booking flow (logic in assets/booking.js + api.php)
require_once __DIR__ . "/layout.php";
require_role(['student']);

page_header('Book Appointment', 'book_appointment.php');
?>
<div class="stepper">
    <div class="step" data-step="1"><b>1</b>Course</div>
    <div class="step" data-step="2"><b>2</b>Service</div>
    <div class="step" data-step="3"><b>3</b>Date</div>
    <div class="step" data-step="4"><b>4</b>Time</div>
    <div class="step" data-step="5"><b>5</b>Review</div>
    <div class="step" data-step="6"><b>6</b>Confirm</div>
</div>

<div class="card">

    <section class="step-panel" data-step="1">
        <h3>Step 1: Choose your Course / Department</h3>
        <div class="msg"></div>
        <div class="choice-grid list">Loading...</div>
    </section>

    <section class="step-panel" data-step="2" hidden>
        <h3>Step 2: Choose a Service <small>for <span id="pickedCourse"></span></small></h3>
        <div class="msg"></div>
        <div class="choice-grid list"></div>
        <div class="step-actions"><button type="button" class="btn btn-light" data-goto="1">← Back</button></div>
    </section>

    <section class="step-panel" data-step="3" hidden>
        <h3>Step 3: Choose a Date <small>for <span id="pickedService"></span></small></h3>
        <div class="msg"></div>
        <div class="choice-grid list"></div>
        <div class="step-actions"><button type="button" class="btn btn-light" data-goto="2">← Back</button></div>
    </section>

    <section class="step-panel" data-step="4" hidden>
        <h3>Step 4: Choose a Time Slot <small>on <span id="pickedDate"></span></small></h3>
        <div class="msg"></div>
        <div class="slot-grid list"></div>
        <div class="step-actions"><button type="button" class="btn btn-light" data-goto="3">← Back</button></div>
    </section>

    <section class="step-panel" data-step="5" hidden>
        <h3>Step 5: Review your Information</h3>
        <dl class="dl" id="reviewSummary"></dl>
        <label style="margin-top:14px">Purpose / notes (optional)
            <textarea id="notes" rows="3" maxlength="500" placeholder="Tell the department what your appointment is about"></textarea></label>
        <div class="step-actions">
            <button type="button" class="btn btn-light" data-goto="4">← Back</button>
            <button type="button" class="btn btn-primary" data-goto="6">Continue →</button>
        </div>
    </section>

    <section class="step-panel" data-step="6" hidden>
        <h3>Step 6: Confirm your Appointment</h3>
        <div class="msg"></div>
        <dl class="dl" id="finalSummary"></dl>
        <p class="muted" style="margin-top:12px">Your appointment will be <strong>Pending</strong> until the department confirms it. You can cancel or reschedule from “My Appointments”.</p>
        <label class="check" style="margin-top:12px"><input type="checkbox" id="agree"> I confirm that the information above is correct.</label>
        <div class="step-actions">
            <button type="button" class="btn btn-light" id="back6">← Back</button>
            <button type="button" class="btn btn-ok" id="confirmBtn">Confirm Appointment</button>
        </div>
    </section>

</div>

<script>
window.BOOK = {
    mode: 'book',
    csrf: <?php echo json_encode(csrf_token()); ?>,
    studentName: <?php echo json_encode($_SESSION['full_name']); ?>,
    studentId: <?php echo json_encode($_SESSION['studentID']); ?>
};
</script>
<?php page_footer(['assets/booking.js']); ?>
