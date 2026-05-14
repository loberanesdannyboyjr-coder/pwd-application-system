<?php
session_start();

if (($_SESSION['role'] ?? '') !== 'doctor') {
    http_response_code(401);
    exit("Unauthorized");
}

require_once __DIR__ . '/../../config/db.php';

/* ===============================
   PARAMETERS
================================= */

$status = $_GET['status'] ?? 'pending';
$search = trim($_GET['search'] ?? '');

/* ===============================
   WHERE CLAUSE
================================= */

switch ($status) {
    case 'approved':
        $where = "a.status = 'Approved'";
        break;
    case 'denied':
        $where = "a.status = 'Denied'";
        break;
    case 'pending':
    default:
        $where = "a.workflow_status = 'cho_review'";
        break;
}

$params = [];
$param_idx = 1;

if ($search !== '') {
    $where .= " AND (ap.first_name ILIKE $" . $param_idx . " OR ap.last_name ILIKE $" . $param_idx . ")";
    $params[] = "%$search%";
    $param_idx++;
}

/* ===============================
   FETCH APPLICATIONS
================================= */

$sql = "
    SELECT
        a.application_id,
        a.application_type,
        a.created_at,
        ap.first_name,
        ap.last_name,
        ap.middle_name
    FROM application a
    JOIN applicant ap ON a.applicant_id = ap.applicant_id
    WHERE $where
    ORDER BY a.created_at DESC
";

$result = $params
    ? pg_query_params($conn, $sql, $params)
    : pg_query($conn, $sql);

$applications = [];
while ($row = pg_fetch_assoc($result)) {
    $applications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CHO Applications</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="container mt-4">

<h3 class="mb-3">Applications</h3>

<!-- Search -->
<form class="row g-2 mb-3" method="GET">
    <div class="col-md-4">
        <input type="text"
               name="search"
               class="form-control"
               placeholder="Search applicant name..."
               value="<?= htmlspecialchars($search) ?>">
    </div>

    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="pending" <?= $status=='pending'?'selected':'' ?>>Pending</option>
            <option value="approved" <?= $status=='approved'?'selected':'' ?>>Approved</option>
            <option value="denied" <?= $status=='denied'?'selected':'' ?>>Denied</option>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<!-- Applications Table -->
<table class="table table-bordered table-hover">
<thead class="table-dark">
<tr>
<th>Applicant Name</th>
<th>Application Type</th>
<th>Date Submitted</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php if (empty($applications)): ?>
<tr>
<td colspan="4" class="text-center">No applications found</td>
</tr>
<?php else: ?>

<?php foreach ($applications as $app): ?>

<tr>

<td>
<?= htmlspecialchars($app['last_name']) ?>,
<?= htmlspecialchars($app['first_name']) ?>
<?= htmlspecialchars($app['middle_name']) ?>
</td>

<td><?= htmlspecialchars($app['application_type']) ?></td>

<td><?= date("M d, Y", strtotime($app['created_at'])) ?></td>

<td>
<a class="btn btn-sm btn-outline-primary"
href="view_applicant.php?id=<?= $app['application_id'] ?>">
View Application
</a>
</td>

</tr>

<?php endforeach; ?>
<?php endif; ?>

</tbody>
</table>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>