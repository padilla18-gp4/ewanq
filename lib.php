<?php
// lib.php - core helpers: DB shortcuts, booking engine, status rules. Included by every app page.

require_once __DIR__ . "/auth.php";

date_default_timezone_set('Asia/Manila');

const APP_DEBUG = true;   // set to false when your project is finished (hides technical error text)

class UserError extends Exception {}

set_exception_handler(function (Throwable $e) {
    error_log((string)$e);
    $msg = APP_DEBUG ? $e->getMessage() : "Something went wrong. Please try again.";
    http_response_code(500);
    if (defined('JSON_RESPONSE')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => $msg]);
    } else {
        echo '<!DOCTYPE html><html><body style="font-family:Arial;padding:40px"><h2>Oops!</h2><p>'
            . htmlspecialchars($msg) . '</p><p><a href="javascript:history.back()">Go back</a></p></body></html>';
    }
    exit;
});

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . "/database.php";     // creates $conn
$conn->set_charset('utf8mb4');

// ======================= DB SHORTCUTS =======================
function db_exec(mysqli $c, string $sql, string $types = '', array $p = []): mysqli_stmt
{
    $st = $c->prepare($sql);
    if ($types !== '') {
        $st->bind_param($types, ...$p);
    }
    $st->execute();
    return $st;
}

function db_rows(mysqli $c, string $sql, string $types = '', array $p = []): array
{
    $st = db_exec($c, $sql, $types, $p);
    $r = $st->get_result();
    $out = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $out;
}

function db_row(mysqli $c, string $sql, string $types = '', array $p = []): ?array
{
    $r = db_rows($c, $sql, $types, $p);
    return $r[0] ?? null;
}

function db_val(mysqli $c, string $sql, string $types = '', array $p = [])
{
    $r = db_row($c, $sql, $types, $p);
    return $r ? array_values($r)[0] : null;
}

// ======================= GENERAL HELPERS =======================
function json_out(array $data): void
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['t' => $type, 'm' => $msg];
}

const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
const STATUS_LABELS = [
    'pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed',
    'cancelled' => 'Cancelled', 'no_show' => 'No Show',
];

function badge(string $s): string
{
    return '<span class="badge badge-' . e($s) . '">' . e(STATUS_LABELS[$s] ?? $s) . '</span>';
}

function fmt_date(string $d): string { return date('F j, Y', strtotime($d)); }
function fmt_time(string $t): string { return date('h:i A', strtotime($t)); }

function valid_date(string $d): bool
{
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

function setting(mysqli $c, string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_rows($c, "SELECT setting_key k, setting_value v FROM settings") as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return $cache[$key] ?? $default;
}

// ======================= SESSION FRESHNESS =======================
// Re-checks the logged-in user on every request so disabling an account or changing
// a role takes effect immediately.
if (!empty($_SESSION['user_id'])) {
    $__u = db_row($conn, "SELECT role, status, full_name FROM users WHERE id = ?", 'i', [$_SESSION['user_id']]);
    if (!$__u || $__u['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        if (defined('JSON_RESPONSE')) {
            http_response_code(401);
            json_out(['ok' => false, 'message' => 'Your session has ended. Please log in again.']);
        }
        redirect('login.php');
    }
    $_SESSION['role'] = $__u['role'];
    $_SESSION['full_name'] = $__u['full_name'];
}

function staff_course(mysqli $c): ?int
{
    $v = db_val($c, "SELECT course_id FROM supervisors WHERE user_id = ?", 'i', [$_SESSION['user_id']]);
    return $v === null ? null : (int)$v;
}

function current_actor(mysqli $c): array
{
    $role = $_SESSION['role'];
    return [
        'id' => (int)$_SESSION['user_id'],
        'role' => $role,
        'course' => $role === 'supervisor' ? staff_course($c) : null,
    ];
}

// ======================= STATUS RULES =======================
function allowed_statuses(string $role, string $old): array
{
    if ($role === 'admin') {
        return array_values(array_diff(STATUSES, [$old]));
    }
    if ($role === 'supervisor') {
        $map = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'no_show', 'cancelled'],
        ];
        return $map[$old] ?? [];
    }
    if ($role === 'student' && in_array($old, ['pending', 'confirmed'], true)) {
        return ['cancelled'];
    }
    return [];
}

// Students may cancel/reschedule only if the appointment is at least N hours away
function student_can_modify(mysqli $c, array $a): bool
{
    if (!in_array($a['status'], ['pending', 'confirmed'], true)) {
        return false;
    }
    $hours = (int)setting($c, 'cancel_cutoff_hours', 24);
    $start = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    return ($start - time()) >= $hours * 3600;
}

function log_history(mysqli $c, int $apptId, ?string $old, string $new, ?int $by, string $note = ''): void
{
    db_exec($c, "INSERT INTO appointment_history (appointment_id, old_status, new_status, changed_by, note) VALUES (?,?,?,?,?)",
        'issis', [$apptId, $old, $new, $by, $note]);
}

function notify(mysqli $c, int $userId, string $msg): void
{
    db_exec($c, "INSERT INTO notifications (user_id, message) VALUES (?, ?)", 'is', [$userId, mb_substr($msg, 0, 250)]);
}

// ======================= AVAILABILITY =======================
// SQL fragment (aliases: s = schedules, t = time_slots) for "bookable right now" rows
function avail_where(mysqli $c): array
{
    $window = (int)setting($c, 'booking_window_days', 60);
    $today = date('Y-m-d');
    $max = date('Y-m-d', strtotime("+$window days"));
    $sql = "s.is_open = 1 AND t.is_active = 1 AND s.schedule_date <= ?
            AND (s.schedule_date > ? OR (s.schedule_date = ? AND t.start_time > ?))
            AND NOT EXISTS (SELECT 1 FROM blocked_dates b WHERE b.blocked_date = s.schedule_date
                            AND (b.course_id IS NULL OR b.course_id = s.course_id))";
    return [$sql, 'ssss', [$max, $today, $today, date('H:i:s')]];
}

function load_slot(mysqli $c, int $slotId): ?array
{
    return db_row($c, "
        SELECT t.id slot_id, t.start_time, t.capacity, t.current_bookings, t.is_active slot_active,
               s.schedule_date, s.is_open, s.course_id, s.service_id,
               c.is_active course_active, sv.is_active service_active, sv.course_id service_course
        FROM time_slots t
        JOIN schedules s ON s.id = t.schedule_id
        JOIN courses c   ON c.id = s.course_id
        JOIN services sv ON sv.id = s.service_id
        WHERE t.id = ?", 'i', [$slotId]);
}

// Returns an error message, or null if the student may take this slot
function slot_error(mysqli $c, ?array $slot, int $userId, ?int $excludeApptId = null): ?string
{
    if (!$slot) return "The selected time slot does not exist.";
    if (!$slot['course_active']) return "This course is currently unavailable.";
    if (!$slot['service_active']) return "This service is currently unavailable.";
    if (!$slot['is_open'] || !$slot['slot_active']) return "This schedule is currently unavailable.";

    $today = date('Y-m-d');
    if ($slot['schedule_date'] < $today) return "You cannot book a past date.";
    if (strtotime($slot['schedule_date'] . ' ' . $slot['start_time']) <= time()) return "This time slot has already passed.";

    $window = (int)setting($c, 'booking_window_days', 60);
    if ($slot['schedule_date'] > date('Y-m-d', strtotime("+$window days"))) {
        return "Appointments can only be booked up to $window days ahead.";
    }

    $blocked = db_val($c, "SELECT COUNT(*) FROM blocked_dates WHERE blocked_date = ? AND (course_id IS NULL OR course_id = ?)",
        'si', [$slot['schedule_date'], $slot['course_id']]);
    if ($blocked > 0) return "This date is unavailable.";

    if ((int)$slot['current_bookings'] >= (int)$slot['capacity']) return "This time slot is already full.";

    $dup = db_val($c, "SELECT COUNT(*) FROM appointments
                       WHERE user_id = ? AND appointment_date = ? AND appointment_time = ?
                         AND status <> 'cancelled' AND id <> ?",
        'issi', [$userId, $slot['schedule_date'], $slot['start_time'], (int)$excludeApptId]);
    if ($dup > 0) return "You already have an appointment for this schedule.";

    return null;
}

// ======================= BOOKING ENGINE =======================
function book_appointment(mysqli $c, int $userId, int $courseId, int $serviceId, int $slotId, string $notes): array
{
    $c->begin_transaction();
    try {
        db_val($c, "SELECT id FROM time_slots WHERE id = ? FOR UPDATE", 'i', [$slotId]);   // lock the slot row
        $slot = load_slot($c, $slotId);

        if ($slot && ((int)$slot['course_id'] !== $courseId || (int)$slot['service_id'] !== $serviceId)) {
            throw new UserError("The selected schedule does not match your course and service.");
        }
        if ($slot && $slot['service_course'] !== null && (int)$slot['service_course'] !== $courseId) {
            throw new UserError("This service is not offered for the selected course.");
        }
        if ($err = slot_error($c, $slot, $userId)) {
            throw new UserError($err);
        }

        $max = (int)setting($c, 'max_active_appointments', 3);
        $active = (int)db_val($c, "SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status IN ('pending','confirmed') AND appointment_date >= ?",
            'is', [$userId, date('Y-m-d')]);
        if ($max > 0 && $active >= $max) {
            throw new UserError("You already have $max active appointments. Please complete or cancel one first.");
        }

        $tmp = 'TMP' . bin2hex(random_bytes(10));
        db_exec($c, "INSERT INTO appointments (reference_no, user_id, course_id, service_id, slot_id, appointment_date, appointment_time, notes, status)
                     VALUES (?,?,?,?,?,?,?,?, 'pending')",
            'siiiisss', [$tmp, $userId, $courseId, $serviceId, $slotId, $slot['schedule_date'], $slot['start_time'], $notes]);
        $id = (int)$c->insert_id;

        $ref = 'NCST-' . date('Ymd') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
        db_exec($c, "UPDATE appointments SET reference_no = ? WHERE id = ?", 'si', [$ref, $id]);

        $st = db_exec($c, "UPDATE time_slots SET current_bookings = current_bookings + 1 WHERE id = ? AND current_bookings < capacity", 'i', [$slotId]);
        if ($st->affected_rows !== 1) {
            throw new UserError("This time slot is already full.");
        }

        log_history($c, $id, null, 'pending', $userId, 'Appointment booked');
        notify($c, $userId, "Your appointment $ref has been booked and is waiting for confirmation.");

        $c->commit();
        return ['ok' => true, 'message' => 'Appointment successfully booked.', 'ref' => $ref, 'id' => $id];
    } catch (UserError $e) {
        $c->rollback();
        return ['ok' => false, 'message' => $e->getMessage()];
    } catch (Throwable $t) {
        $c->rollback();
        throw $t;
    }
}

function change_status(mysqli $c, int $apptId, string $new, array $actor, string $note = ''): array
{
    $c->begin_transaction();
    try {
        $a = db_row($c, "SELECT * FROM appointments WHERE id = ? FOR UPDATE", 'i', [$apptId]);
        if (!$a) throw new UserError("Appointment not found.");

        // ownership / scope
        if ($actor['role'] === 'student' && (int)$a['user_id'] !== $actor['id']) throw new UserError("Unauthorized access.");
        if ($actor['role'] === 'supervisor' && (int)$a['course_id'] !== (int)$actor['course']) throw new UserError("Unauthorized access.");

        if (!in_array($new, allowed_statuses($actor['role'], $a['status']), true)) {
            throw new UserError("This action is not allowed for the current appointment status.");
        }
        if ($actor['role'] === 'student' && !student_can_modify($c, $a)) {
            $h = (int)setting($c, 'cancel_cutoff_hours', 24);
            throw new UserError("Appointments can only be cancelled at least $h hours before the schedule.");
        }

        $old = $a['status'];
        if ($old !== 'cancelled' && $new === 'cancelled') {
            db_exec($c, "UPDATE time_slots SET current_bookings = GREATEST(current_bookings - 1, 0) WHERE id = ?", 'i', [$a['slot_id']]);
        } elseif ($old === 'cancelled' && $new !== 'cancelled') {
            $st = db_exec($c, "UPDATE time_slots SET current_bookings = current_bookings + 1 WHERE id = ? AND current_bookings < capacity", 'i', [$a['slot_id']]);
            if ($st->affected_rows !== 1) throw new UserError("Cannot restore this appointment: the time slot is full.");
        }

        $reason = ($new === 'cancelled' && $note !== '') ? $note : null;
        db_exec($c, "UPDATE appointments SET status = ?, cancel_reason = ? WHERE id = ?", 'ssi', [$new, $reason, $apptId]);
        log_history($c, $apptId, $old, $new, $actor['id'], $note);

        if ((int)$a['user_id'] !== $actor['id']) {
            notify($c, (int)$a['user_id'], "Your appointment {$a['reference_no']} is now " . STATUS_LABELS[$new] . ($reason ? ". Reason: $reason" : "."));
        }

        $c->commit();
        $msg = $new === 'cancelled' ? "Appointment cancelled successfully." : "Appointment marked as " . STATUS_LABELS[$new] . ".";
        return ['ok' => true, 'message' => $msg];
    } catch (UserError $e) {
        $c->rollback();
        return ['ok' => false, 'message' => $e->getMessage()];
    } catch (Throwable $t) {
        $c->rollback();
        throw $t;
    }
}

function reschedule_appointment(mysqli $c, int $apptId, int $userId, int $newSlotId): array
{
    $c->begin_transaction();
    try {
        $a = db_row($c, "SELECT * FROM appointments WHERE id = ? FOR UPDATE", 'i', [$apptId]);
        if (!$a || (int)$a['user_id'] !== $userId) throw new UserError("Unauthorized access.");
        if (!(int)setting($c, 'allow_reschedule', 1)) throw new UserError("Rescheduling is currently disabled.");
        if (!student_can_modify($c, $a)) {
            $h = (int)setting($c, 'cancel_cutoff_hours', 24);
            throw new UserError("Appointments can only be rescheduled at least $h hours before the schedule.");
        }
        if ((int)$a['slot_id'] === $newSlotId) throw new UserError("Please choose a different time slot.");

        $ids = [(int)$a['slot_id'], $newSlotId];
        sort($ids);                                    // fixed lock order avoids deadlocks
        foreach ($ids as $sid) db_val($c, "SELECT id FROM time_slots WHERE id = ? FOR UPDATE", 'i', [$sid]);

        $slot = load_slot($c, $newSlotId);
        if ($slot && ((int)$slot['course_id'] !== (int)$a['course_id'] || (int)$slot['service_id'] !== (int)$a['service_id'])) {
            throw new UserError("You can only reschedule within the same course and service.");
        }
        if ($err = slot_error($c, $slot, $userId, $apptId)) throw new UserError($err);

        $st = db_exec($c, "UPDATE time_slots SET current_bookings = current_bookings + 1 WHERE id = ? AND current_bookings < capacity", 'i', [$newSlotId]);
        if ($st->affected_rows !== 1) throw new UserError("This time slot is already full.");
        db_exec($c, "UPDATE time_slots SET current_bookings = GREATEST(current_bookings - 1, 0) WHERE id = ?", 'i', [$a['slot_id']]);

        db_exec($c, "UPDATE appointments SET slot_id = ?, appointment_date = ?, appointment_time = ?, status = 'pending', cancel_reason = NULL WHERE id = ?",
            'issi', [$newSlotId, $slot['schedule_date'], $slot['start_time'], $apptId]);

        $note = "Rescheduled from " . fmt_date($a['appointment_date']) . " " . fmt_time($a['appointment_time'])
              . " to " . fmt_date($slot['schedule_date']) . " " . fmt_time($slot['start_time']);
        log_history($c, $apptId, $a['status'], 'pending', $userId, $note);
        notify($c, $userId, "Your appointment {$a['reference_no']} was rescheduled and is waiting for confirmation.");

        $c->commit();
        return ['ok' => true, 'message' => 'Appointment rescheduled successfully.', 'ref' => $a['reference_no']];
    } catch (UserError $e) {
        $c->rollback();
        return ['ok' => false, 'message' => $e->getMessage()];
    } catch (Throwable $t) {
        $c->rollback();
        throw $t;
    }
}

// Appointment visibility check for the current user
function can_view_appt(array $actor, array $a): bool
{
    if ($actor['role'] === 'admin') return true;
    if ($actor['role'] === 'supervisor') return (int)$a['course_id'] === (int)$actor['course'];
    return (int)$a['user_id'] === $actor['id'];
}

function safe_return(string $r, string $fallback): string
{
    return preg_match('/^[a-z_]+\.php(\?[\w=&%.\-\[\]+]*)?$/i', $r) ? $r : $fallback;
}
