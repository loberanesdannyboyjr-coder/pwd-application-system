<?php
// api/submit_application.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

// 🔒 Turn OFF HTML error output
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* -------------------------------------------------
| AUTH (FIXED)
--------------------------------------------------*/
if (empty($_SESSION['applicant_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

$applicant_id = (int) $_SESSION['applicant_id'];

/* -------------------------------------------------
| GET application_id (JSON only)
--------------------------------------------------*/
$payload = json_decode(file_get_contents('php://input'), true);

$application_id = (int) ($payload['application_id'] ?? 0);

if (!$application_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'application_id required'
    ]);
    exit;
}

/* -------------------------------------------------
| TRANSACTION
--------------------------------------------------*/
pg_query($conn, 'BEGIN');

try {
    // Lock application
    $res = pg_query_params(
        $conn,
        "SELECT workflow_status
         FROM application
         WHERE application_id = $1
         FOR UPDATE",
        [$application_id]
    );

    if (!$res || pg_num_rows($res) === 0) {
        throw new Exception('Application not found');
    }

    $row = pg_fetch_assoc($res);
    $from = $row['workflow_status'] ?? 'draft';

    // Prevent double submit
    if ($from === 'submitted') {
        pg_query($conn, 'ROLLBACK');
        echo json_encode([
            'success' => true,
            'already_submitted' => true
        ]);
        exit;
    }

$upd = pg_query_params(
    $conn,
    "UPDATE application
     SET application_date = CURRENT_DATE,
         status = 'Pending',
         workflow_status = 'submitted',
         updated_at = CURRENT_TIMESTAMP
     WHERE application_id = $1",
    [$application_id]
);

if (!$upd || pg_affected_rows($upd) === 0) {
    throw new Exception('Failed to update application');
}

/* =========================================
   COPY STEP 1 DATA → applicant
========================================= */
$resDraft = pg_query_params(
    $conn,
    "SELECT data
     FROM application_draft
     WHERE application_id = $1 AND step = 1
     LIMIT 1",
    [$application_id]
);

if ($resDraft && pg_num_rows($resDraft) > 0) {

    $draftRow = pg_fetch_assoc($resDraft);
    $data = json_decode($draftRow['data'], true);

    pg_query_params(
        $conn,
        "UPDATE applicant SET
            first_name      = $1,
            middle_name     = $2,
            last_name       = $3,
            birthdate       = $4,
            sex             = $5,
            civil_status    = $6,
            house_no_street = $7,
            barangay        = $8,
            municipality    = $9,
            province        = $10,
            region          = $11,
            landline_no     = $12,
            mobile_no       = $13,
            email_address   = $14,
            updated_at      = NOW()
        WHERE applicant_id = $15",
        [
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
            $data['birthdate'] ?? null,
            $data['sex'] ?? null,
            $data['civil_status'] ?? null,
            $data['house_no_street'] ?? null,
            $data['barangay'] ?? null,
            $data['municipality'] ?? null,
            $data['province'] ?? null,
            $data['region'] ?? null,
            $data['landline_no'] ?? null,
            $data['mobile_no'] ?? null,
            $data['email_address'] ?? null,
            $applicant_id
        ]
    );
}


    // History
    $hist = pg_query_params(
        $conn,
        "INSERT INTO application_status_history
         (application_id, from_status, to_status, changed_by, role, remarks)
         VALUES ($1, $2, 'submitted', $3, 'applicant', $4)",
        [
            $application_id,
            $from,
            $applicant_id,
            'Applicant submitted final application'
        ]
    );

    if (!$hist) {
        throw new Exception('Failed to write history');
    }

    pg_query($conn, 'COMMIT');

    echo json_encode([
        'success' => true,
        'application_id' => $application_id
    ]);
    exit;

} catch (Throwable $e) {
    pg_query($conn, 'ROLLBACK');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'details' => $e->getMessage()
    ]);
    exit;
}
