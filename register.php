<?php
require_once "auth.php";
require_once "database.php";      // your existing mysqli $conn
require_once "auth_icons.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $studentId       = trim($_POST["studentId"] ?? "");
    $fullName        = trim($_POST["fName"] ?? "");
    $email           = strtolower(trim($_POST["email"] ?? ""));
    $phone           = trim($_POST["tel"] ?? "");
    $password        = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // ---------- SERVER-SIDE VALIDATION ----------
    if (!csrf_verify()) {
        $message = "Session expired. Please try again.";
    } elseif (!preg_match('/^\d{4}-\d{5}$/', $studentId)) {
        $message = "Student ID must look like 2026-12345.";
    } elseif ($fullName === "" || mb_strlen($fullName) > 100) {
        $message = "Please enter your full name.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || substr($email, -12) !== "@ncst.edu.ph") {
        $message = "Please use your NCST school email (@ncst.edu.ph).";
    } elseif (!preg_match('/^\d{11}$/', $phone)) {
        $message = "Phone number must be 11 digits.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match!";
    } else {

        $check = $conn->prepare("SELECT id FROM users WHERE student_id = ? OR email = ?");
        $check->bind_param("ss", $studentId, $email);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {

            $message = "Student ID or Email already exists!";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // role is always 'student' - it is never taken from the form
            $insert = $conn->prepare("
                INSERT INTO users (student_id, full_name, email, phone, password, role)
                VALUES (?, ?, ?, ?, ?, 'student')
            ");
            $insert->bind_param("sssss", $studentId, $fullName, $email, $phone, $hashedPassword);

            if ($insert->execute()) {
                header("Location: login.php?registered=1");
                exit();
            }

            $message = "Registration failed. Please try again.";
        }
    }
}

$cssVersion = @filemtime(__DIR__ . '/register.css') ?: 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - NCST Scheduling System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="register.css?v=<?php echo $cssVersion; ?>">
</head>
<body>

<div class="reg-card">

    <header class="reg-head">
        <div>
            <img class="reg-logo" src="assets/images/ncst-logo.png" alt="NCST logo"
                 onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
            <div class="logo-fallback" hidden>NCST</div>
        </div>
        <h1>STUDENT REGISTRATION</h1>
        <div></div>
    </header>

    <h2 class="reg-title">Create Student Account</h2>
    <p class="reg-sub">Fill in your information to register</p>

    <?php if ($message !== ""): ?>
        <div class="alert alert-error"><?php echo e($message); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="regForm">

        <?php echo csrf_field(); ?>

        <div class="grid">

            <div>
                <label class="lbl" for="studentId">Student ID</label>
                <div class="field">
                    <input type="text" id="studentId" name="studentId" placeholder="Enter your Student ID (2026-12345)"
                           maxlength="10" pattern="\d{4}-\d{5}" title="Format: 2026-12345" required
                           value="<?php echo e($_POST['studentId'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label class="lbl" for="email">Email Address</label>
                <div class="field">
                    <input type="email" id="email" name="email" placeholder="Enter your email (@ncst.edu.ph)" required
                           value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label class="lbl" for="fName">Full Name</label>
                <div class="field">
                    <input type="text" id="fName" name="fName" placeholder="Enter your full name" maxlength="100" required
                           value="<?php echo e($_POST['fName'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label class="lbl" for="tel">Phone Number</label>
                <div class="field">
                    <input type="tel" id="tel" name="tel" placeholder="Enter your phone number"
                           maxlength="11" pattern="\d{11}" title="11 digits, e.g. 09123456789" required
                           value="<?php echo e($_POST['tel'] ?? ''); ?>">
                </div>
            </div>

            <div>
                <label class="lbl" for="password">Password</label>
                <div class="field has-eye">
                    <input type="password" id="password" name="password" placeholder="Create a password" minlength="8" required>
                    <button type="button" class="eye" data-toggle="password" aria-label="Show or hide password"><?php echo icon('eye'); ?></button>
                </div>
            </div>

            <div>
                <label class="lbl" for="confirm_password">Confirm Password</label>
                <div class="field has-eye">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" minlength="8" required>
                    <button type="button" class="eye" data-toggle="confirm_password" aria-label="Show or hide password"><?php echo icon('eye'); ?></button>
                </div>
            </div>

        </div>

        <button type="submit" class="btn-gold">Register</button>

        <p class="login-text">Already have an account? <a href="login.php">Login here</a></p>
    </form>
</div>

<script src="register.js"></script>
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

// live "passwords match" check
var pw = document.getElementById('password'), cpw = document.getElementById('confirm_password');
function checkMatch() {
    cpw.setCustomValidity(cpw.value !== '' && cpw.value !== pw.value ? 'Passwords do not match.' : '');
}
pw.addEventListener('input', checkMatch);
cpw.addEventListener('input', checkMatch);
</script>

</body>
</html>
