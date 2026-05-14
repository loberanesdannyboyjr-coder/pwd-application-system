<?php
/** Displays denied CHO applications with search and barangay filter. */

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ===============================
   AUTH CHECK
================================*/
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['doctor','CHO','ADMIN'])) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';


/* ===============================
   FILTERS + PAGINATION
================================*/
$search = trim($_GET['q'] ?? $_GET['search'] ?? '');
$barangayFilter = trim($_GET['barangay'] ?? '');

$page = max(1,(int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;


/* ===============================
   BASE QUERY
================================*/
$baseSql = "
SELECT
    a.application_id,
    a.application_date,
    a.status,
    a.application_type,
    a.applicant_id,
    a.workflow_status,
    a.remarks,
    COALESCE(ap.pwd_number,'') AS pwd_number,
    COALESCE(d.data->>'barangay',ap.barangay,'') AS barangay,
    COALESCE(d.data->>'last_name',ap.last_name,'') AS last_name,
    COALESCE(d.data->>'first_name',ap.first_name,'') AS first_name,
    COALESCE(d.data->>'middle_name',ap.middle_name,'') AS middle_name
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id
    AND d.step = 1
WHERE (
    a.workflow_status = 'rejected'
    OR a.status = 'Denied'
)
";

$params = [];
$conds = [];


/* SEARCH */
if ($search !== ''){

    $s = '%' . str_replace('%','\\%',$search) . '%';

    $conds[] = "(
        COALESCE(d.data->>'last_name',ap.last_name) ILIKE $1
        OR COALESCE(d.data->>'first_name',ap.first_name) ILIKE $1
        OR ap.pwd_number ILIKE $1
        OR COALESCE(d.data->>'barangay',ap.barangay) ILIKE $1
    )";

    $params[] = $s;
}


/* BARANGAY FILTER */
if ($barangayFilter !== ''){

    $idx = '$' . (count($params) + 1);

    $conds[] = "COALESCE(d.data->>'barangay',ap.barangay) = {$idx}";

    $params[] = $barangayFilter;
}


if (!empty($conds)){
    $baseSql .= ' AND ' . implode(' AND ', $conds);
}


/* ===============================
   COUNT QUERY
================================*/
$countSql = "
SELECT COUNT(*)
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id
    AND d.step = 1
WHERE (
    a.status = 'Denied'
    OR a.workflow_status = 'denied'
)
";

if (!empty($conds)){
    $countSql .= ' AND ' . implode(' AND ', $conds);
}

$countRes = !empty($params)
    ? pg_query_params($conn,$countSql,$params)
    : pg_query($conn,$countSql);

$total = $countRes ? (int)pg_fetch_result($countRes,0,0) : 0;
$totalPages = max(1,(int)ceil($total/$limit));


/* ===============================
   FINAL QUERY
================================*/
$baseSql .= " ORDER BY a.application_date DESC LIMIT {$limit} OFFSET {$offset}";

$res = !empty($params)
    ? pg_query_params($conn,$baseSql,$params)
    : pg_query($conn,$baseSql);


$rows = [];
$dbErr = null;

if ($res === false){
    $dbErr = pg_last_error($conn);
}else{
    while ($r = pg_fetch_assoc($res)){
        $rows[] = $r;
    }
}


/* ===============================
   BARANGAY LIST
================================*/
$barangaySql = "
SELECT DISTINCT COALESCE(d.data->>'barangay',ap.barangay) AS barangay
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id
    AND d.step = 1
WHERE COALESCE(d.data->>'barangay',ap.barangay) IS NOT NULL
AND COALESCE(d.data->>'barangay',ap.barangay) != ''
ORDER BY barangay
";

$barangays = [];
$barangayRes = pg_query($conn,$barangaySql);

if ($barangayRes){
    while ($b = pg_fetch_assoc($barangayRes)){
        $barangays[] = $b['barangay'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CHO Denied Applications</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="../../assets/css/global/base.css">
<link rel="stylesheet" href="../../assets/css/global/layout.css">
<link rel="stylesheet" href="../../assets/css/global/component.css">

</head>

<body>

<div class="layout">

<?php include __DIR__ . '/../../includes/cho_sidebar.php'; ?>

<div class="main-content">

<div class="container-fluid">


<form method="get" class="d-flex align-items-center mt-3 gap-2 flex-wrap">

<label class="me-1">Search:</label>

<input
type="text"
name="q"
placeholder="Search applicants..."
value="<?= h($search) ?>"
class="form-control"
style="width:220px;"
>

<select name="barangay" class="form-select" style="width:160px;">

<option value="">Barangay</option>

<?php foreach($barangays as $b): ?>

<option value="<?= h($b) ?>" <?= $b === $barangayFilter ? 'selected' : '' ?>>
<?= h($b) ?>
</option>

<?php endforeach; ?>

</select>

<button type="submit" class="btn btn-primary">
<i class="fas fa-search"></i>
</button>

<?php if ($search !== '' || $barangayFilter !== ''): ?>

<a href="denied.php" class="btn btn-outline-secondary">
<i class="fas fa-times"></i> Clear
</a>

<?php endif; ?>

</form>


<?php if ($dbErr): ?>
<div class="alert alert-danger mt-3">
Database error: <?= h($dbErr) ?>
</div>
<?php endif; ?>


<div class="section-header">
<div>LIST OF DENIED APPLICANTS</div>
</div>


<?php if (empty($rows)): ?>

<div class="p-4 bg-white rounded">
<p class="text-muted mb-0">No denied applications found.</p>
</div>

<?php else: ?>

<?php foreach ($rows as $r):

$fullname = trim(($r['first_name'] ?? '').' '.($r['middle_name'] ?? '').' '.($r['last_name'] ?? ''));
$barangay = $r['barangay'] ?: 'N/A';
$viewUrl = 'view_a.php?id='.urlencode($r['application_id']);

?>

<div class="member-list">

<div class="member-info">
<div>
<div><b><?= h($fullname ?: 'Unknown') ?></b></div>
<div style="font-size:12px;"><?= h($barangay) ?>, Iligan City</div>
</div>
</div>

<div class="d-flex align-items-center">

<span
class="badge text-white"
style="background:#dc2626;min-width:140px;padding:0.5rem 0.75rem;"
>
REJECTED
</span>

<a href="<?= h($viewUrl) ?>" class="view-link text-primary text-decoration-none">
<i class="fas fa-eye me-1 ms-5"></i> View Applicant
</a>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>


<?php if ($totalPages > 1): ?>

<div class="pagination">

<?php if ($page > 1): ?>

<a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>" class="btn btn-sm btn-outline-secondary">
&lt; Previous
</a>

<?php else: ?>

<button class="btn btn-sm btn-outline-secondary" disabled>
&lt; Previous
</button>

<?php endif; ?>


<?php
$startPage = max(1,$page-2);
$endPage = min($totalPages,$page+2);

for ($i=$startPage;$i<=$endPage;$i++):
?>

<a
href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>"
class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"
>
<?= $i ?>
</a>

<?php endfor; ?>


<?php if ($page < $totalPages): ?>

<a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>" class="btn btn-sm btn-outline-secondary">
Next &gt;
</a>

<?php else: ?>

<button class="btn btn-sm btn-outline-secondary" disabled>
Next &gt;
</button>

<?php endif; ?>

</div>

<?php endif; ?>

</div>
</div>
</div> 


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../assets/js/sidebar.js"></script>

<style>

.main-content{
    flex:1;
    min-height:100vh;
    background:#f5f7fb;
    padding:20px;

    margin-left:64px;
    transition: margin-left .3s ease;
}

body.sidebar-expanded .main-content{
    margin-left:256px;
}

.rotate{
transform:rotate(180deg);
transition:transform .3s ease;
}

.pagination{
display:flex;
gap:5px;
margin-top:20px;
justify-content:center;
}

</style>

</body>
</html>