<?php
// admin_reports.php - statistics, print, CSV export
require_once __DIR__ . "/layout.php";
require_role(['admin']);

$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-t');
if ($to < $from) { $to = $from; }

// ---------- CSV export ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments_' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Reference', 'Student ID', 'Student', 'Course', 'Service', 'Date', 'Time', 'Status', 'Booked On']);
    foreach (db_rows($conn, appt_select_sql() . " WHERE a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_date, a.appointment_time", 'ss', [$from, $to]) as $r) {
        fputcsv($out, [$r['reference_no'], $r['student_id'], $r['full_name'], $r['course_name'], $r['service_name'],
            $r['appointment_date'], fmt_time($r['appointment_time']), STATUS_LABELS[$r['status']], $r['created_at']]);
    }
    fclose($out);
    exit;
}

$rng = 'a.appointment_date BETWEEN ? AND ?';
$P = [$from, $to];
$byStatus = [];
foreach (db_rows($conn, "SELECT status, COUNT(*) n FROM appointments a WHERE $rng GROUP BY status", 'ss', $P) as $r) $byStatus[$r['status']] = (int)$r['n'];
$total = array_sum($byStatus);

$cols = "COUNT(*) n, SUM(status='completed') done, SUM(status='cancelled') canc, SUM(status='no_show') ns";
$daily = db_rows($conn, "SELECT a.appointment_date label, $cols FROM appointments a WHERE $rng GROUP BY a.appointment_date ORDER BY a.appointment_date", 'ss', $P);
$weekly = db_rows($conn, "SELECT MIN(a.appointment_date) label, $cols FROM appointments a WHERE $rng GROUP BY YEARWEEK(a.appointment_date, 1) ORDER BY label", 'ss', $P);
$monthly = db_rows($conn, "SELECT DATE_FORMAT(a.appointment_date, '%Y-%m') label, $cols FROM appointments a WHERE $rng GROUP BY label ORDER BY label", 'ss', $P);
$byCourse = db_rows($conn, "SELECT c.name label, $cols FROM appointments a JOIN courses c ON c.id = a.course_id WHERE $rng GROUP BY c.id ORDER BY n DESC", 'ss', $P);
$byService = db_rows($conn, "SELECT sv.name label, $cols FROM appointments a JOIN services sv ON sv.id = a.service_id WHERE $rng GROUP BY sv.id ORDER BY n DESC", 'ss', $P);

function report_table(string $title, array $rows, string $labelHead, string $kind = ''): void
{
    echo '<div class="card"><h3>' . e($title) . '</h3>';
    if (!$rows) { echo '<div class="empty">No data in this period.</div></div>'; return; }
    $max = max(array_map(fn($r) => (int)$r['n'], $rows));
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>' . e($labelHead) . '</th><th>Total</th><th>Completed</th><th>Cancelled</th><th>No Show</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $label = $r['label'];
        if ($kind === 'day') $label = fmt_date($label);
        if ($kind === 'week') $label = 'Week of ' . fmt_date($label);
        if ($kind === 'month') $label = date('F Y', strtotime($label . '-01'));
        echo '<tr><td>' . e($label) . '</td><td><strong>' . (int)$r['n'] . '</strong></td><td>' . (int)$r['done'] . '</td><td>' . (int)$r['canc'] . '</td><td>' . (int)$r['ns']
            . '</td><td style="width:30%"><div class="bar"><i style="width:' . round(100 * $r['n'] / $max) . '%"></i></div></td></tr>';
    }
    echo '</tbody></table></div></div>';
}

page_header('Reports & Statistics', 'admin_reports.php');
?>
<form method="get" class="filters card no-print">
    <label class="inline-label">From <input type="date" name="from" value="<?php echo e($from); ?>"></label>
    <label class="inline-label">To <input type="date" name="to" value="<?php echo e($to); ?>"></label>
    <button class="btn btn-primary">Generate</button>
    <a class="btn btn-light" href="?<?php echo e(http_build_query(['from' => $from, 'to' => $to, 'export' => 'csv'])); ?>">⬇ Export CSV</a>
    <button type="button" class="btn btn-light" onclick="window.print()">🖨 Print Report</button>
</form>

<h3>Report period: <?php echo e(fmt_date($from)); ?> – <?php echo e(fmt_date($to)); ?></h3>
<div class="stats">
    <div class="stat s-gray"><b><?php echo $total; ?></b><span>Total Appointments</span></div>
    <div class="stat s-yellow"><b><?php echo $byStatus['pending'] ?? 0; ?></b><span>Pending</span></div>
    <div class="stat s-green"><b><?php echo $byStatus['confirmed'] ?? 0; ?></b><span>Confirmed</span></div>
    <div class="stat s-blue"><b><?php echo $byStatus['completed'] ?? 0; ?></b><span>Completed</span></div>
    <div class="stat s-red"><b><?php echo $byStatus['cancelled'] ?? 0; ?></b><span>Cancelled</span></div>
    <div class="stat s-gray"><b><?php echo $byStatus['no_show'] ?? 0; ?></b><span>No Show</span></div>
</div>

<?php
report_table('Daily appointments', $daily, 'Date', 'day');
report_table('Weekly appointments', $weekly, 'Week', 'week');
report_table('Monthly appointments', $monthly, 'Month', 'month');
report_table('Appointments by course', $byCourse, 'Course');
report_table('Appointments by service', $byService, 'Service');
page_footer();
