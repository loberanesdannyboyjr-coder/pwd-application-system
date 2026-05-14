<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

/* ===============================
   AUTH CHECK
================================ */

$role = strtoupper($_SESSION['role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ===============================
   QUERY MEMBERS
================================ */

$sql = "
SELECT DISTINCT ON (ap.applicant_id)

    ap.applicant_id,
    ap.pwd_number,

    a.application_id,
    COALESCE(d.data->>'first_name', ap.first_name) AS first_name,
    COALESCE(d.data->>'middle_name', ap.middle_name) AS middle_name,
    COALESCE(d.data->>'last_name', ap.last_name) AS last_name,

    ap.sex::text AS sex,
    COALESCE((d.data->>'birthdate')::date, ap.birthdate) AS birthdate

FROM applicant ap

JOIN application a
ON a.applicant_id = ap.applicant_id

LEFT JOIN application_draft d
ON d.application_id = a.application_id
AND d.step = 1

WHERE
ap.pwd_number IS NOT NULL
AND a.workflow_status = 'pdao_approved'

ORDER BY ap.applicant_id, a.approved_at DESC
";

$res = pg_query($conn,$sql);
$rows = $res ? pg_fetch_all($res) : [];

/* ===============================
   HELPER
================================ */

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<title>PWD Members</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

#mainContent{
    margin-left:64px;
    padding:20px;
    transition:.3s;
}

body.sidebar-expanded #mainContent{
    margin-left:256px;
}

.table thead th{
    background:#4b5563;
    color:white;
}

.badge-pwd{
    font-size:.95rem;
}

</style>

</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div id="mainContent">

<h4 class="mb-3">PWD Members</h4>

<?php if(empty($rows)): ?>

<div class="alert alert-info">
No registered PWD members yet.
</div>

<?php else: ?>

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>
<tr>
<th>PWD Number</th>
<th>Name</th>
<th>Sex</th>
<th>Date of Birth</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($rows as $r):

$name = trim($r['last_name'].', '.$r['first_name'].' '.$r['middle_name']);

$birthdate = !empty($r['birthdate'])
    ? date('M d, Y',strtotime($r['birthdate']))
    : '-';

?>

<tr>

<td>
<span class="badge bg-success badge-pwd">
<?= h($r['pwd_number']) ?>
</span>
</td>

<td><?= h($name) ?></td>

<td><?= h($r['sex']) ?></td>

<td><?= $birthdate ?></td>


<td>

<a href="profile.php?id=<?= urlencode($r['applicant_id']) ?>"
class="btn btn-primary btn-sm">

<i class="fas fa-eye"></i> View

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</body>
</html>