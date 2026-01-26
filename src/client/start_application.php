<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['applicant_id'])) {
    header('Location: /public/login_form.php');
    exit;
}

$applicant_id = (int)$_SESSION['applicant_id'];
$type = strtolower($_GET['type'] ?? 'new');
$valid = ['new','renew','lost'];

if (!in_array($type, $valid, true)) {
    http_response_code(400);
    echo "Invalid application type.";
    exit;
}

// Always create a new draft application row
$result = pg_query_params($conn,
    "INSERT INTO application (applicant_id, appl    ication_type, status, created_at)
     VALUES ($1, $2, 'draft', NOW()) RETURNING application_id",
    [$applicant_id, $type]
);

if (!$result) {
    http_response_code(500);
    echo "Database error: " . pg_last_error($conn);
    exit;
}

$application_id = (int)pg_fetch_result($result, 0, 'application_id');

$_SESSION['application_id']   = $application_id;
$_SESSION['application_type'] = $type;

// Redirect to Form 1
header("Location: /src/client/form1.php?type=" . urlencode($type));
exit;
