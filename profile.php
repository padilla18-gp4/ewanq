<?php
// profile.php - update profile + change password (all roles)
require_once __DIR__ . "/layout.php";
require_login();

$uid = (int)$_SESSION['user_id'];
$isStudent = $_SESSION['role'] === 'student';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { flash('error', 'Session expired. Please try again.'); redirect('profile.php'); }

    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $courseId = $isStudent ? ((int)($_POST['course_id'] ?? 0) ?: null) : null;

        if ($name === '' || mb_strlen($name) > 100) flash('error', 'Please enter your full name.');
        elseif (!preg_match('/^\d{11}$/', $phone) && $isStudent) flash('error', 'Phone number must be 11 digits.');
        else {
            if ($isStudent) {
                db_exec($conn, "UPDATE users SET full_name = ?, phone = ?, course_id = ? WHERE id = ?", 'ssii', [$name, $phone, $courseId, $uid]);
            } else {
                db_exec($conn, "UPDATE users SET full_name = ?, phone = ? WHERE id = ?", 'ssi', [$name, $phone, $uid]);
            }
            $_SESSION['full_name'] = $name;
            flash('success', 'Profile updated successfully.');
        }
    }

    if (($_POST['action'] ?? '') === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        $hash = db_val($conn, "SELECT password FROM users WHERE id = ?", 'i', [$uid]);

        if (!password_verify($cur, $hash)) flash('error', 'Your current password is incorrect.');
        elseif (strlen($new) < 8) flash('error', 'New password must be at least 8 characters.');
        elseif ($new !== $conf) flash('error', 'New passwords do not match.');
        else {
            db_exec($conn, "UPDATE users SET password = ? WHERE id = ?", 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            flash('success', 'Password changed successfully.');
        }
    }
    redirect('profile.php');
}

$u = db_row($conn, "SELECT * FROM users WHERE id = ?", 'i', [$uid]);
$courses = $isStudent ? db_rows($conn, "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name") : [];

page_header('My Profile', 'profile.php');
?>
<div class="two-col">
    <div class="card">
        <h3>Profile Information</h3>
        <form method="post" class="form-grid one">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="profile">
            <label>Student / Staff ID <input type="text" value="<?php echo e($u['student_id']); ?>" disabled></label>
            <label>Email <input type="text" value="<?php echo e($u['email']); ?>" disabled></label>
            <label>Full Name <input type="text" name="full_name" value="<?php echo e($u['full_name']); ?>" required maxlength="100"></label>
            <label>Phone Number <input type="tel" name="phone" value="<?php echo e($u['phone']); ?>" maxlength="11" <?php echo $isStudent ? 'pattern="\d{11}" required' : ''; ?>></label>
            <?php if ($isStudent): ?>
            <label>Course / Department
                <select name="course_id"><option value="0">— Select your course —</option>
                    <?php foreach ($courses as $c): ?><option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$u['course_id'] === (int)$c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option><?php endforeach; ?>
                </select></label>
            <?php endif; ?>
            <div><button class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>

    <div class="card">
        <h3>Change Password</h3>
        <form method="post" class="form-grid one">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="password">
            <label>Current Password <input type="password" name="current_password" required></label>
            <label>New Password <input type="password" name="new_password" minlength="8" required></label>
            <label>Confirm New Password <input type="password" name="confirm_password" minlength="8" required></label>
            <div><button class="btn btn-primary">Change Password</button></div>
        </form>
    </div>
</div>
<?php page_footer(); ?>
