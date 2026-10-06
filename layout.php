<?php
// layout.php - shared page frame (sidebar, topbar, modals) + reusable table/pager renderers.
require_once __DIR__ . "/lib.php";

function nav_items(string $role): array
{
    if ($role === 'admin') {
        return [
            ['admin_dashboard.php', 'Dashboard', '⌂'],
            ['admin_appointments.php', 'Appointments', '▣'],
            ['admin_schedules.php', 'Schedules', '▤'],
            ['admin_courses.php', 'Courses', '✎'],
            ['admin_services.php', 'Services', '⚙'],
            ['admin_users.php', 'Users & Roles', '♟'],
            ['admin_supervisors.php', 'Supervisors', '♜'],
            ['admin_reports.php', 'Reports', '▥'],
            ['admin_settings.php', 'Settings', '☰'],
            ['profile.php', 'My Profile', '♙'],
        ];
    }
    if ($role === 'supervisor') {
        return [
            ['supervisor_dashboard.php', 'Dashboard', '⌂'],
            ['supervisor_appointments.php', 'Appointments', '▣'],
            ['supervisor_schedules.php', 'Schedules', '▤'],
            ['profile.php', 'My Profile', '♙'],
        ];
    }
    return [
        ['homepage.php', 'Dashboard', '⌂'],
        ['book_appointment.php', 'Book Appointment', '＋'],
        ['my_appointments.php', 'My Appointments', '▣'],
        ['appointment_history.php', 'Appointment History', '↺'],
        ['profile.php', 'Profile', '♙'],
    ];
}

function page_header(string $title, string $active = ''): void
{
    global $conn;
    $role = $_SESSION['role'];
    $roleLabel = ucfirst($role);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?> - NCST Scheduling System</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="logo-circle">NCST</div>
            <div>
                <h2>NCST</h2>
                <p>NATIONAL COLLEGE OF<br>SCIENCE &amp; TECHNOLOGY</p>
            </div>
        </div>

        <nav class="nav">
            <?php foreach (nav_items($role) as [$href, $label, $icon]): ?>
                <a href="<?php echo e($href); ?>" class="nav-item<?php echo $href === $active ? ' active' : ''; ?>">
                    <span><?php echo $icon; ?></span> <?php echo e($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="logout-box">
            <a href="logout.php" class="nav-item"><span>↪</span> Logout</a>
        </div>
    </aside>

    <div class="content">
        <header class="topbar">
            <button type="button" class="menu-btn" id="menuBtn" aria-label="Menu">☰</button>
            <h1><?php echo e($title); ?></h1>
            <div class="user-chip">
                <div class="avatar"><?php echo e(mb_strtoupper(mb_substr($_SESSION['full_name'], 0, 1))); ?></div>
                <div>
                    <strong><?php echo e($_SESSION['full_name']); ?></strong>
                    <small><?php echo e($roleLabel); ?></small>
                </div>
            </div>
        </header>

        <main class="main">
            <?php
            if (!empty($_SESSION['flash'])) {
                foreach ($_SESSION['flash'] as $f) {
                    echo '<div class="alert alert-' . e($f['t']) . '">' . e($f['m']) . '</div>';
                }
                unset($_SESSION['flash']);
            }
}

function page_footer(array $scripts = []): void
{
    ?>
        </main>
    </div>
</div>

<!-- Confirm modal (used by every form with data-confirm) -->
<div class="modal" id="confirmModal">
    <div class="modal-box small">
        <h3>Please confirm</h3>
        <p id="confirmText"></p>
        <div id="confirmNoteWrap" hidden>
            <label for="confirmNote">Reason / note (optional)</label>
            <textarea id="confirmNote" rows="2" maxlength="250"></textarea>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-light" data-modal-close>Back</button>
            <button type="button" class="btn btn-primary" id="confirmOk">Yes, continue</button>
        </div>
    </div>
</div>

<!-- Appointment details modal -->
<div class="modal" id="viewModal">
    <div class="modal-box">
        <div class="modal-head"><h3>Appointment Details</h3><button type="button" class="x" data-modal-close>&times;</button></div>
        <div id="viewBody">Loading...</div>
    </div>
</div>

<script src="assets/app.js"></script>
<?php foreach ($scripts as $s): ?>
<script src="<?php echo e($s); ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
}

// Buttons/forms for an appointment row, depending on the viewer's role and appointment status
function appt_actions(array $a, string $role, string $return): string
{
    global $conn;
    $id = (int)$a['id'];
    $h = '<button type="button" class="btn btn-sm btn-light" data-view-appt="' . $id . '">View</button> ';

    $allowed = allowed_statuses($role, $a['status']);
    if ($role === 'student' && !student_can_modify($conn, $a)) {
        $allowed = [];
    }

    $hidden = csrf_field()
        . '<input type="hidden" name="appointment_id" value="' . $id . '">'
        . '<input type="hidden" name="return" value="' . e($return) . '">'
        . '<input type="hidden" name="note" value="">';

    if ($role === 'admin') {
        if ($allowed) {
            $h .= '<form method="post" action="appointment_action.php" class="inline-form" data-confirm="Change the status of this appointment?" data-note="1">' . $hidden
                . '<select name="action" class="input-sm">';
            foreach ($allowed as $s) {
                $h .= '<option value="' . e($s) . '">' . e(STATUS_LABELS[$s]) . '</option>';
            }
            $h .= '</select> <button class="btn btn-sm btn-primary">Update</button></form>';
        }
        return $h;
    }

    $labels = ['confirmed' => ['Confirm', 'btn-ok'], 'completed' => ['Complete', 'btn-ok'],
               'cancelled' => [$a['status'] === 'pending' && $role === 'supervisor' ? 'Decline' : 'Cancel', 'btn-bad'],
               'no_show' => ['No Show', 'btn-warn']];
    foreach ($allowed as $s) {
        [$label, $cls] = $labels[$s];
        $note = $s === 'cancelled' ? ' data-note="1"' : '';
        $h .= '<form method="post" action="appointment_action.php" class="inline-form" data-confirm="' . e($label) . ' this appointment (' . e($a['reference_no']) . ')?"' . $note . '>'
            . $hidden . '<input type="hidden" name="action" value="' . e($s) . '">'
            . '<button class="btn btn-sm ' . $cls . '">' . e($label) . '</button></form> ';
    }
    return $h;
}

// Base SELECT used by appointment tables (append WHERE / ORDER yourself)
function appt_select_sql(): string
{
    return "SELECT a.*, u.full_name, u.student_id, c.name course_name, sv.name service_name
            FROM appointments a
            JOIN users u     ON u.id  = a.user_id
            JOIN courses c   ON c.id  = a.course_id
            JOIN services sv ON sv.id = a.service_id ";
}

function render_appt_table(array $rows, string $role, string $return, bool $showCourse = false): void
{
    if (!$rows) {
        echo '<div class="empty">No appointments found.</div>';
        return;
    }
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th>Student</th>';
    if ($showCourse) echo '<th>Course</th>';
    echo '<th>Service</th><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td><strong>' . e($r['reference_no']) . '</strong></td>'
            . '<td>' . e($r['full_name']) . '<br><small>' . e($r['student_id']) . '</small></td>';
        if ($showCourse) echo '<td>' . e($r['course_name']) . '</td>';
        echo '<td>' . e($r['service_name']) . '</td><td>' . e(fmt_date($r['appointment_date'])) . '</td>'
            . '<td>' . e(fmt_time($r['appointment_time'])) . '</td><td>' . badge($r['status']) . '</td>'
            . '<td class="actions">' . appt_actions($r, $role, $return) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}

function pager(int $total, int $page, int $per): string
{
    $pages = (int)ceil($total / $per);
    if ($pages <= 1) return '';
    $h = '<div class="pager">';
    for ($i = 1; $i <= $pages; $i++) {
        $q = http_build_query(array_merge($_GET, ['page' => $i]));
        $h .= '<a class="' . ($i === $page ? 'cur' : '') . '" href="?' . e($q) . '">' . $i . '</a>';
    }
    return $h . '</div>';
}
