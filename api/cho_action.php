<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paths.php';  
require_once __DIR__ . '/../src/doctor/generate_certificate.php';

/* ===============================
   AUTHORIZATION
================================= */
if (
    empty($_SESSION['user_id']) ||
    !in_array(strtoupper($_SESSION['role'] ?? ''), ['DOCTOR','CHO','ADMIN'])
) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Not authorized']);
    exit;
}

$cho_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error'=>'Use POST']);
    exit;
}

/* ===============================
   INPUTS
================================= */
$appId = (int)($_POST['application_id'] ?? 0);
$medical_status = trim($_POST['medical_status'] ?? '');
$remarks = trim($_POST['disapprove_reason'] ?? '');

$disability_type = trim($_POST['disability_type'] ?? '');
$certifying_physician = trim($_POST['certifying_physician'] ?? '');
$prc_id = trim($_POST['certifying_prc_id'] ?? '');

if (!$appId || !$medical_status) {
    http_response_code(400);
    echo json_encode(['error'=>'Missing parameters']);
    exit;
}

if (!in_array($medical_status, ['accepted','denied'])) {
    http_response_code(400);
    echo json_encode(['error'=>'Invalid status']);
    exit;
}

/* ===============================
   TRANSACTION START
================================= */
pg_query($conn, 'BEGIN');

try {

    /* ===============================
       LOCK & VALIDATE WORKFLOW
    ================================= */
    $statusCheck = pg_query_params(
        $conn,
        "SELECT workflow_status 
         FROM application 
         WHERE application_id = $1
         FOR UPDATE",
        [$appId]
    );

    if (!$statusCheck || pg_num_rows($statusCheck) === 0) {
        throw new Exception("Application not found.");
    }

    $row = pg_fetch_assoc($statusCheck);

    if ($row['workflow_status'] !== 'cho_review') {
        throw new Exception("Invalid workflow state.");
    }

    /* ======================================================
       BRANCH: MEDICAL APPROVED
    ====================================================== */
    if ($medical_status === 'accepted') {

        // UPSERT disability
        pg_query_params(
            $conn,
            "INSERT INTO disability
                (application_id, disability_type, created_at)
             VALUES ($1, $2, NOW())
             ON CONFLICT (application_id)
             DO UPDATE SET
                 disability_type = EXCLUDED.disability_type,
                 updated_at = NOW()",
            [$appId, $disability_type]
        );

        // UPSERT certification
        pg_query_params(
            $conn,
            "INSERT INTO certification
                (application_id, certifying_physician, license_no, created_at)
             VALUES ($1, $2, $3, NOW())
             ON CONFLICT (application_id)
             DO UPDATE SET
                 certifying_physician = EXCLUDED.certifying_physician,
                 license_no = EXCLUDED.license_no,
                 updated_at = NOW()",
            [$appId, $certifying_physician, $prc_id]
        );

         // Update application status
        pg_query_params(
            $conn,
            "UPDATE application
             SET status = 'CHO Verified',
                 workflow_status = 'cho_approved',
                 updated_at = NOW()
             WHERE application_id = $1",
            [$appId]
        );

        // Generate official certificate
        $certificatePath = generateCertificate($appId, $conn);

        if (!$certificatePath) {
            throw new Exception("Certificate generation failed.");
        }


        // Insert history
        pg_query_params(
            $conn,
            "INSERT INTO application_status_history
                (application_id, from_status, to_status, changed_by, role)
             VALUES ($1, 'For CHO Verification', 'CHO Verified', $2, 'CHO')",
            [$appId, $cho_id]
        );
    }

    $application_id = $_POST['application_id'] ?? null;
    $diagnosis = $_POST['diagnosis'] ?? null;
    $certifying_physician = $_POST['certifying_physician'] ?? null;
    $certifying_prc_id = $_POST['certifying_prc_id'] ?? null;

            $diagnosis = $_POST['diagnosis'] ?? null;

        pg_query_params(
            $conn,
            "UPDATE certification
            SET diagnosis = $1,
                certifying_physician = $2,
                license_no = $3,
                updated_at = NOW()
            WHERE application_id = $4",
            [
                $diagnosis,
                $certifying_physician,
                $certifying_prc_id,
                $application_id
            ]
        );

    /* ======================================================
       BRANCH: MEDICAL REJECTED
    ====================================================== */
    if ($medical_status === 'denied') {

        pg_query_params(
            $conn,
            "UPDATE application
             SET status = 'CHO Rejected',
                 workflow_status = 'rejected',
                 updated_at = NOW()
             WHERE application_id = $1",
            [$appId]
        );

        pg_query_params(
            $conn,
            "INSERT INTO application_status_history
                (application_id, from_status, to_status, changed_by, role, remarks)
             VALUES ($1, 'For CHO Verification', 'CHO Rejected', $2, 'CHO', $3)",
            [$appId, $cho_id, $remarks]
        );
    }

    /*  COMMIT */
    pg_query($conn, 'COMMIT');

    header("Location: " . APP_BASE_URL . "/src/doctor/applications.php?saved=1");
    exit;

} catch (Exception $e) {

    pg_query($conn, 'ROLLBACK');

    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'error'=>$e->getMessage()
    ]);
}