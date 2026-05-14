<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../includes/draftMigration.php';

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

$appId = (int)($_GET['id'] ?? 0);

if(!$appId){
    die("Invalid application");
}

/* GET APPLICANT */

$sql = "
SELECT 
a.application_id,
ap.applicant_id,
ap.first_name,
ap.last_name,
ap.middle_name

FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id

WHERE a.application_id = $1
";

$res = pg_query_params($conn,$sql,[$appId]);
$row = pg_fetch_assoc($res);

if(!$row){
    die("Application not found");
}

/* SAVE PWD NUMBER */

if($_SERVER['REQUEST_METHOD']==='POST'){

$pwdNumber = trim($_POST['pwd_number']);

/* UPDATE PWD NUMBER */
pg_query_params($conn,"
UPDATE applicant
SET pwd_number = $1
WHERE applicant_id = $2
",[$pwdNumber,$row['applicant_id']]);

/* RUN DRAFT MIGRATION */
migrateDraftToOfficial($conn, $appId, $row['applicant_id']);

/* FINAL APPROVAL */
pg_query_params($conn,"
UPDATE application
SET workflow_status='pdao_approved'
WHERE application_id=$1
",[$appId]);

header("Location: members.php");
exit;

}

?>

<!DOCTYPE html>
<html>
<head>
<title>Issue PWD ID</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<h4>Issue PWD ID</h4>

<p>
<strong>Name:</strong>
<?= htmlspecialchars($row['last_name'].', '.$row['first_name']) ?>
</p>

<form method="POST">

<div class="mb-3">
<label class="form-label">PWD Number</label>

<input 
type="text"
name="pwd_number"
class="form-control"
placeholder="PWD-2026-00001"
required
>

</div>

<button class="btn btn-success">
Issue PWD ID
</button>

<a href="accepted.php" class="btn btn-secondary">
Cancel
</a>

</form>

</body>
</html>