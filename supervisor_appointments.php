<?php
require_once __DIR__ . "/appointments_manager.php";
require_role(['supervisor']);

$course = staff_course($conn);
page_header('Appointments', 'supervisor_appointments.php');
if (!$course) {
    echo '<div class="alert alert-error">Your account is not assigned to a course yet.</div>';
} else {
    appointments_manager($conn, 'supervisor', $course, 'supervisor_appointments.php');
}
page_footer();
