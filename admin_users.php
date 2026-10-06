<?php
// admin_users.php - search all users, change roles, enable/disable accounts
require_once __DIR__ . "/layout.php";
require_role(['admin']);

$me = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('admin_users.php'); }
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $back = safe_return($_POST['return'] ?? '', 'admin_users.php');
    $u = db_row($conn, "SELECT id, role FROM users WHERE id = ?", 'i', [$id]);

    if (!$u) flash('error', 'User not found.');
    elseif ($id === $me) flash('error', 'You cannot change your own role or status here.');
    elseif ($act === 'toggle') {
        db_exec($conn, "UPDATE users SET status = IF(status = 'active', 'disabled', 'active') WHERE id = ?", 'i', [$id]);
        flash('success', 'Account status updated.');
    } elseif ($act === 'set_role') {
        $role = $_POST['role'] ?? '';
        $courseId = (int)($_POST['course_id'] ?? 0);
        if (!in_array($role, ['student', 'supervisor', 'admin'], true)) flash('error', 'Invalid role.');
        elseif ($role === 'supervisor' && !db_val($conn, "SELECT COUNT(*) FROM courses WHERE id = ?", 'i', [$courseId])) flash('error', 'Choose the course this supervisor will manage.');
        else {
            db_exec($conn, "UPDATE users SET role = ? WHERE id = ?", 'si', [$role, $id]);
            if ($role === 'supervisor') db_exec($conn, "INSERT INTO supervisors (user_id, course_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE course_id = VALUES(course_id)", 'ii', [$id, $courseId]);
            else db_exec($conn, "DELETE FROM supervisors WHERE user_id = ?", 'i', [$id]);
            flash('success', 'Role updated to ' . ucfirst($role) . '.');
        }
    }
    redirect($back);
}

$q = trim($_GET['q'] ?? '');
$roleF = $_GET['role'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 12;
$w = '1=1'; $t = ''; $p = [];
if ($q !== '') { $w .= ' AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)'; $like = "%$q%"; $t .= 'sss'; array_push($p, $like, $like, $like); }
if (in_array($roleF, ['student', 'supervisor', 'admin'], true)) { $w .= ' AND u.role = ?'; $t .= 's'; $p[] = $roleF; }

$total = (int)db_val($conn, "SELECT COUNT(*) FROM users u WHERE $w", $t, $p);
$rows = db_rows($conn, "SELECT u.*, s.course_id sup_course FROM users u LEFT JOIN supervisors s ON s.user_id = u.id
    WHERE $w ORDER BY u.created_at DESC, u.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $t, $p);
$courses = db_rows($conn, "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name");
$return = 'admin_users.php' . (empty($_GET) ? '' : '?' . http_build_query($_GET));

page_header('Users & Roles', 'admin_users.php');
?>
<form method="get" class="filters card">
    <input type="text" name="q" placeholder="Search name, ID or email" value="<?php echo e($q); ?>">
    <select name="role"><option value="">All roles</option>
        <?php foreach (['student', 'supervisor', 'admin'] as $r): ?><option value="<?php echo $r; ?>" <?php echo $roleF === $r ? 'selected' : ''; ?>><?php echo ucfirst($r); ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-primary">Search</button><a class="btn btn-light" href="admin_users.php">Reset</a>
</form>

<div class="card">
    <p class="muted"><?php echo $total; ?> user(s)</p>
    <?php if (!$rows): ?><div class="empty">No users found.</div><?php else: ?>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th>Status</th><th>Role</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $self = (int)$r['id'] === $me; ?>
            <tr>
                <td><?php echo e($r['student_id']); ?></td>
                <td><?php echo e($r['full_name']); ?></td>
                <td><?php echo e($r['email']); ?></td>
                <td><?php echo e($r['phone']); ?></td>
                <td><?php echo e(date('M j, Y', strtotime($r['created_at']))); ?></td>
                <td>
                    <span class="badge <?php echo $r['status'] === 'active' ? 'badge-confirmed' : 'badge-cancelled'; ?>"><?php echo e(ucfirst($r['status'])); ?></span>
                    <?php if (!$self): ?>
                    <form method="post" class="inline-form" data-confirm="<?php echo $r['status'] === 'active' ? 'Disable' : 'Enable'; ?> this account?">
                        <?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="return" value="<?php echo e($return); ?>">
                        <button class="btn btn-sm btn-warn"><?php echo $r['status'] === 'active' ? 'Disable' : 'Enable'; ?></button></form>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($self): ?><span class="badge badge-completed">You (Admin)</span><?php else: ?>
                    <form method="post" class="inline-form" data-confirm="Change this user's role?">
                        <?php echo csrf_field(); ?><input type="hidden" name="action" value="set_role"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><input type="hidden" name="return" value="<?php echo e($return); ?>">
                        <select name="role" class="input-sm">
                            <?php foreach (['student', 'supervisor', 'admin'] as $ro): ?><option value="<?php echo $ro; ?>" <?php echo $r['role'] === $ro ? 'selected' : ''; ?>><?php echo ucfirst($ro); ?></option><?php endforeach; ?>
                        </select>
                        <select name="course_id" class="input-sm" title="Course (only used for Supervisor)">
                            <option value="0">Course (supervisors)</option>
                            <?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$r['sup_course'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-primary">Save</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php echo pager($total, $page, $per); endif; ?>
</div>
<?php page_footer(); ?>
