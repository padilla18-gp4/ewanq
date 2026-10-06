<?php
// api.php - JSON endpoints for the booking flow. Every important rule is re-checked here on the server.
define('JSON_RESPONSE', true);
require_once __DIR__ . "/lib.php";

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    json_out(['ok' => false, 'message' => 'Please log in again.']);
}

$role = $_SESSION['role'];
$uid = (int)$_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

// ---------- POST actions (students only, CSRF protected) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) json_out(['ok' => false, 'message' => 'Session expired. Please refresh the page.']);
    if ($role !== 'student') json_out(['ok' => false, 'message' => 'Unauthorized access.']);

    if ($action === 'book') {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $serviceId = (int)($_POST['service_id'] ?? 0);
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $notes = mb_substr(trim($_POST['notes'] ?? ''), 0, 500);
        if (!$courseId) json_out(['ok' => false, 'message' => 'Please select a course.']);
        if (!$serviceId) json_out(['ok' => false, 'message' => 'Please select a service.']);
        if (!$slotId) json_out(['ok' => false, 'message' => 'Please select a time slot.']);
        json_out(book_appointment($conn, $uid, $courseId, $serviceId, $slotId, $notes));
    }

    if ($action === 'reschedule') {
        $res = reschedule_appointment($conn, (int)($_POST['appointment_id'] ?? 0), $uid, (int)($_POST['slot_id'] ?? 0));
        if ($res['ok']) flash('success', $res['message']);
        json_out($res);
    }
    json_out(['ok' => false, 'message' => 'Unknown action.']);
}

// ---------- GET lookups ----------
if ($action === 'courses') {
    json_out(['ok' => true, 'courses' => db_rows($conn, "SELECT id, code, name FROM courses WHERE is_active = 1 ORDER BY name")]);
}

if ($action === 'services') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    [$av, $avT, $avP] = avail_where($conn);
    $rows = db_rows($conn, "
        SELECT sv.id, sv.name, sv.description,
          (SELECT COUNT(DISTINCT s.schedule_date) FROM schedules s JOIN time_slots t ON t.schedule_id = s.id
            WHERE s.service_id = sv.id AND s.course_id = ? AND t.current_bookings < t.capacity AND $av) AS available_dates
        FROM services sv
        WHERE sv.is_active = 1 AND (sv.course_id IS NULL OR sv.course_id = ?)
          AND EXISTS (SELECT 1 FROM courses c WHERE c.id = ? AND c.is_active = 1)
        ORDER BY sv.name", 'i' . $avT . 'ii', array_merge([$courseId], $avP, [$courseId, $courseId]));
    json_out(['ok' => true, 'services' => $rows]);
}

if ($action === 'dates') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $serviceId = (int)($_GET['service_id'] ?? 0);
    [$av, $avT, $avP] = avail_where($conn);
    $rows = db_rows($conn, "
        SELECT s.schedule_date d, SUM(GREATEST(t.capacity - t.current_bookings, 0)) remaining
        FROM schedules s
        JOIN time_slots t ON t.schedule_id = s.id
        JOIN courses c ON c.id = s.course_id AND c.is_active = 1
        JOIN services sv ON sv.id = s.service_id AND sv.is_active = 1 AND (sv.course_id IS NULL OR sv.course_id = s.course_id)
        WHERE s.course_id = ? AND s.service_id = ? AND $av
        GROUP BY s.schedule_date ORDER BY s.schedule_date", 'ii' . $avT, array_merge([$courseId, $serviceId], $avP));
    $out = [];
    foreach ($rows as $r) {
        $out[] = ['date' => $r['d'], 'label' => fmt_date($r['d']), 'weekday' => date('l', strtotime($r['d'])), 'remaining' => (int)$r['remaining']];
    }
    json_out(['ok' => true, 'dates' => $out]);
}

if ($action === 'slots') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $serviceId = (int)($_GET['service_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if (!valid_date($date)) json_out(['ok' => false, 'message' => 'Invalid date.']);
    [$av, $avT, $avP] = avail_where($conn);
    $rows = db_rows($conn, "
        SELECT t.id, t.start_time, t.capacity, t.current_bookings,
          (SELECT COUNT(*) FROM appointments a WHERE a.user_id = ? AND a.appointment_date = s.schedule_date
              AND a.appointment_time = t.start_time AND a.status <> 'cancelled') mine
        FROM time_slots t JOIN schedules s ON s.id = t.schedule_id
        WHERE s.course_id = ? AND s.service_id = ? AND s.schedule_date = ? AND $av
        ORDER BY t.start_time", 'iiis' . $avT, array_merge([$uid, $courseId, $serviceId, $date], $avP));
    $out = [];
    foreach ($rows as $r) {
        $left = max(0, (int)$r['capacity'] - (int)$r['current_bookings']);
        $out[] = ['id' => (int)$r['id'], 'label' => fmt_time($r['start_time']), 'capacity' => (int)$r['capacity'],
                  'remaining' => $left, 'full' => $left === 0, 'mine' => (int)$r['mine'] > 0];
    }
    json_out(['ok' => true, 'slots' => $out]);
}

if ($action === 'appointment') {
    $a = db_row($conn, appt_select_sql() . " WHERE a.id = ?", 'i', [(int)($_GET['id'] ?? 0)]);
    $actor = current_actor($conn);
    if (!$a || !can_view_appt($actor, $a)) json_out(['ok' => false, 'message' => 'Unauthorized access.']);

    $hist = [];
    foreach (db_rows($conn, "SELECT new_status, note, created_at FROM appointment_history WHERE appointment_id = ? ORDER BY id", 'i', [$a['id']]) as $h) {
        $hist[] = ['when' => date('M j, Y h:i A', strtotime($h['created_at'])), 'status' => STATUS_LABELS[$h['new_status']] ?? $h['new_status'], 'note' => $h['note']];
    }
    $detail = [
        'reference_no' => $a['reference_no'], 'status' => $a['status'], 'status_label' => STATUS_LABELS[$a['status']],
        'course' => $a['course_name'], 'service' => $a['service_name'],
        'date' => fmt_date($a['appointment_date']), 'time' => fmt_time($a['appointment_time']),
        'notes' => $a['notes'], 'cancel_reason' => $a['cancel_reason'], 'history' => $hist,
    ];
    if ($role !== 'student') {
        $u = db_row($conn, "SELECT full_name, student_id, email, phone FROM users WHERE id = ?", 'i', [$a['user_id']]);
        $detail['student'] = ['name' => $u['full_name'], 'student_id' => $u['student_id'], 'email' => $u['email'], 'phone' => $u['phone']];
    }
    json_out(['ok' => true, 'appointment' => $detail]);
}

json_out(['ok' => false, 'message' => 'Unknown action.']);
