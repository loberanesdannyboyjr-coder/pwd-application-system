<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';



ini_set('display_errors', 0);
error_reporting(E_ALL);

/* -------------------------------------------------
| AUTH: application_id is enough
--------------------------------------------------*/
if (empty($_SESSION['application_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Session expired'
    ]);
    exit;
}

$session_application_id = (int) $_SESSION['application_id'];

/* -------------------------------------------------
| Read JSON payload
--------------------------------------------------*/
$payload = json_decode(file_get_contents('php://input'), true);
$application_id = (int) ($payload['application_id'] ?? 0);

error_log('SUBMIT application_id=' . $application_id);


if (!$application_id || $application_id !== $session_application_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid application'
    ]);
    exit;
}

/* -------------------------------------------------
| TRANSACTION
--------------------------------------------------*/
pg_query($conn, 'BEGIN');

try {
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

    if (!$upd) {
        throw new Exception('Failed to update application');
    }

    pg_query($conn, 'COMMIT');

    echo json_encode([
        'success' => true
    ]);
    exit;

} catch (Throwable $e) {
    pg_query($conn, 'ROLLBACK');

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage() // TEMP: show exact error
    ]);
    exit;
}
