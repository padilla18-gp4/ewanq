<?php
// admin_supervisors.php - add / edit / delete supervisors and assign them to a course
require_once __DIR__ . "/layout.php";
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('admin_supervisors.php'); }
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($act === 'save') {
        $staffId = trim($_POST['student_id'] ?? '');
        $name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '') ?: '00000000000';
        $courseId = (int)($_POST['course_id'] ?? 0);
        $pass = $_POST['password'] ?? '';
        $status = ($_POST['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active';

        if ($staffId === '' || mb_strlen($staffId) > 10) flash('error', 'Staff ID is required (max 10 characters).');
        elseif ($name === '') flash('error', 'Please enter the full name.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) flash('error', 'Please enter a valid email.');
        elseif (!db_val($conn, "SELECT COUNT(*) FROM courses WHERE id = ?", 'i', [$courseId])) flash('error', 'Please assign a course.');
        elseif (!$id && strlen($pass) < 8) flash('error', 'Password must be at least 8 characters.');
        elseif ($id && $pass !== '' && strlen($pass) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif (db_val($conn, "SELECT COUNT(*) FROM users WHERE (student_id = ? OR email = ?) AND id <> ?", 'ssi', [$staffId, $email, $id]) > 0) flash('error', 'Staff ID or email is already used by another account.');
        else {
            $conn->begin_transaction();
            if ($id) {
                if (!db_val($conn, "SELECT COUNT(*) FROM users WHERE id = ? AND role = 'supervisor'", 'i', [$id])) { $conn->rollback(); flash('error', 'Supervisor not found.'); redirect('admin_supervisors.php'); }
                db_exec($conn, "UPDATE users SET student_id=?, full_name=?, email=?, phone=?, status=? WHERE id=?", 'sssssi', [$staffId, $name, $email, $phone, $status, $id]);
                if ($pass !== '') db_exec($conn, "UPDATE users SET password=? WHERE id=?", 'si', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                db_exec($conn, "UPDATE supervisors SET course_id=? WHERE user_id=?", 'ii', [$courseId, $id]);
                flash('success', 'Supervisor updated successfully.');
            } else {
                db_exec($conn, "INSERT INTO users (student_id, full_name, email, phone, password, role, status) VALUES (?,?,?,?,?, 'supervisor', ?)",
                    'ssssss', [$staffId, $name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT), $status]);
                db_exec($conn, "INSERT INTO supervisors (user_id, course_id) VALUES (?, ?)", 'ii', [$conn->insert_id, $courseId]);
                flash('success', 'Supervisor added successfully.');
            }
            $conn->commit();
        }
    } elseif ($act === 'toggle') {
        db_exec($conn, "UPDATE users SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ? AND role = 'supervisor'", 'i', [$id]);
        flash('success', 'Supervisor status updated.');
    } elseif ($act === 'delete') {
        db_exec($conn, "DELETE FROM users WHERE id = ? AND role = 'supervisor'", 'i', [$id]);
        flash('success', 'Supervisor deleted.');
    }
    redirect('admin_supervisors.php');
}

$rows = db_rows($conn, "SELECT u.*, s.course_id sup_course, c.name course_name FROM users u
    LEFT JOIN supervisors s ON s.user_id = u.id LEFT JOIN courses c ON c.id = s.course_id
    WHERE u.role = 'supervisor' ORDER BY u.full_name");
$courses = db_rows($conn, "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name");

page_header('Manage Supervisors', 'admin_supervisors.php');
?>
<div class="toolbar"><button class="btn btn-primary" data-modal-open="supModal" data-title="Add Supervisor">＋ Add Supervisor</button></div>

<div class="card">
<?php if (!$rows): ?><div class="empty">No supervisors yet. Click “Add Supervisor”.</div><?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Staff ID</th><th>Name</th><th>Email</th><th>Course</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?php echo e($r['student_id']); ?></td>
            <td><?php echo e($r['full_name']); ?></td>
            <td><?php echo e($r['email']); ?></td>
            <td><?php echo e($r['course_name'] ?? '—'); ?></td>
            <td><span class="badge <?php echo $r['status'] === 'active' ? 'badge-confirmed' : 'badge-cancelled'; ?>"><?php echo e(ucfirst($r['status'])); ?></span></td>
            <td class="actions">
                <button class="btn btn-sm btn-light" data-modal-open="supModal" data-title="Edit Supervisor"
                    data-fill="<?php echo e(json_encode(['id' => $r['id'], 'student_id' => $r['student_id'], 'full_name' => $r['full_name'], 'email' => $r['email'], 'phone' => $r['phone'], 'course_id' => $r['sup_course'], 'status' => $r['status']])); ?>">Edit</button>
                <form method="post" class="inline-form"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-warn"><?php echo $r['status'] === 'active' ? 'Disable' : 'Enable'; ?></button></form>
                <form method="post" class="inline-form" data-confirm="Delete supervisor “<?php echo e($r['full_name']); ?>”?"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                    <button class="btn btn-sm btn-bad">Delete</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
</div>

<div class="modal" id="supModal"><div class="modal-box">
    <div class="modal-head"><h3 data-title>Add Supervisor</h3><button type="button" class="x" data-modal-close>&times;</button></div>
    <form method="post" class="form-grid">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
        <label>Staff ID <input type="text" name="student_id" maxlength="10" required placeholder="SUP-001"></label>
        <label>Full Name <input type="text" name="full_name" maxlength="100" required></label>
        <label>Email <input type="email" name="email" required></label>
        <label>Phone <input type="text" name="phone" maxlength="11"></label>
        <label>Assigned Course
            <select name="course_id" required><option value="">Select course</option>
                <?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['name']); ?></option><?php endforeach; ?>
            </select></label>
        <label>Status <select name="status"><option value="active">Active</option><option value="disabled">Disabled</option></select></label>
        <label class="span2">Password <small>(leave blank when editing to keep the current one)</small>
            <input type="password" name="password" minlength="8" autocomplete="new-password"></label>
        <div class="modal-actions span2"><button type="button" class="btn btn-light" data-modal-close>Cancel</button><button class="btn btn-primary">Save</button></div>
    </form>
</div></div>
<?php page_footer(); ?>
