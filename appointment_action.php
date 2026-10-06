<?php
// appointment_action.php - handles Confirm / Complete / Cancel / No Show / Decline buttons (all roles)
require_once __DIR__ . "/lib.php";
require_login();

$fallback = dashboard_for($_SESSION['role']);
$return = safe_return($_POST['return'] ?? '', $fallback);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect($fallback);

if (!csrf_verify()) {
    flash('error', 'Session expired. Please try again.');
    redirect($return);
}

$id = (int)($_POST['appointment_id'] ?? 0);
$new = $_POST['action'] ?? '';
$note = mb_substr(trim($_POST['note'] ?? ''), 0, 250);

if (!in_array($new, STATUSES, true)) {
    flash('error', 'Invalid action.');
    redirect($return);
}

$res = change_status($conn, $id, $new, current_actor($conn), $note);
flash($res['ok'] ? 'success' : 'error', $res['message']);
redirect($return);
