<?php
// src/admin_side/change_status.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';
session_start();

// --- 1) auth: must be admin ---
if (
    empty($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])
) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

// --- 2) basic POST checks ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    // invalid CSRF -> redirect back (safe)
    header('Location: ' . rtrim(APP_BASE_URL, '/') . '/admin/application_review.php?msg=error');
    exit;
}

$appId = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
$action = $_POST['action'] ?? '';
$remarks = trim((string)($_POST['remarks'] ?? ''));

// limit remarks length (example: 1000 chars)
$maxRemarks = 1000;
if (mb_strlen($remarks) > $maxRemarks) {
    $remarks = mb_substr($remarks, 0, $maxRemarks);
}

// allowed actions -> statuses
$map = [
    'approve' => 'Accepted',
    'reject'  => 'Rejected'
];

if ($appId <= 0 || !isset($map[$action])) {
    header('Location: ' . rtrim(APP_BASE_URL, '/') . "/admin/view_a.php?id={$appId}&msg=error");
    exit;
}

$newStatus = $map[$action];
$adminUserId = intval($_SESSION['user_id']);

// Optional: validate application exists
$existsQ = "SELECT application_id FROM application WHERE application_id = $1 LIMIT 1";
$r = pg_query_params($conn, $existsQ, [$appId]);
if (!$r || pg_num_rows($r) === 0) {
    header('Location: ' . rtrim(APP_BASE_URL, '/') . "/admin/view_a.php?id={$appId}&msg=error");
    exit;
}

// --- 3) run transaction: update application + insert into status_history ---
pg_query($conn, 'BEGIN');

$updateSql = "
  UPDATE application
  SET status = $1,
      approved_by = $2,
      approved_at = NOW()
  WHERE application_id = $3
";
$updRes = pg_query_params($conn, $updateSql, [$newStatus, $adminUserId, $appId]);

// ensure update actually affected a row
$updatedRows = ($updRes !== false) ? @pg_affected_rows($updRes) : 0;

$insertHistSql = "
  INSERT INTO status_history (application_id, status, changed_by, remarks, created_at)
  VALUES ($1, $2, $3, $4, NOW())
";
$histRes = pg_query_params($conn, $insertHistSql, [$appId, $newStatus, $adminUserId, $remarks]);

if ($updRes === false || $histRes === false || $updatedRows === 0) {
    pg_query($conn, 'ROLLBACK');
    error_log('change_status.php error: ' . pg_last_error($conn));
    // redirect back with error
    header('Location: ' . rtrim(APP_BASE_URL, '/') . "/admin/view_a.php?id={$appId}&msg=error");
    exit;
}

pg_query($conn, 'COMMIT');

// unset CSRF to avoid repeat submission
unset($_SESSION['csrf_token']);

// Redirect with dynamic message
$redirectMsg = ($action === 'approve') ? 'status_approved' : 'status_rejected';
header('Location: ' . rtrim(APP_BASE_URL, '/') . "/admin/view_a.php?id={$appId}&msg={$redirectMsg}");
exit;
