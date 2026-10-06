<?php
// admin_courses.php - add / edit / disable / delete courses
require_once __DIR__ . "/layout.php";
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('admin_courses.php'); }
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = mb_substr(trim($_POST['description'] ?? ''), 0, 255);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($code === '' || mb_strlen($code) > 20 || $name === '' || mb_strlen($name) > 100) {
            flash('error', 'Please enter a course code (max 20 characters) and a course name.');
        } elseif (db_val($conn, "SELECT COUNT(*) FROM courses WHERE code = ? AND id <> ?", 'si', [$code, $id]) > 0) {
            flash('error', 'That course code is already used.');
        } elseif ($id) {
            db_exec($conn, "UPDATE courses SET code=?, name=?, description=?, is_active=? WHERE id=?", 'sssii', [$code, $name, $desc, $active, $id]);
            flash('success', 'Course updated successfully.');
        } else {
            db_exec($conn, "INSERT INTO courses (code, name, description, is_active) VALUES (?,?,?,?)", 'sssi', [$code, $name, $desc, $active]);
            flash('success', 'Course added successfully.');
        }
    } elseif ($act === 'toggle') {
        db_exec($conn, "UPDATE courses SET is_active = 1 - is_active WHERE id = ?", 'i', [$id]);
        flash('success', 'Course status updated.');
    } elseif ($act === 'delete') {
        $used = db_val($conn, "SELECT (SELECT COUNT(*) FROM appointments WHERE course_id = ?) + (SELECT COUNT(*) FROM schedules WHERE course_id = ?)
                               + (SELECT COUNT(*) FROM supervisors WHERE course_id = ?)", 'iii', [$id, $id, $id]);
        if ($used > 0) {
            flash('error', 'This course already has appointments, schedules or supervisors. Disable it instead of deleting it.');
        } else {
            db_exec($conn, "UPDATE users SET course_id = NULL WHERE course_id = ?", 'i', [$id]);
            db_exec($conn, "DELETE FROM courses WHERE id = ?", 'i', [$id]);   // its own services are removed by the foreign key
            flash('success', 'Course deleted.');
        }
    }
    redirect('admin_courses.php');
}

$rows = db_rows($conn, "SELECT c.*, (SELECT COUNT(*) FROM appointments a WHERE a.course_id = c.id) appts FROM courses c ORDER BY c.name");

page_header('Manage Courses', 'admin_courses.php');
?>
<div class="toolbar"><button class="btn btn-primary" data-modal-open="courseModal" data-title="Add Course">＋ Add Course</button></div>

<div class="card"><div class="table-wrap"><table class="table">
    <thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Appointments</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?php echo e($r['code']); ?></strong></td>
            <td><?php echo e($r['name']); ?></td>
            <td><?php echo e($r['description']); ?></td>
            <td><?php echo (int)$r['appts']; ?></td>
            <td><span class="badge <?php echo $r['is_active'] ? 'badge-confirmed' : 'badge-cancelled'; ?>"><?php echo $r['is_active'] ? 'Active' : 'Disabled'; ?></span></td>
            <td class="actions">
                <button class="btn btn-sm btn-light" data-modal-open="courseModal" data-title="Edit Course"
                    data-fill="<?php echo e(json_encode(['id' => $r['id'], 'code' => $r['code'], 'name' => $r['name'], 'description' => $r['description'], 'is_active' => $r['is_active']])); ?>">Edit</button>
                <form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-warn"><?php echo $r['is_active'] ? 'Disable' : 'Enable'; ?></button></form>
                <form method="post" class="inline-form" data-confirm="Delete the course “<?php echo e($r['name']); ?>”? This cannot be undone."><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-bad">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>

<div class="modal" id="courseModal"><div class="modal-box small">
    <div class="modal-head"><h3 data-title>Add Course</h3><button type="button" class="x" data-modal-close>&times;</button></div>
    <form method="post" class="form-grid one">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <label>Course Code <input type="text" name="code" maxlength="20" required placeholder="e.g. BSIT"></label>
        <label>Course Name <input type="text" name="name" maxlength="100" required placeholder="e.g. Information Technology"></label>
        <label>Description (optional) <input type="text" name="description" maxlength="255"></label>
        <label class="check"><input type="checkbox" name="is_active" checked> Active (visible to students)</label>
        <div class="modal-actions"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
</div></div>
<?php page_footer(); ?>
