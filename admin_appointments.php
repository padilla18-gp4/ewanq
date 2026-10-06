<?php
require_once __DIR__ . "/appointments_manager.php";
require_role(['admin']);

page_header('All Appointments', 'admin_appointments.php');
appointments_manager($conn, 'admin', null, 'admin_appointments.php');
page_footer();
