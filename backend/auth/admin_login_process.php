<?php
ob_start();
session_start();

require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/db.php';

/* ---------------------------------------------------
   URL CONSTANTS
--------------------------------------------------- */

define(
    'ADMIN_SIGNIN',
    rtrim(APP_BASE_URL, '/') . '/src/admin_side/signin.php'
);

define(
    'ADMIN_DASH_ADMIN',
    rtrim(APP_BASE_URL, '/') . '/src/admin_side/dashboard.php'
);

define(
    'ADMIN_DASH_DOCTOR',
    rtrim(APP_BASE_URL, '/') . '/src/doctor/CHO_dashboard.php'
);

/* ---------------------------------------------------
   ENSURE POST REQUEST
--------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_SIGNIN);
    exit;
}

/* ---------------------------------------------------
   INPUTS
--------------------------------------------------- */

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

/* ---------------------------------------------------
   VALIDATION
--------------------------------------------------- */

if ($username === '') {
    header('Location: ' . ADMIN_SIGNIN . '?err=empty_user');
    exit;
}

if ($password === '') {
    header('Location: ' . ADMIN_SIGNIN . '?err=empty_pwd');
    exit;
}

if (!$conn) {
    header('Location: ' . ADMIN_SIGNIN . '?err=db_conn');
    exit;
}

/* ---------------------------------------------------
   FETCH USER
--------------------------------------------------- */

$sql = "
    SELECT
        id,
        username,
        password,
        role,
        status,
        access_level,
        full_name
    FROM public.user_admin
    WHERE username = $1
    LIMIT 1
";

$res = pg_query_params($conn, $sql, [$username]);

if (!$res || pg_num_rows($res) === 0) {

    header('Location: ' . ADMIN_SIGNIN . '?err=no_account');
    exit;
}

$user = pg_fetch_assoc($res);

/* ---------------------------------------------------
   USER DATA
--------------------------------------------------- */

$userId         = $user['id'];
$dbUsername     = $user['username'];
$storedPassword = $user['password'] ?? '';
$dbRole         = strtolower($user['role'] ?? '');
$status         = strtolower($user['status'] ?? 'inactive');
$accessLevel    = strtolower($user['access_level'] ?? 'view');
$fullName       = $user['full_name'] ?? '';

/* ---------------------------------------------------
   STATUS CHECK
--------------------------------------------------- */

if ($status !== 'active') {

    header('Location: ' . ADMIN_SIGNIN . '?err=inactive');
    exit;
}

/* ---------------------------------------------------
   PASSWORD CHECK
--------------------------------------------------- */

if ($storedPassword === '') {

    header('Location: ' . ADMIN_SIGNIN . '?err=invalid_pwd');
    exit;
}

/*
|--------------------------------------------------------------------------
| Support BOTH:
| - hashed passwords
| - plain text passwords (legacy)
|--------------------------------------------------------------------------
*/

$isHashed = preg_match('/^\$(2y|2a|argon2)/', $storedPassword);

if ($isHashed) {
    $validPassword = password_verify($password, $storedPassword);
} else {
    $validPassword = ($password === $storedPassword);
}

if (!$validPassword) {

    header('Location: ' . ADMIN_SIGNIN . '?err=invalid_pwd');
    exit;
}

/* ---------------------------------------------------
   CREATE SESSION
--------------------------------------------------- */

session_regenerate_id(true);

$_SESSION['user_id']      = $userId;
$_SESSION['username']     = $dbUsername;
$_SESSION['role']         = $dbRole;
$_SESSION['status']       = $status;
$_SESSION['access_level'] = $accessLevel;
$_SESSION['full_name']    = $fullName;

/* ---------------------------------------------------
   ROLE-BASED REDIRECT
--------------------------------------------------- */

switch ($dbRole) {

    case 'doctor':
        header('Location: ' . ADMIN_DASH_DOCTOR);
        exit;

    case 'admin':
    case 'super_admin':
        header('Location: ' . ADMIN_DASH_ADMIN);
        exit;

    default:
        header('Location: ' . ADMIN_SIGNIN . '?err=invalid_role');
        exit;
}
?>