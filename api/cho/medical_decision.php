<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!in_array($_SESSION['role'] ?? '', ['CHO','DOCTOR','ADMIN'], true)) {
    http_response_code(403);
    die('Unauthorized');
}

if (empty($_POST['application_id'])) {
    http_response_code(400);
    die('Missing application ID');
}

$application_id = (int) $_POST['application_id'];

/* ================= READ FORM DATA ================= */
$medical_status = $_POST['medical_status'] ?? '';
$pending_reason = trim($_POST['pending_reason'] ?? '');
$assessing_physician = trim($_POST['assessing_physician'] ?? '');
$prc_id = trim($_POST['prc_id'] ?? '');
$disability_type = trim($_POST['disability_type'] ?? '');

/* ==================================================
   🔒 VALIDATION (THIS IS WHERE YOUR CODE GOES)
================================================== */
if (in_array($medical_status, ['pending','denied','accepted'], true)) {
    if ($assessing_physician === '' || $prc_id === '') {
        die('Assessing physician and PRC ID are required.');
    }
}

if ($medical_status === 'pending' && $pending_reason === '') {
    die('Pending reason is required.');
}

if ($medical_status === 'accepted' && $disability_type === '') {
    die('Disability type is required.');
}

/* ================= SAVE TO DRAFT ================= */
$data = [
    'medical_status' => $medical_status,
    'pending_reason' => $pending_reason,
    'assessing_physician' => $assessing_physician,
    'prc_id' => $prc_id,
    'disability_type' => $disability_type,
];

// Save as JSON (example)
pg_query_params(
    $conn,
    "INSERT INTO application_draft (application_id, data)
     VALUES ($1, $2::jsonb)",
    [$application_id, json_encode($data)]
);

/* ================= UPDATE APPLICATION ================= */
$status_map = [
    'pending'  => 'Pending',
    'denied'   => 'Denied',
    'accepted' => 'Approved'
];

pg_query_params(
    $conn,
    "UPDATE application
     SET status = $1,
         updated_at = CURRENT_TIMESTAMP
     WHERE application_id = $2",
    [$status_map[$medical_status] ?? 'Pending', $application_id]
);

/* ================= DONE ================= */
header('Location: ../../src/doctor/applications.php');
exit;
