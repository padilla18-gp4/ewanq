<?php
require_once __DIR__ . "/schedules_manager.php";
require_role(['admin']);

schedules_handle_post($conn, null, 'admin_schedules.php');

page_header('Manage Schedules', 'admin_schedules.php');
schedules_render($conn, null, 'admin_schedules.php');
page_footer();
