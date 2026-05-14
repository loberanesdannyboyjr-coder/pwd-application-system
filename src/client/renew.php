<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'], $_SESSION['applicant_id'])) {
    header('Location: ../../public/login_form.php');
    exit;
}

$applicant_id = (int) $_SESSION['applicant_id'];

$_SESSION['application_type'] = 'renew';

/* ===============================
   REQUIRE APPROVED PWD FIRST
================================ */

$approved = pg_query_params(
    $conn,
    "SELECT application_id
     FROM application
     WHERE applicant_id = $1
     AND workflow_status = 'pdao_approved'
     LIMIT 1",
    [$applicant_id]
);

if (!$approved || pg_num_rows($approved) === 0) {
    die("You must have an approved PWD ID before renewal.");
}


/* ===============================
   PREVENT MULTIPLE PENDING
================================ */

$pending = pg_query_params(
    $conn,
    "SELECT application_id
     FROM application
     WHERE applicant_id = $1
     AND workflow_status NOT IN ('pdao_approved','rejected')
     LIMIT 1",
    [$applicant_id]
);

if ($pending && pg_num_rows($pending) > 0) {

    $row = pg_fetch_assoc($pending);
    $_SESSION['application_id'] = $row['application_id'];

} else {

    /* CREATE NEW RENEWAL APPLICATION */

    $res = pg_query_params(
        $conn,
        "INSERT INTO application (applicant_id, application_type, created_at)
         VALUES ($1,'Renewal',NOW())
         RETURNING application_id",
        [$applicant_id]
    );

    if (!$res) {
        die("Database error: " . pg_last_error($conn));
    }

    $_SESSION['application_id'] = pg_fetch_result($res, 0, 'application_id');
}

/* ===============================
   REDIRECT TO FORM 1
================================ */

header("Location: form1.php?type=renew");
exit;