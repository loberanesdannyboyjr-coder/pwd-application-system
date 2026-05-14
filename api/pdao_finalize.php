<?php
declare(strict_types=1);

session_start();

require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/paths.php';
require_once __DIR__.'/../../includes/draftMigration.php';

/* ===============================
   AUTH CHECK
================================ */

$role = strtoupper($_SESSION['role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($role,['PDAO','ADMIN'],true)) {
    header('Location: '.APP_BASE_URL.'/backend/auth/login.php');
    exit;
}

/* ===============================
   INPUT
================================ */

$application_id = (int)($_POST['application_id'] ?? 0);
$pwd_number     = trim($_POST['pwd_number'] ?? '');

if(!$application_id || !$pwd_number){
    die("Invalid request.");
}

/* ===============================
   DUPLICATE CHECK
================================ */

$dup = pg_query_params(
    $conn,
    "SELECT applicant_id FROM applicant WHERE pwd_number=$1",
    [$pwd_number]
);

if(pg_num_rows($dup) > 0){
    die("PWD number already exists.");
}

/* ===============================
   GET APPLICATION
================================ */

$appRes = pg_query_params(
    $conn,
    "SELECT applicant_id FROM application WHERE application_id=$1",
    [$application_id]
);

$app = pg_fetch_assoc($appRes);

if(!$app){
    die("Application not found.");
}

$applicant_id = $app['applicant_id'];

/* ===============================
   START TRANSACTION
================================ */

pg_query($conn,"BEGIN");

/* ===============================
   UPDATE PWD NUMBER
================================ */

pg_query_params(
$conn,
"UPDATE applicant
SET pwd_number=$1
WHERE applicant_id=$2",
[
$pwd_number,
$applicant_id
]
);

/* ===============================
   MIGRATE DRAFT DATA
================================ */

migrateDraftToOfficial($conn,$application_id,$applicant_id);

/* ===============================
   FINAL APPROVAL
================================ */

$admin_id = $_SESSION['user_id'] ?? null;

pg_query_params(
$conn,
"UPDATE application
SET
workflow_status='pdao_approved',
approved_at=NOW(),
approved_by=$1
WHERE application_id=$2",
[
$admin_id,
$application_id
]
);

/* ===============================
   COMMIT
================================ */

pg_query($conn,"COMMIT");

/* ===============================
   REDIRECT
================================ */

header("Location: ".APP_BASE_URL."/src/admin_side/members.php");
exit;