<?php
session_start();
require_once '../../config/db.php';

$applicant_id = $_SESSION['applicant_id'];

$_SESSION['application_type'] = 'lost';

/* REQUIRE APPROVED PWD */
$approved = pg_query_params(
    $conn,
    "SELECT application_id
     FROM application
     WHERE applicant_id = $1
     AND workflow_status = 'pdao_approved'
     LIMIT 1",
    [$applicant_id]
);

if (pg_num_rows($approved) === 0) {
    die("You must have an approved PWD ID before requesting lost ID.");
}

/* PREVENT DUPLICATE */
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

    $res = pg_query_params(
        $conn,
        "INSERT INTO application (applicant_id, application_type, created_at)
         VALUES ($1,'lost',NOW())
         RETURNING application_id",
        [$applicant_id]
    );

    $_SESSION['application_id'] = pg_fetch_result($res,0,'application_id');
}

header("Location: lost_uploads.php");
exit;