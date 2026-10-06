<?php
// appointments_manager.php - searchable/filterable/paginated appointment list.
// Used by supervisor_appointments.php (scoped to one course) and admin_appointments.php (all courses).
require_once __DIR__ . "/layout.php";

function appointments_manager(mysqli $c, string $role, ?int $scope, string $self): void
{
    $q = trim($_GET['q'] ?? '');
    $date = $_GET['date'] ?? '';
    $status = $_GET['status'] ?? '';
    $serviceF = (int)($_GET['service'] ?? 0);
    $courseF = (int)($_GET['course'] ?? 0);
    $range = $_GET['range'] ?? 'all';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 10;
    $today = date('Y-m-d');

    $where = ['1=1'];
    $types = '';
    $p = [];

    if ($scope) { $where[] = 'a.course_id = ?'; $types .= 'i'; $p[] = $scope; }
    elseif ($courseF) { $where[] = 'a.course_id = ?'; $types .= 'i'; $p[] = $courseF; }

    if ($q !== '') {
        $where[] = '(a.reference_no LIKE ? OR u.full_name LIKE ? OR u.student_id LIKE ?)';
        $like = '%' . $q . '%';
        $types .= 'sss'; array_push($p, $like, $like, $like);
    }
    if (valid_date($date)) { $where[] = 'a.appointment_date = ?'; $types .= 's'; $p[] = $date; }
    if (isset(STATUS_LABELS[$status])) { $where[] = 'a.status = ?'; $types .= 's'; $p[] = $status; }
    if ($serviceF) { $where[] = 'a.service_id = ?'; $types .= 'i'; $p[] = $serviceF; }

    $order = 'a.appointment_date DESC, a.appointment_time DESC';
    if ($range === 'today') { $where[] = 'a.appointment_date = ?'; $types .= 's'; $p[] = $today; $order = 'a.appointment_time ASC'; }
    elseif ($range === 'upcoming') {
        $where[] = "a.appointment_date >= ? AND a.status IN ('pending','confirmed')"; $types .= 's'; $p[] = $today;
        $order = 'a.appointment_date ASC, a.appointment_time ASC';
    } elseif ($range === 'history') { $where[] = "a.status IN ('completed','cancelled','no_show')"; }

    $w = implode(' AND ', $where);
    $total = (int)db_val($c, "SELECT COUNT(*) FROM appointments a JOIN users u ON u.id = a.user_id WHERE $w", $types, $p);
    $off = ($page - 1) * $per;
    $rows = db_rows($c, appt_select_sql() . " WHERE $w ORDER BY $order LIMIT $per OFFSET $off", $types, $p);

    $services = $scope
        ? db_rows($c, "SELECT id, name FROM services WHERE is_active = 1 AND (course_id IS NULL OR course_id = ?) ORDER BY name", 'i', [$scope])
        : db_rows($c, "SELECT id, name FROM services ORDER BY name");
    $courses = $scope ? [] : db_rows($c, "SELECT id, name FROM courses ORDER BY name");

    $tabs = ['all' => 'All', 'today' => 'Today', 'upcoming' => 'Upcoming', 'history' => 'History'];
    echo '<div class="tabs">';
    foreach ($tabs as $k => $label) {
        $qs = http_build_query(['range' => $k]);
        echo '<a href="' . e($self . '?' . $qs) . '" class="' . ($range === $k ? 'on' : '') . '">' . e($label) . '</a>';
    }
    echo '</div>';
    ?>
    <form method="get" class="filters card">
        <input type="hidden" name="range" value="<?php echo e($range); ?>">
        <input type="text" name="q" placeholder="Search reference, student name or ID" value="<?php echo e($q); ?>">
        <input type="date" name="date" value="<?php echo e($date); ?>">
        <?php if (!$scope): ?>
        <select name="course"><option value="0">All courses</option>
            <?php foreach ($courses as $co): ?><option value="<?php echo (int)$co['id']; ?>" <?php echo $courseF === (int)$co['id'] ? 'selected' : ''; ?>><?php echo e($co['name']); ?></option><?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="service"><option value="0">All services</option>
            <?php foreach ($services as $s): ?><option value="<?php echo (int)$s['id']; ?>" <?php echo $serviceF === (int)$s['id'] ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option><?php endforeach; ?>
        </select>
        <select name="status"><option value="">All statuses</option>
            <?php foreach (STATUS_LABELS as $k => $l): ?><option value="<?php echo e($k); ?>" <?php echo $status === $k ? 'selected' : ''; ?>><?php echo e($l); ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Filter</button>
        <a class="btn btn-light" href="<?php echo e($self); ?>">Reset</a>
    </form>

    <div class="card">
        <p class="muted"><?php echo $total; ?> appointment(s) found</p>
        <?php
        $return = $self . (empty($_GET) ? '' : '?' . http_build_query($_GET));
        render_appt_table($rows, $role, $return, !$scope);
        echo pager($total, $page, $per);
        ?>
    </div>
    <?php
}
