<?php
require_once __DIR__ . "/schedules_manager.php";
require_role(['supervisor']);

$course = staff_course($conn);
if ($course) schedules_handle_post($conn, $course, 'supervisor_schedules.php');

page_header('Manage Schedules', 'supervisor_schedules.php');
if (!$course) {
    echo '<div class="alert alert-error">Your account is not assigned to a course yet.</div>';
} else {
    schedules_render($conn, $course, 'supervisor_schedules.php');
}
page_footer();
