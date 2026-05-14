<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

/* =================================================
   Error handling (log only, no screen output)
================================================= */
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* =================================================
   AUTH: require valid session application_id
================================================= */
if (empty($_SESSION['application_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Session expired. Please log in again.'
    ]);
    exit;
}

$sessionApplicationId = (int) $_SESSION['application_id'];

/* =================================================
   Read JSON payload
================================================= */
$payload = json_decode(file_get_contents('php://input'), true);
$applicationId = (int) ($payload['application_id'] ?? 0);

error_log('PWD SUBMIT: application_id=' . $applicationId);

if (!$applicationId || $applicationId !== $sessionApplicationId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => 'Invalid application request.'
    ]);
    exit;
}

/* =================================================
   TRANSACTION
================================================= */
pg_query($conn, 'BEGIN');

try {

    /* Lock row to prevent double submit */
    $res = pg_query_params(
        $conn,
        "SELECT workflow_status
         FROM application
         WHERE application_id = $1
         FOR UPDATE",
        [$applicationId]
    );

    if (!$res || pg_num_rows($res) === 0) {
        throw new Exception('Application not found.');
    }

    $row = pg_fetch_assoc($res);
    $currentStatus = strtolower($row['workflow_status'] ?? 'draft');

    /* Already submitted / already under review */
    if (in_array($currentStatus, ['pdao_review','cho_review','cho_approved','pdao_approved','rejected'])) {
        pg_query($conn, 'ROLLBACK');

        $_SESSION['application_submitted'] = true;

        echo json_encode([
            'success'           => true,
            'already_submitted' => true
        ]);
        exit;
    }


    /* Update application */
    $upd = pg_query_params(
        $conn,
        "UPDATE application
        SET application_date = CURRENT_DATE,
            status = 'Pending',
            workflow_status = 'pdao_review',
            updated_at = CURRENT_TIMESTAMP
        WHERE application_id = $1",
        [$applicationId]
    );


    if (!$upd) {
        throw new Exception('Failed to update application.');
    }

    pg_query($conn, 'COMMIT');

    /* Allow access to confirmation page */
    $_SESSION['application_submitted'] = true;

    echo json_encode([
        'success' => true
    ]);
    exit;

} catch (Throwable $e) {

    pg_query($conn, 'ROLLBACK');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Submission failed. Please try again.'
        // For debugging only:
        // 'debug' => $e->getMessage()
    ]);
    exit;
}
