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
   INPUTS
================================ */
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

/* ===============================
   QUERY
================================ */
$where = ["a.workflow_status IN ('rejected','cho_rejected','cho_denied')"];
$params = [];
$paramIndex = 1;

/* SEARCH */
if ($search !== '') {
    $where[] = "(
        COALESCE(d.data->>'first_name', ap.first_name) ILIKE $" . $paramIndex . " OR
        COALESCE(d.data->>'last_name', ap.last_name) ILIKE $" . $paramIndex . "
    )";
    $params[] = "%$search%";
    $paramIndex++;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

/* COUNT */
$countSql = "
SELECT COUNT(*)
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d ON d.application_id = a.application_id AND d.step = 1
$whereSql
";

$countRes = pg_query_params($conn, $countSql, $params);
$total = $countRes ? (int)pg_fetch_result($countRes, 0, 0) : 0;
$totalPages = max(1, ceil($total / $limit));

/* FETCH */
$sql = "
SELECT
    a.application_id,
    a.application_type,
    a.application_date,

    COALESCE(d.data->>'first_name', ap.first_name) AS first_name,
    COALESCE(d.data->>'middle_name', ap.middle_name) AS middle_name,
    COALESCE(d.data->>'last_name', ap.last_name) AS last_name

FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d ON d.application_id = a.application_id AND d.step = 1

$whereSql
ORDER BY a.application_date DESC
LIMIT $" . $paramIndex++ . " OFFSET $" . $paramIndex++;

$params[] = $limit;
$params[] = $offset;

$res = pg_query_params($conn, $sql, $params);
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
<title>Disapproved Applications</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
#mainContent {
    margin-left: 64px;
    padding: 20px;
    transition: margin-left .3s ease;
}
body.sidebar-expanded #mainContent {
    margin-left: 256px;
}
.table thead th {
    background: #4b5563;
    color: white;
}
.view-link {
    white-space: nowrap;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.pagination {
    display: flex;
    gap: 5px;
    justify-content: center;
    margin-top: 20px;
}
.badge-danger {
    background: #dc3545;
}
</style>

</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div id="mainContent">

<h4 class="mb-3">Disapproved Applications</h4>

<!-- SEARCH -->
<form method="get" class="d-flex gap-2 mb-3">
    <input type="text" name="q" value="<?= h($search) ?>"
           class="form-control" placeholder="Search...">

    <button class="btn btn-primary">
        <i class="fas fa-search"></i>
    </button>

    <?php if ($search): ?>
        <a href="denied.php" class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
</form>

<div class="mb-2"><strong>Total:</strong> <?= $total ?></div>

<!-- TABLE -->
<?php if (empty($rows)): ?>
<div class="alert alert-info">No disapproved applications.</div>
<?php else: ?>

<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
    <th>Name</th>
    <th>Type</th>
    <th>Date</th>
    <th>Status</th>
    <th>Action</th>
</tr>
</thead>

<tbody>
<?php foreach ($rows as $r):
    $name = trim($r['last_name'] . ', ' . $r['first_name'] . ' ' . $r['middle_name']);
?>
<tr>
<td><?= h($name) ?></td>
<td><?= h(ucfirst($r['application_type'])) ?></td>
<td><?= date('M d, Y', strtotime($r['application_date'])) ?></td>

<td>
    <span class="badge bg-danger">Disapproved</span>
</td>

<td>
    <a href="view_a.php?id=<?= urlencode($r['application_id']) ?>" class="view-link">
        <i class="fas fa-eye"></i> View
    </a>
</td>
</tr>
<?php endforeach; ?>
</tbody>

</table>
</div>

<?php endif; ?>

<!-- PAGINATION -->
<?php if ($totalPages > 1): ?>
<div class="pagination">

<?php if ($page > 1): ?>
<a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">Prev</a>
<?php endif; ?>

<?php for ($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"
class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline-secondary' ?>">
<?= $i ?>
</a>
<?php endfor; ?>

<?php if ($page < $totalPages): ?>
<a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">Next</a>
<?php endif; ?>

</div>
<?php endif; ?>

</div>

</body>
</html>