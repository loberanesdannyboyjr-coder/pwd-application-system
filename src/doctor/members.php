<?php
/** Displays list of PWD members with avatar, age, search, and download functionality. */

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
================================ */

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['doctor','CHO','ADMIN'])) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';

/* ===============================
   SEARCH + PAGINATION
================================ */

$search = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));

$limit = 10;
$offset = ($page-1) * $limit;

$baseSql = "
SELECT
    ap.applicant_id,
    ap.pwd_number,
    a.pic_1x1_path,
    COALESCE(d.data->>'first_name',ap.first_name,'') AS first_name,
    COALESCE(d.data->>'middle_name',ap.middle_name,'') AS middle_name,
    COALESCE(d.data->>'last_name',ap.last_name,'') AS last_name,
    COALESCE(d.data->>'barangay',ap.barangay,'') AS barangay,
    COALESCE((d.data->>'birthdate')::date,ap.birthdate) AS birthdate,
    a.application_id

FROM applicant ap
JOIN application a ON a.applicant_id = ap.applicant_id
LEFT JOIN application_draft d
ON d.application_id = a.application_id AND d.step = 1

WHERE a.workflow_status='pdao_approved'
AND ap.pwd_number IS NOT NULL
";

$params=[];
$conds=[];

if($search!==''){
    $s='%'.$search.'%';

    $conds[]="(
        COALESCE(d.data->>'last_name',ap.last_name) ILIKE $1
        OR COALESCE(d.data->>'first_name',ap.first_name) ILIKE $1
        OR ap.pwd_number ILIKE $1
        OR COALESCE(d.data->>'barangay',ap.barangay) ILIKE $1
    )";

    $params[]=$s;
}

if(!empty($conds)){
    $baseSql .= ' AND '.implode(' AND ',$conds);
}

/* COUNT */

$countSql="
SELECT COUNT(*)
FROM applicant ap
JOIN application a ON a.applicant_id = ap.applicant_id
LEFT JOIN application_draft d
ON d.application_id = a.application_id AND d.step=1

WHERE a.workflow_status='pdao_approved'
AND ap.pwd_number IS NOT NULL
";

if(!empty($conds)){
    $countSql.=' AND '.implode(' AND ',$conds);
}

$countRes=!empty($params)
? pg_query_params($conn,$countSql,$params)
: pg_query($conn,$countSql);

$total=$countRes?(int)pg_fetch_result($countRes,0,0):0;
$totalPages=max(1,ceil($total/$limit));

$baseSql.=" ORDER BY ap.last_name ASC LIMIT $limit OFFSET $offset";

$res=!empty($params)
? pg_query_params($conn,$baseSql,$params)
: pg_query($conn,$baseSql);

$rows=[];

while($r=pg_fetch_assoc($res)){
    $rows[]=$r;
}

/* AGE */

function calculateAge($dob){
    if(!$dob) return null;
    $birth=new DateTime($dob);
    $today=new DateTime();
    return $birth->diff($today)->y;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>CHO PWD Members</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link rel="stylesheet" href="../../assets/css/global/base.css">
<link rel="stylesheet" href="../../assets/css/global/layout.css">
<link rel="stylesheet" href="../../assets/css/global/component.css">

</head>

<body>

<div class="layout">

<?php include __DIR__.'/../../includes/cho_sidebar.php'; ?>


<div class="main-content">

<!-- SEARCH BAR -->

<form method="get"
class="search-container d-flex align-items-center justify-content-between mt-3 mb-3">

<div class="d-flex align-items-center">

<label class="me-2">Search:</label>

<input
type="text"
name="q"
value="<?= h($search) ?>"
placeholder="Search PWDs..."
class="form-control"
style="width:300px;">

<button class="btn btn-primary ms-2">
<i class="fas fa-search"></i>
</button>

<?php if($search!==''): ?>

<a href="members.php"
class="btn btn-outline-secondary ms-2">
<i class="fas fa-times"></i> Clear
</a>

<?php endif; ?>

</div>

<a href="download_members.php"
class="btn btn-outline-primary">

<i class="fas fa-download me-1"></i>
Download

</a>

</form>


<div class="section-header">
List of PWD Members
</div>


<?php if(empty($rows)): ?>

<div class="p-4 bg-white rounded">
<p class="text-muted">No PWD members found.</p>
</div>

<?php else: ?>

<?php foreach($rows as $r):

$fullname=trim(($r['first_name']??'').' '.($r['middle_name']??'').' '.($r['last_name']??''));

$age=calculateAge($r['birthdate']);

$ageText=$age!==null?$age.' yrs old':'Age unknown';

$initials=strtoupper(substr($r['first_name'],0,1).substr($r['last_name'],0,1));

$viewUrl='view_a.php?id='.urlencode($r['application_id']);

?>

<div class="member-list">

<div class="member-info d-flex align-items-center">

<div class="avatar-circle me-3">

<?php if(!empty($r['pic_1x1_path'])): ?>

<img src="../../uploads/<?= h($r['pic_1x1_path']) ?>" class="avatar-img">

<?php else: ?>

<?= h($initials) ?>

<?php endif; ?>

</div>
<div>
    <div><b><?= h($fullname) ?></b></div>
    <div class="text-muted small"><?= h($ageText) ?></div>
</div>

</div>

<a href="<?= h($viewUrl) ?>"
class="view-link text-primary text-decoration-none">

<i class="fas fa-eye me-1"></i>
View Member Profile

</a>

</div>

<?php endforeach; ?>

<?php endif; ?>


<!-- PAGINATION -->

<?php if($totalPages>1): ?>

<div class="pagination mt-3">

<?php if($page>1): ?>

<a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>"
class="btn btn-sm btn-outline-secondary">
Previous
</a>

<?php endif; ?>

<?php for($i=1;$i<=$totalPages;$i++): ?>

<a
href="?page=<?= $i ?>&q=<?= urlencode($search) ?>"
class="btn btn-sm <?= $i==$page?'btn-primary':'btn-outline-secondary' ?>">

<?= $i ?>

</a>

<?php endfor; ?>

<?php if($page<$totalPages): ?>

<a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>"
class="btn btn-sm btn-outline-secondary">
Next
</a>

<?php endif; ?>

</div>

<?php endif; ?>


</div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="../../assets/js/sidebar.js"></script>


<style>

    .main-content{
    flex:1;
    min-height:100vh;
    background:#f5f7fb;

    padding:20px 20px;

    margin-left:64px;   /* width of collapsed sidebar */
    transition: margin-left .3s ease;
}

/* When sidebar expands */
body.sidebar-expanded .main-content{
    margin-left:256px;
}

.member-list{
display:flex;
justify-content:space-between;
align-items:center;
padding:16px 20px;
background:white;
border-bottom:1px solid #eee;
}

.member-list:hover{
background:#f9fafb;
}

.pagination{
display:flex;
gap:5px;
justify-content:center;
}

.avatar-circle{
width:50px;
height:50px;
border-radius:50%;
overflow:hidden;
background:linear-gradient(135deg,#667eea,#764ba2);
display:flex;
align-items:center;
justify-content:center;
color:white;
font-weight:600;
}

.avatar-img{
width:100%;
height:100%;
object-fit:cover;
}


</style>

</body>
</html>