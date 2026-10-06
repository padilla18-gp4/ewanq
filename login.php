<?php
require_once "auth.php";
require_once "database.php";      // your existing mysqli $conn
require_once "auth_icons.php";

// Already logged in? Go to the right dashboard.
if (!empty($_SESSION['user_id']) && !empty($_SESSION['role'])) {
    header("Location: " . dashboard_for($_SESSION['role']));
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_verify()) {

        $error = "Session expired. Please try again.";

    } else {

        $identifier = trim($_POST["username"] ?? "");   // Student ID (an email also works)
        $password   = $_POST["password"] ?? "";

        $stmt = $conn->prepare("
            SELECT id, student_id, full_name, email, password, role, status
            FROM users
            WHERE student_id = ? OR email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && password_verify($password, $user["password"])) {

            if ($user["status"] !== "active") {

                $error = "This account has been disabled. Please contact the administrator.";

            } else {

                session_regenerate_id(true);   // prevents session fixation

                $_SESSION["user_id"]   = (int)$user["id"];
                $_SESSION["role"]      = $user["role"];
                $_SESSION["studentID"] = $user["student_id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"]     = $user["email"];

                header("Location: " . dashboard_for($user["role"]));
                exit();
            }

        } else {

            $error = "Invalid Student ID or Password.";
        }
    }
}

$cssVersion = @filemtime(__DIR__ . '/login.css') ?: 1;   // forces the browser to reload the CSS after every edit
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NCST Student Scheduling System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css?v=<?php echo $cssVersion; ?>">
</head>
<body>

<div class="auth-card">

    <!-- LEFT: BRAND PANEL -->
    <section class="brand-panel">
        <img class="logo-img" src="assets/images/ncst-logo.png" alt="NCST logo"
             onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
        <div class="logo-fallback" hidden>NCST</div>

        <h1>NCST</h1>
        <h2>NATIONAL COLLEGE OF<br>SCIENCE &amp; TECHNOLOGY</h2>
        <div class="brand-line"></div>
        <p>STUDENT SCHEDULING SYSTEM</p>
    </section>

    <!-- RIGHT: FORM PANEL -->
    <section class="form-panel">

        <h2>Welcome Back!</h2>
        <p class="subtitle">Login to your account</p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php elseif (!empty($_GET['registered'])): ?>
            <div class="alert alert-success">Account created! You can now log in.</div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" autocomplete="on">

            <?php echo csrf_field(); ?>

            <label class="lbl" for="username">Student ID</label>
            <div class="field">
                <span class="ico-left"><?php echo icon('user'); ?></span>
                <input type="text" id="username" name="username" placeholder="Enter your Student ID"
                       maxlength="100" required value="<?php echo e($_POST['username'] ?? ''); ?>">
            </div>

            <label class="lbl" for="password">Password</label>
            <div class="field">
                <span class="ico-left"><?php echo icon('lock'); ?></span>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="eye" data-toggle="password" aria-label="Show or hide password"><?php echo icon('eye'); ?></button>
            </div>

            <div class="row-between">
                <label class="remember"><input type="checkbox" id="remember"> Remember me</label>
                <button type="button" class="link" id="forgotBtn">Forgot password?</button>
            </div>

            <button type="submit" class="btn-main">Login</button>

            <p class="bottom-text">Don't have an account? <a class="link" href="register.php">Register here</a></p>
        </form>
    </section>
</div>

<!-- Forgot password dialog -->
<div class="overlay" id="forgotBox" hidden>
    <div class="dialog">
        <h3>Forgot your password?</h3>
        <p>Please contact the system administrator or your department so they can reset your password. Bring your Student ID.</p>
        <button type="button" class="btn-main" id="forgotClose">OK</button>
    </div>
</div>

<script>
// show / hide password
document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.toggle);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.classList.toggle('on', show);
    });
});

// remember me (stores only the Student ID in this browser, never the password)
var idInput = document.getElementById('username'), remember = document.getElementById('remember');
try {
    var saved = localStorage.getItem('ncst_student_id');
    if (saved && idInput.value === '') { idInput.value = saved; }
    if (saved) { remember.checked = true; }
} catch (e) {}
document.getElementById('loginForm').addEventListener('submit', function () {
    try {
        if (remember.checked) localStorage.setItem('ncst_student_id', idInput.value.trim());
        else localStorage.removeItem('ncst_student_id');
    } catch (e) {}
});

// forgot password dialog
var box = document.getElementById('forgotBox');
document.getElementById('forgotBtn').addEventListener('click', function () { box.hidden = false; });
document.getElementById('forgotClose').addEventListener('click', function () { box.hidden = true; });
box.addEventListener('click', function (e) { if (e.target === box) box.hidden = true; });
</script>

</body>
</html>
