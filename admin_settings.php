<?php
// admin_settings.php - system settings
require_once __DIR__ . "/layout.php";
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('admin_settings.php'); }
    $vals = [
        'booking_window_days' => max(1, min(365, (int)($_POST['booking_window_days'] ?? 60))),
        'cancel_cutoff_hours' => max(0, min(240, (int)($_POST['cancel_cutoff_hours'] ?? 24))),
        'allow_reschedule' => isset($_POST['allow_reschedule']) ? 1 : 0,
        'max_active_appointments' => max(0, min(50, (int)($_POST['max_active_appointments'] ?? 3))),
    ];
    foreach ($vals as $k => $v) {
        db_exec($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 'ss', [$k, (string)$v]);
    }
    flash('success', 'Settings saved.');
    redirect('admin_settings.php');
}

page_header('System Settings', 'admin_settings.php');
?>
<div class="card" style="max-width:640px">
    <form method="post" class="form-grid one">
        <?php echo csrf_field(); ?>
        <label>How many days ahead can students book?
            <input type="number" name="booking_window_days" min="1" max="365" value="<?php echo (int)setting($conn, 'booking_window_days', 60); ?>"></label>
        <label>Cancel / reschedule cut-off (hours before the appointment)
            <input type="number" name="cancel_cutoff_hours" min="0" max="240" value="<?php echo (int)setting($conn, 'cancel_cutoff_hours', 24); ?>"></label>
        <label>Maximum active appointments per student <small>(0 = unlimited)</small>
            <input type="number" name="max_active_appointments" min="0" max="50" value="<?php echo (int)setting($conn, 'max_active_appointments', 3); ?>"></label>
        <label class="check"><input type="checkbox" name="allow_reschedule" <?php echo (int)setting($conn, 'allow_reschedule', 1) ? 'checked' : ''; ?>> Allow students to reschedule appointments</label>
        <div><button class="btn btn-primary">Save Settings</button></div>
    </form>
</div>
<?php page_footer(); ?>
