<?php
// api/admin_action.php

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../config/db.php';

$workflow = require __DIR__ . '/../includes/workflow.php';


/* =============================
   VALIDATION
============================= */

$action = $_POST['action'] ?? '';
$appId  = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$csrf   = $_POST['csrf_token'] ?? '';

if (!$action || !$appId) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing action or application_id']);
    exit;
}

if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid CSRF token']);
    exit;
}


/* =============================
   ROLE CHECK
============================= */

$session_role = strtoupper(
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    (!empty($_SESSION['is_admin']) ? 'ADMIN' : '')
);

if (!isset($workflow[$action])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Unknown action']);
    exit;
}

$meta = $workflow[$action];

$allowed = array_map('strtoupper', $meta['allowed_roles'] ?? []);

if (!in_array($session_role, $allowed, true)) {
    http_response_code(403);
    echo json_encode([
        'success'=>false,
        'error'=>'Not allowed for your role'
    ]);
    exit;
}


/* =============================
   REMARKS CHECK
============================= */

$remarks = trim((string)($_POST['remarks'] ?? ''));

if (!empty($meta['require_remarks']) && $remarks === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Remarks required']);
    exit;
}


/* =============================
   WORKFLOW TARGET STATUS
============================= */

$newStatus = $meta['to_status'] ?? null;

if (!$newStatus) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Invalid workflow status']);
    exit;
}


/* =============================
   TRANSACTION
============================= */

pg_query($conn, 'BEGIN');

try {

    // get current status BEFORE updating
    $q = pg_query_params(
        $conn,
        "SELECT workflow_status FROM application WHERE application_id = $1",
        [$appId]
    );

    if (!$q || pg_num_rows($q) === 0) {
        throw new Exception('Application not found');
    }

    $row = pg_fetch_assoc($q);
    $previousStatus = $row['workflow_status'];


    // update application
    $updateSql = "
        UPDATE application
        SET workflow_status = $1,
            remarks = $2,
            updated_at = now()
        WHERE application_id = $3
    ";

    $res = pg_query_params($conn, $updateSql, [
        $newStatus,
        $remarks ?: null,
        $appId
    ]);

    if (!$res) {
        throw new Exception(pg_last_error($conn));
    }


    // insert history
    $insertSql = "
        INSERT INTO application_status_history
        (application_id, from_status, to_status, changed_by, role, remarks, created_at)
        VALUES ($1,$2,$3,$4,$5,$6,now())
    ";

    pg_query_params($conn, $insertSql, [
        $appId,
        $previousStatus,
        $newStatus,
        $_SESSION['user_id'] ?? 0,
        $session_role,
        $remarks ?: null
    ]);


    pg_query($conn, 'COMMIT');

    echo json_encode([
        'success'  => true,
        'redirect' => $meta['redirect'] ?? null
    ]);

} catch (Exception $e) {

    pg_query($conn, 'ROLLBACK');

    http_response_code(500);

    echo json_encode([
        'success'=>false,
        'error'=>$e->getMessage()
    ]);
}