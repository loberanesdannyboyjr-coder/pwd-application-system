<?php
session_start();

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/db.php';

define('ADMIN_SIGNIN', rtrim(APP_BASE_URL, '/') . '/src/admin_side/signin.php');
define('ADMIN_DASH_ADMIN', rtrim(APP_BASE_URL, '/') . '/src/admin_side/dashboard.php');
define('ADMIN_DASH_DOCTOR', rtrim(APP_BASE_URL, '/') . '/src/doctor/CHO_dashboard.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_SIGNIN);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$role     = strtolower(trim($_POST['role'] ?? 'admin'));

if ($username === '' || $password === '') {
    header('Location: ' . ADMIN_SIGNIN . '?err=empty');
    exit;
}

if (!$conn) {
    header('Location: ' . ADMIN_SIGNIN . '?err=db');
    exit;
}

/* -------------------------------------------------
| Fetch user (ADMIN or DOCTOR both from user_admin)
--------------------------------------------------*/
$sql = "SELECT id, username, password, is_admin, is_doctor
        FROM public.user_admin
        WHERE username = $1
        LIMIT 1";

$res = pg_query_params($conn, $sql, [$username]);

if (!$res || pg_num_rows($res) === 0) {
    header('Location: ' . ADMIN_SIGNIN . '?err=no_account');
    exit;
}

$user = pg_fetch_assoc($res);
$storedPassword = $user['password'] ?? '';

/* -------------------------------------------------
| Verify role
--------------------------------------------------*/
if ($role === 'admin' && !$user['is_admin']) {
    header('Location: ' . ADMIN_SIGNIN . '?err=invalid_role');
    exit;
}

if ($role === 'doctor' && !$user['is_doctor']) {
    header('Location: ' . ADMIN_SIGNIN . '?err=doctor_not_supported');
    exit;
}

/* -------------------------------------------------
| Password verification
--------------------------------------------------*/
if ($storedPassword === '') {
    header('Location: ' . ADMIN_SIGNIN . '?err=invalid_pwd');
    exit;
}

$valid = preg_match('/^\$(2y|2a|argon2)/', $storedPassword)
    ? password_verify($password, $storedPassword)
    : ($password === $storedPassword);

if (!$valid) {
    header('Location: ' . ADMIN_SIGNIN . '?err=invalid_pwd');
    exit;
}

/* -------------------------------------------------
| Login success
--------------------------------------------------*/
session_regenerate_id(true);

$_SESSION['user_id']  = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role']     = $role;
$_SESSION['is_admin'] = ($role === 'admin');

if ($role === 'admin') {
    header('Location: ' . ADMIN_DASH_ADMIN);
    exit;
}

header('Location: ' . ADMIN_DASH_DOCTOR);
exit;
