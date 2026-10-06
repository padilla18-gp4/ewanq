<?php
// schedules_manager.php - create/manage schedules, time slots, capacity and blocked dates.
// Used by supervisor_schedules.php (scope = their course) and admin_schedules.php (scope = null).
require_once __DIR__ . "/layout.php";

function sched_check(mysqli $c, int $scheduleId, ?int $scope): array
{
    $s = db_row($c, "SELECT * FROM schedules WHERE id = ?", 'i', [$scheduleId]);
    if (!$s || ($scope && (int)$s['course_id'] !== $scope)) throw new UserError("Unauthorized access.");
    return $s;
}

function schedules_handle_post(mysqli $c, ?int $scope, string $self): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect($self); }

    $act = $_POST['action'] ?? '';
    try {
        switch ($act) {

            case 'create':
                $courseId = $scope ?? (int)($_POST['course_id'] ?? 0);
                $serviceId = (int)($_POST['service_id'] ?? 0);
                $from = $_POST['date_from'] ?? '';
                $to = ($_POST['date_to'] ?? '') ?: $from;
                $skipWk = isset($_POST['skip_weekends']);
                $start = $_POST['start_time'] ?? '';
                $end = $_POST['end_time'] ?? '';
                $interval = (int)($_POST['interval'] ?? 0);
                $cap = (int)($_POST['capacity'] ?? 0);

                $course = db_row($c, "SELECT id FROM courses WHERE id = ? AND is_active = 1", 'i', [$courseId]);
                if (!$course) throw new UserError("Please select a valid, active course.");
                $svc = db_row($c, "SELECT id FROM services WHERE id = ? AND is_active = 1 AND (course_id IS NULL OR course_id = ?)", 'ii', [$serviceId, $courseId]);
                if (!$svc) throw new UserError("Please select a service available for this course.");
                if (!valid_date($from) || !valid_date($to)) throw new UserError("Please enter valid dates.");
                if ($from < date('Y-m-d')) throw new UserError("You cannot create schedules in the past.");
                if ($to < $from) throw new UserError("The end date must be on or after the start date.");
                if ((strtotime($to) - strtotime($from)) / 86400 > 62) throw new UserError("Please create at most 2 months of schedules at a time.");
                if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) throw new UserError("Start time must be earlier than end time.");
                if ($interval < 5 || $interval > 240) throw new UserError("Slot length must be between 5 and 240 minutes.");
                if ($cap < 1 || $cap > 500) throw new UserError("Capacity must be between 1 and 500.");

                $times = [];
                $t = strtotime("2000-01-01 $start");
                $endT = strtotime("2000-01-01 $end");
                while ($t + $interval * 60 <= $endT) {
                    $times[] = [date('H:i:s', $t), date('H:i:s', $t + $interval * 60)];
                    $t += $interval * 60;
                }
                if (!$times) throw new UserError("The time range is too short for that slot length.");
                if (count($times) > 48) throw new UserError("That would create too many slots per day (max 48).");

                $c->begin_transaction();
                $days = 0; $newSlots = 0;
                $d = new DateTime($from);
                $last = new DateTime($to);
                for (; $d <= $last; $d->modify('+1 day')) {
                    if ($skipWk && (int)$d->format('N') >= 6) continue;
                    db_exec($c, "INSERT INTO schedules (course_id, service_id, schedule_date) VALUES (?,?,?)
                                 ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)", 'iis', [$courseId, $serviceId, $d->format('Y-m-d')]);
                    $sid = (int)$c->insert_id;
                    $days++;
                    foreach ($times as [$s, $e2]) {
                        $st = db_exec($c, "INSERT IGNORE INTO time_slots (schedule_id, start_time, end_time, capacity) VALUES (?,?,?,?)", 'issi', [$sid, $s, $e2, $cap]);
                        $newSlots += $st->affected_rows > 0 ? 1 : 0;
                    }
                }
                $c->commit();
                flash('success', "Schedule saved: $days day(s), $newSlots new time slot(s) created.");
                break;

            case 'toggle_schedule':
                $s = sched_check($c, (int)$_POST['id'], $scope);
                db_exec($c, "UPDATE schedules SET is_open = 1 - is_open WHERE id = ?", 'i', [$s['id']]);
                flash('success', 'Schedule updated.');
                break;

            case 'delete_schedule':
                $s = sched_check($c, (int)$_POST['id'], $scope);
                $n = db_val($c, "SELECT COUNT(*) FROM appointments a JOIN time_slots t ON t.id = a.slot_id WHERE t.schedule_id = ?", 'i', [$s['id']]);
                if ($n > 0) throw new UserError("This schedule already has appointments. Close it instead of deleting it.");
                db_exec($c, "DELETE FROM schedules WHERE id = ?", 'i', [$s['id']]);
                flash('success', 'Schedule deleted.');
                break;

            case 'slot_capacity':
            case 'toggle_slot':
                $slot = db_row($c, "SELECT * FROM time_slots WHERE id = ?", 'i', [(int)$_POST['id']]);
                if (!$slot) throw new UserError("Time slot not found.");
                sched_check($c, (int)$slot['schedule_id'], $scope);
                if ($act === 'toggle_slot') {
                    db_exec($c, "UPDATE time_slots SET is_active = 1 - is_active WHERE id = ?", 'i', [$slot['id']]);
                } else {
                    $cap = (int)($_POST['capacity'] ?? 0);
                    if ($cap < 1 || $cap > 500) throw new UserError("Capacity must be between 1 and 500.");
                    if ($cap < (int)$slot['current_bookings']) throw new UserError("Capacity cannot be lower than the current bookings ({$slot['current_bookings']}).");
                    db_exec($c, "UPDATE time_slots SET capacity = ? WHERE id = ?", 'ii', [$cap, $slot['id']]);
                }
                flash('success', 'Time slot updated.');
                break;

            case 'add_block':
                $date = $_POST['blocked_date'] ?? '';
                if (!valid_date($date) || $date < date('Y-m-d')) throw new UserError("Please choose a valid future date.");
                $courseId = $scope ?? ((int)($_POST['course_id'] ?? 0) ?: null);
                $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 150);
                db_exec($c, "INSERT INTO blocked_dates (course_id, blocked_date, reason) VALUES (?,?,?)", 'iss', [$courseId, $date, $reason]);
                flash('success', 'Date marked as unavailable.');
                break;

            case 'delete_block':
                $b = db_row($c, "SELECT * FROM blocked_dates WHERE id = ?", 'i', [(int)$_POST['id']]);
                if (!$b || ($scope && (int)$b['course_id'] !== $scope)) throw new UserError("Unauthorized access.");
                db_exec($c, "DELETE FROM blocked_dates WHERE id = ?", 'i', [$b['id']]);
                flash('success', 'Unavailable date removed.');
                break;

            default:
                throw new UserError("Unknown action.");
        }
    } catch (UserError $e) {
        if ($c->errno === 0) { try { $c->rollback(); } catch (Throwable $x) {} }
        flash('error', $e->getMessage());
    }
    redirect($self);
}

function schedules_render(mysqli $c, ?int $scope, string $self): void
{
    $courseF = $scope ?? (int)($_GET['course'] ?? 0);
    $serviceF = (int)($_GET['service'] ?? 0);
    $from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d');

    $courses = db_rows($c, "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name");
    $services = $scope
        ? db_rows($c, "SELECT id, name, course_id FROM services WHERE is_active = 1 AND (course_id IS NULL OR course_id = ?) ORDER BY name", 'i', [$scope])
        : db_rows($c, "SELECT id, name, course_id FROM services WHERE is_active = 1 ORDER BY name");

    $where = 's.schedule_date >= ?'; $types = 's'; $p = [$from];
    if ($courseF) { $where .= ' AND s.course_id = ?'; $types .= 'i'; $p[] = $courseF; }
    if ($serviceF) { $where .= ' AND s.service_id = ?'; $types .= 'i'; $p[] = $serviceF; }

    $schedules = db_rows($c, "SELECT s.*, c.name course_name, sv.name service_name FROM schedules s
        JOIN courses c ON c.id = s.course_id JOIN services sv ON sv.id = s.service_id
        WHERE $where ORDER BY s.schedule_date, c.name, sv.name LIMIT 60", $types, $p);

    $slotsBy = [];
    if ($schedules) {
        $ids = implode(',', array_map(fn($s) => (int)$s['id'], $schedules));
        foreach (db_rows($c, "SELECT * FROM time_slots WHERE schedule_id IN ($ids) ORDER BY start_time") as $t) {
            $slotsBy[$t['schedule_id']][] = $t;
        }
    }

    $blocks = db_rows($c, "SELECT b.*, c.name course_name FROM blocked_dates b LEFT JOIN courses c ON c.id = b.course_id
        WHERE b.blocked_date >= ? " . ($scope ? "AND (b.course_id = ? OR b.course_id IS NULL)" : "") . " ORDER BY b.blocked_date",
        $scope ? 'si' : 's', $scope ? [date('Y-m-d'), $scope] : [date('Y-m-d')]);
    ?>
    <div class="toolbar">
        <button class="btn btn-primary" data-modal-open="createModal">＋ Add Schedule</button>
        <button class="btn btn-light" data-modal-open="blockModal">⛔ Set Unavailable Date</button>
    </div>

    <form method="get" class="filters card">
        <?php if (!$scope): ?>
        <select name="course"><option value="0">All courses</option>
            <?php foreach ($courses as $co): ?><option value="<?php echo (int)$co['id']; ?>" <?php echo $courseF === (int)$co['id'] ? 'selected' : ''; ?>><?php echo e($co['name']); ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="service"><option value="0">All services</option>
            <?php foreach ($services as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $serviceF === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option><?php endforeach; ?>
        </select>
        <label class="inline-label">From <input type="date" name="from" value="<?php echo e($from); ?>"></label>
        <button class="btn btn-primary">Filter</button>
    </form>

    <?php if ($blocks): ?>
    <div class="card">
        <h3>Unavailable dates</h3>
        <?php foreach ($blocks as $b): ?>
            <form method="post" class="chip-row" data-confirm="Remove this unavailable date?">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_block"><input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
                <strong><?php echo e(fmt_date($b['blocked_date'])); ?></strong>
                <span class="muted">— <?php echo e($b['course_name'] ?? 'All courses'); ?><?php echo $b['reason'] ? ' · ' . e($b['reason']) : ''; ?></span>
                <?php if (!$scope || $b['course_id']): ?><button class="btn btn-sm btn-bad">Remove</button><?php endif; ?>
            </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$schedules): ?><div class="card empty">No schedules found. Click “Add Schedule” to create dates and time slots.</div><?php endif; ?>

    <?php foreach ($schedules as $s):
        $slots = $slotsBy[$s['id']] ?? [];
        $booked = array_sum(array_column($slots, 'current_bookings'));
        $capTotal = array_sum(array_column($slots, 'capacity'));
        ?>
    <details class="card sched">
        <summary>
            <strong><?php echo e(fmt_date($s['schedule_date'])); ?></strong>
            <span><?php echo e($s['service_name']); ?><?php echo $scope ? '' : ' · ' . e($s['course_name']); ?></span>
            <span class="muted"><?php echo count($slots); ?> slots · <?php echo (int)$booked; ?>/<?php echo (int)$capTotal; ?> booked</span>
            <span class="badge <?php echo $s['is_open'] ? 'badge-confirmed' : 'badge-cancelled'; ?>"><?php echo $s['is_open'] ? 'Open' : 'Closed'; ?></span>
        </summary>

        <div class="toolbar">
            <form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle_schedule"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                <button class="btn btn-sm btn-light"><?php echo $s['is_open'] ? 'Close this date' : 'Re-open this date'; ?></button></form>
            <form method="post" class="inline-form" data-confirm="Delete this schedule and all its (empty) time slots?"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_schedule"><input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                <button class="btn btn-sm btn-bad">Delete schedule</button></form>
        </div>

        <div class="table-wrap"><table class="table">
            <thead><tr><th>Time</th><th>Booked</th><th>Capacity</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($slots as $t):
                $full = (int)$t['current_bookings'] >= (int)$t['capacity']; ?>
                <tr>
                    <td><?php echo e(fmt_time($t['start_time'])); ?> – <?php echo e(fmt_time($t['end_time'])); ?></td>
                    <td><?php echo (int)$t['current_bookings']; ?></td>
                    <td>
                        <form method="post" class="inline-form"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="slot_capacity"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                            <input type="number" name="capacity" class="input-sm w70" min="1" max="500" value="<?php echo (int)$t['capacity']; ?>">
                            <button class="btn btn-sm btn-light">Save</button>
                        </form>
                    </td>
                    <td><?php
                        if (!$t['is_active']) echo '<span class="badge badge-cancelled">Disabled</span>';
                        elseif ($full) echo '<span class="badge badge-no_show">FULL</span>';
                        else echo '<span class="badge badge-confirmed">Available</span>'; ?></td>
                    <td><form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle_slot"><input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                        <button class="btn btn-sm btn-light"><?php echo $t['is_active'] ? 'Disable' : 'Enable'; ?></button></form></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </details>
    <?php endforeach; ?>

    <!-- CREATE SCHEDULE MODAL -->
    <div class="modal" id="createModal"><div class="modal-box">
        <div class="modal-head"><h3>Add Schedule &amp; Time Slots</h3><button type="button" class="x" data-modal-close>&times;</button></div>
        <form method="post" class="form-grid">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
            <?php if (!$scope): ?>
            <label>Course
                <select name="course_id" id="schCourse" required>
                    <option value="">Select course</option>
                    <?php foreach ($courses as $co): ?><option value="<?php echo (int)$co['id']; ?>"><?php echo e($co['name']); ?></option><?php endforeach; ?>
                </select></label>
            <?php endif; ?>
            <label>Service
                <select name="service_id" id="schService" required>
                    <option value="">Select service</option>
                    <?php foreach ($services as $s): ?><option value="<?php echo (int)$s['id']; ?>" data-course="<?php echo (int)($s['course_id'] ?? 0); ?>"><?php echo e($s['name']); ?></option><?php endforeach; ?>
                </select></label>
            <label>From date <input type="date" name="date_from" min="<?php echo date('Y-m-d'); ?>" required></label>
            <label>To date (optional) <input type="date" name="date_to" min="<?php echo date('Y-m-d'); ?>"></label>
            <label>First slot starts <input type="time" name="start_time" value="09:00" required></label>
            <label>Last slot ends <input type="time" name="end_time" value="16:00" required></label>
            <label>Slot length (minutes) <input type="number" name="interval" value="30" min="5" max="240" required></label>
            <label>Capacity per slot <input type="number" name="capacity" value="5" min="1" max="500" required></label>
            <label class="check span2"><input type="checkbox" name="skip_weekends" checked> Skip Saturdays &amp; Sundays</label>
            <div class="modal-actions span2"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Create Schedule</button></div>
        </form>
    </div></div>

    <!-- BLOCK DATE MODAL -->
    <div class="modal" id="blockModal"><div class="modal-box small">
        <div class="modal-head"><h3>Set Unavailable Date</h3><button type="button" class="x" data-modal-close>&times;</button></div>
        <form method="post" class="form-grid one">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="add_block">
            <?php if (!$scope): ?>
            <label>Applies to
                <select name="course_id"><option value="0">All courses</option>
                    <?php foreach ($courses as $co): ?><option value="<?php echo (int)$co['id']; ?>"><?php echo e($co['name']); ?></option><?php endforeach; ?>
                </select></label>
            <?php endif; ?>
            <label>Date <input type="date" name="blocked_date" min="<?php echo date('Y-m-d'); ?>" required></label>
            <label>Reason (optional) <input type="text" name="reason" maxlength="150" placeholder="e.g. Holiday"></label>
            <div class="modal-actions"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
        </form>
    </div></div>

    <script>
    // Admin form: only show services that apply to the chosen course
    (function () {
        const course = document.getElementById('schCourse'), svc = document.getElementById('schService');
        if (!course) return;
        function filter() {
            [...svc.options].forEach(o => { if (!o.value) return; o.hidden = !(o.dataset.course === '0' || o.dataset.course === course.value); });
            if (svc.selectedOptions[0] && svc.selectedOptions[0].hidden) svc.value = '';
        }
        course.addEventListener('change', filter); filter();
    })();
    </script>
    <?php
}
