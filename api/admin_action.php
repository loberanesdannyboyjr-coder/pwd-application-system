<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/db.php';
$workflow = include __DIR__ . '/../includes/workflow.php';

/* ---------------- INPUT ---------------- */
$action = $_POST['action'] ?? '';
$appId  = (int)($_POST['application_id'] ?? 0);
$csrf   = $_POST['csrf_token'] ?? '';

if ($action === '' || $appId <= 0) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing action or application_id']);
    exit;
}

/* ---------------- CSRF ---------------- */
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
    exit;
}

/* ---------------- ROLE ---------------- */
$session_role = strtoupper(
    $_SESSION['role']
    ?? $_SESSION['user_role']
    ?? (!empty($_SESSION['is_admin']) ? 'ADMIN' : '')
);

/* ---------------- WORKFLOW ---------------- */
if (!isset($workflow[$action])) {
    http_response_code(400);
    echo json_encode([
        'success'=>false,
        'error'=>'Unknown action',
        'received_action'=>$action
    ]);
    exit;
}

$meta = $workflow[$action];
$allowed = array_map('strtoupper', $meta['allowed_roles'] ?? []);

if (!in_array($session_role, $allowed, true)) {
    http_response_code(403);
    echo json_encode([
        'success'=>false,
        'error'=>'Not allowed for your role',
        'role'=>$session_role,
        'allowed'=>$allowed
    ]);
    exit;
}

/* ---------------- REMARKS ---------------- */
$remarks = trim($_POST['remarks'] ?? '');
if (!empty($meta['require_remarks']) && $remarks === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Remarks required']);
    exit;
}

/* ---------------- TRANSACTION ---------------- */
pg_query($conn, 'BEGIN');

/* Get previous status FIRST */
$q = pg_query_params(
    $conn,
    "SELECT workflow_status FROM application WHERE application_id = $1 LIMIT 1",
    [$appId]
);
if (!$q || pg_num_rows($q) === 0) {
    pg_query($conn, 'ROLLBACK');
    http_response_code(404);
    echo json_encode(['success'=>false,'error'=>'Application not found']);
    exit;
}
$prev = pg_fetch_result($q, 0, 'workflow_status');

/* Update status */
$newStatus = $meta['to_status'];

$u = pg_query_params(
    $conn,
    "UPDATE application
     SET workflow_status = $1, updated_at = now()
     WHERE application_id = $2",
    [$newStatus, $appId]
);

if (!$u || pg_affected_rows($u) === 0) {
    pg_query($conn, 'ROLLBACK');
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to update application']);
    exit;
}

/* Insert history */
$i = pg_query_params(
    $conn,
    "INSERT INTO application_status_history
     (application_id, from_status, to_status, changed_by, role, remarks, created_at)
     VALUES ($1,$2,$3,$4,$5,$6,now())",
    [
        $appId,
        $prev,
        $newStatus,
        $_SESSION['user_id'] ?? 0,
        $session_role,
        $remarks
    ]
);

if (!$i) {
    pg_query($conn, 'ROLLBACK');
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Failed to insert history']);
    exit;
}

pg_query($conn, 'COMMIT');

echo json_encode([
    'success'=>true,
    'redirect'=>$meta['redirect'] ?? null
]);
exit;
