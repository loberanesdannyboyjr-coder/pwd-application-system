<?php
session_start();
require_once '../../config/db.php';

/* ===============================
   AUTH CHECK
================================ */
$role = strtoupper($_SESSION['role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ===============================
   VALIDATE INPUT
================================ */
$application_id = isset($_GET['id']) && ctype_digit($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if (!$application_id) {
    die("Invalid request");
}

/* ===============================
   FETCH APPLICATION
================================ */
$res = pg_query_params($conn,
    "SELECT applicant_id, workflow_status
     FROM application
     WHERE application_id = $1",
    [$application_id]
);

if (!$res || pg_num_rows($res) === 0) {
    die("Application not found");
}

$data = pg_fetch_assoc($res);

/* Prevent double approval */
if ($data['workflow_status'] === 'pdao_approved') {
    die("Already approved");
}

$applicant_id = $data['applicant_id'];

/* ===============================
   TRANSACTION
================================ */
pg_query($conn, "BEGIN");

/* Activate applicant */
pg_query_params($conn,
    "UPDATE applicant
     SET pwd_status = 'active'
     WHERE applicant_id = $1",
    [$applicant_id]
);

/* Update application */
pg_query_params($conn,
    "UPDATE application
     SET workflow_status = 'pdao_approved',
         approved_at = NOW()
     WHERE application_id = $1",
    [$application_id]
);

pg_query($conn, "COMMIT");

/* ===============================
   REDIRECT
================================ */
header("Location: accepted.php");
exit;