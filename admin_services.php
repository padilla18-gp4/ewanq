<?php
// admin_services.php - add / edit / disable / delete appointment services
require_once __DIR__ . "/layout.php";
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('admin_services.php'); }
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        $desc = mb_substr(trim($_POST['description'] ?? ''), 0, 255);
        $courseId = (int)($_POST['course_id'] ?? 0) ?: null;     // empty = all courses
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Please enter a service name.');
        } elseif ($courseId && !db_val($conn, "SELECT COUNT(*) FROM courses WHERE id = ?", 'i', [$courseId])) {
            flash('error', 'Invalid course.');
        } elseif ($id) {
            db_exec($conn, "UPDATE services SET name=?, description=?, course_id=?, is_active=? WHERE id=?", 'ssiii', [$name, $desc, $courseId, $active, $id]);
            flash('success', 'Service updated successfully.');
        } else {
            db_exec($conn, "INSERT INTO services (name, description, course_id, is_active) VALUES (?,?,?,?)", 'ssii', [$name, $desc, $courseId, $active]);
            flash('success', 'Service added successfully.');
        }
    } elseif ($act === 'toggle') {
        db_exec($conn, "UPDATE services SET is_active = 1 - is_active WHERE id = ?", 'i', [$id]);
        flash('success', 'Service status updated.');
    } elseif ($act === 'delete') {
        $used = db_val($conn, "SELECT (SELECT COUNT(*) FROM appointments WHERE service_id = ?) + (SELECT COUNT(*) FROM schedules WHERE service_id = ?)", 'ii', [$id, $id]);
        if ($used > 0) flash('error', 'This service already has schedules or appointments. Disable it instead of deleting it.');
        else { db_exec($conn, "DELETE FROM services WHERE id = ?", 'i', [$id]); flash('success', 'Service deleted.'); }
    }
    redirect('admin_services.php');
}

$rows = db_rows($conn, "SELECT sv.*, c.name course_name FROM services sv LEFT JOIN courses c ON c.id = sv.course_id ORDER BY sv.name, c.name");
$courses = db_rows($conn, "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name");

page_header('Manage Services', 'admin_services.php');
?>
<div class="toolbar"><button class="btn btn-primary" data-modal-open="svcModal" data-title="Add Service">＋ Add Service</button></div>

<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Service</th><th>Description</th><th>Available for</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?php echo e($r['name']); ?></strong></td>
            <td><?php echo e($r['description']); ?></td>
            <td><?php echo e($r['course_name'] ?? 'All courses'); ?></td>
            <td><span class="badge <?php echo $r['is_active'] ? 'badge-confirmed' : 'badge-cancelled'; ?>"><?php echo $r['is_active'] ? 'Active' : 'Disabled'; ?></span></td>
            <td class="actions">
                <button class="btn btn-sm btn-light" data-modal-open="svcModal" data-title="Edit Service"
                    data-fill="<?php echo e(json_encode(['id' => $r['id'], 'name' => $r['name'], 'description' => $r['description'], 'course_id' => $r['course_id'] ?? 0, 'is_active' => $r['is_active']])); ?>">Edit</button>
                <form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-warn"><?php echo $r['is_active'] ? 'Disable' : 'Enable'; ?></button></form>
                <form method="post" class="inline-form" data-confirm="Delete the service “<?php echo e($r['name']); ?>”?"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-bad">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>

<div class="modal" id="svcModal"><div class="modal-box small">
    <div class="modal-head"><h3 data-title>Add Service</h3><button type="button" class="x" data-modal-close>&times;</button></div>
    <form method="post" class="form-grid one">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <label>Service Name <input type="text" name="name" maxlength="100" required></label>
        <label>Description (optional) <input type="text" name="description" maxlength="255"></label>
        <label>Available for
            <select name="course_id"><option value="0">All courses</option>
                <?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?> only</option><?php endforeach; ?>
            </select></label>
        <label class="check"><input type="checkbox" name="is_active" checked> Active (visible to students)</label>
        <div class="modal-actions"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
</div></div>
<?php page_footer(); ?>
