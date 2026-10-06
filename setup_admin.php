<?php
// setup_admin.php - ONE-TIME script to create your first admin and a test supervisor.
// 1) Run it once at http://localhost/YOUR_FOLDER/setup_admin.php
// 2) DELETE THIS FILE immediately afterward.

require_once "auth.php";
require_once "database.php";

$msg = "";

$exists = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetch_assoc()['c'] > 0;

if ($exists) {
    $msg = "An admin already exists. Delete this file.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_verify()) {

    $staffId  = trim($_POST["staff_id"] ?? "");
    $name     = trim($_POST["full_name"] ?? "");
    $email    = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($staffId === "" || $name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $msg = "Fill in every field. Password must be at least 8 characters.";
    } else {
        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $phone = "00000000000";
        $stmt  = $conn->prepare("INSERT INTO users (student_id, full_name, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'admin')");
        $stmt->bind_param("sssss", $staffId, $name, $email, $phone, $hash);
        $msg = $stmt->execute() ? "Admin created! DELETE setup_admin.php NOW, then log in." : "Could not create admin (ID or email already used).";
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Create Admin</title></head>
<body style="font-family:Arial;padding:40px;max-width:420px">
    <h2>Create first Admin</h2>
    <?php if ($msg): ?><p><strong><?php echo e($msg); ?></strong></p><?php endif; ?>
    <?php if (!$exists): ?>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <p>Staff ID (e.g. ADMIN-001)<br><input name="staff_id" required maxlength="10"></p>
        <p>Full name<br><input name="full_name" required></p>
        <p>Email<br><input type="email" name="email" required></p>
        <p>Password (8+ chars)<br><input type="password" name="password" required minlength="8"></p>
        <button type="submit">Create Admin</button>
    </form>
    <?php endif; ?>
</body></html>
