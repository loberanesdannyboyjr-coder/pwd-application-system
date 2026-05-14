<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$workflow = require __DIR__ . '/../includes/workflow.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Not authorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$userRole = strtoupper($_SESSION['role']);

$appId  = (int) ($_POST['application_id'] ?? 0);
$action = $_POST['action'] ?? '';
$remarks = trim($_POST['remarks'] ?? '');

if (!$appId || !$action || !isset($workflow[$action])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

$rule = $workflow[$action];

/* ---------- ROLE CHECK ---------- */
if (!in_array($userRole, $rule['allowed_roles'], true)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Unauthorized action']);
    exit;
}

/* ---------- REMARKS CHECK ---------- */
if (!empty($rule['require_remarks']) && $remarks === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Remarks required']);
    exit;
}

pg_query($conn, 'BEGIN');

try {

    $res = pg_query_params(
        $conn,
        "SELECT workflow_status FROM application WHERE application_id=$1 FOR UPDATE",
        [$appId]
    );

    if (!$res || pg_num_rows($res) === 0) {
        throw new Exception('Application not found');
    }

    $row = pg_fetch_assoc($res);
    $fromStatus = $row['workflow_status'];

    /* ---------- SPECIAL: FINAL PDAO APPROVAL ---------- */
if ($action === 'final_approve') {

    $pwdId = trim($_POST['final_pwd_id'] ?? '');

    if ($pwdId === '') {
        throw new Exception('PWD ID is required for final approval');
    }

    // Get applicant_id
    $appRes = pg_query_params(
        $conn,
        "SELECT applicant_id FROM application WHERE application_id = $1",
        [$appId]
    );

    $appRow = pg_fetch_assoc($appRes);
    $applicantId = $appRow['applicant_id'] ?? null;

    // Update application with final details
    $finalUpd = pg_query_params(
        $conn,
        "UPDATE application
         SET final_pwd_id = $1,
             approved_by = $2,
             approved_at = CURRENT_TIMESTAMP
         WHERE application_id = $3",
        [$pwdId, $userId, $appId]
    );

    if (!$finalUpd) {
        throw new Exception('Failed to save final approval details');
    }

    // Update applicant table
    if ($applicantId) {
        pg_query_params(
            $conn,
            "UPDATE applicant
             SET pwd_number = $1
             WHERE applicant_id = $2",
            [$pwdId, $applicantId]
        );
    }
}


    /* ---------- UPDATE APPLICATION ---------- */
    $upd = pg_query_params(
        $conn,
        "UPDATE application
         SET workflow_status = $1,
             remarks = $2,
             updated_at = CURRENT_TIMESTAMP
         WHERE application_id = $3",
        [
            $rule['to_status'],
            $remarks ?: null,
            $appId
        ]
    );

    if (!$upd) {
        throw new Exception('Failed to update application');
    }

    /* ---------- INSERT HISTORY ---------- */
    pg_query_params(
        $conn,
        "INSERT INTO application_status_history
         (application_id, from_status, to_status, changed_by, role, remarks)
         VALUES($1,$2,$3,$4,$5,$6)",
        [
            $appId,
            $fromStatus,
            $rule['to_status'],
            $userId,
            $userRole,
            $remarks ?: null
        ]
    );

    pg_query($conn, 'COMMIT');

    echo json_encode([
        'success' => true,
        'message' => 'Application is now ' . $rule['to_status'],
        'redirect' => $rule['redirect'] ?? null
    ]);

    exit;

} catch (Exception $e) {

    pg_query($conn, 'ROLLBACK');
    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>$e->getMessage()
    ]);
    exit;
}
