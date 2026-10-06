<?php
// auth.php - shared session, CSRF and role helpers. Include at the top of every page.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Safe output (XSS protection)
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf']) && is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

// ---------- ROLES ----------
function dashboard_for(string $role): string
{
    switch ($role) {
        case 'admin':      return 'admin_dashboard.php';
        case 'supervisor': return 'supervisor_dashboard.php';
        default:           return 'homepage.php'; // student dashboard
    }
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
}

// Usage: require_role(['admin']);  or  require_role(['admin','supervisor']);
function require_role(array $allowedRoles): void
{
    require_login();

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        // Wrong role: send them to THEIR own dashboard
        header("Location: " . dashboard_for($_SESSION['role']));
        exit();
    }
}
