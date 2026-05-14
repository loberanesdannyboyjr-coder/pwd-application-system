<?php
session_start();
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ===============================
   DASHBOARD COUNTS (FIXED)
================================ */
/* OFFICIAL PWD MEMBERS (UNIQUE APPLICANTS) */
$res = pg_query($conn,"
    SELECT COUNT(DISTINCT a.applicant_id)
    FROM application a
    JOIN applicant ap ON ap.applicant_id = a.applicant_id
    WHERE a.workflow_status = 'pdao_approved'
    AND ap.pwd_number IS NOT NULL
    AND ap.pwd_number <> ''
");

$totalPWD = $res ? pg_fetch_result($res,0,0) : 0;


/* READY (CHO Approved → waiting PDAO) */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE workflow_status = 'cho_approved'
");
$totalReady = $res ? pg_fetch_result($res,0,0) : 0;


/* APPROVED */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE workflow_status = 'pdao_approved'
");
$totalApproved = $res ? pg_fetch_result($res,0,0) : 0;

/* REJECTED */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE workflow_status = 'rejected'
");
$totalRejected = $res ? pg_fetch_result($res,0,0) : 0;


/* ===============================
   BREAKDOWN BY TYPE (ONLY RELEVANT STATES)
================================ */

$workflowFilter = "('pdao_approved')";

/* NEW */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='new'
    AND workflow_status IN $workflowFilter
");
$totalNew = $res ? pg_fetch_result($res,0,0) : 0;

/* RENEW */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='renew'
    AND workflow_status IN $workflowFilter
");
$totalRenew = $res ? pg_fetch_result($res,0,0) : 0;

/* LOST */
$res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='lost'
    AND workflow_status IN $workflowFilter
");
$totalLost = $res ? pg_fetch_result($res,0,0) : 0;

/* ===============================
   MONTHLY CHART DATA
================================ */
$months = [];
$newData = [];
$renewData = [];
$lostData = [];

for ($i=11; $i>=0; $i--) {

    $date = new DateTime();
    $date->modify("-$i months");

    $months[] = $date->format("M Y");

    $start = $date->format("Y-m-01");
    $end   = $date->format("Y-m-t");

    /* NEW */
    $res = pg_query($conn,"
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text)='new'
        AND workflow_status IN ('pdao_approved')
        AND application_date BETWEEN '$start' AND '$end'
    ");
    $newData[] = $res ? pg_fetch_result($res,0,0) : 0;

    /* RENEW */
    $res = pg_query($conn,"
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text)='renew'
        AND workflow_status IN ('pdao_approved')
        AND application_date BETWEEN '$start' AND '$end'
    ");
    $renewData[] = $res ? pg_fetch_result($res,0,0) : 0;

    /* LOST */
    $res = pg_query($conn,"
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text)='lost'
        AND workflow_status IN ('pdao_approved')
        AND application_date BETWEEN '$start' AND '$end'
    ");
    $lostData[] = $res ? pg_fetch_result($res,0,0) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PDAO Admin Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/global/base.css">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/global/layout.css">
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/assets/css/global/component.css">

<style>

#mainContent{
    margin-left:64px;
    padding:20px;
    transition:.3s;
}

body.sidebar-expanded #mainContent{
    margin-left:256px;
}

.chart-container{
    height:350px;
    margin-top:20px;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

</style>

</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div id="mainContent">

<!-- TOPBAR -->
<div class="d-flex justify-content-end align-items-center mb-4">
<strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
<i class="fas fa-user-circle ms-3" style="font-size:2rem;"></i>
</div>

<!-- DASHBOARD CARDS -->
<div class="cards">

<div class="card-stat">
<div>
<small>PWDs</small>
<h3><?= $totalPWD ?></h3>
</div>
<i class="fas fa-users"></i>
</div>

<div class="card-stat">
<div>
<small>NEW</small>
<h3><?= $totalNew ?></h3>
</div>
<i class="fas fa-user-plus"></i>
</div>

<div class="card-stat">
<div>
<small>RENEW</small>
<h3><?= $totalRenew ?></h3>
</div>
<i class="fas fa-id-card"></i>
</div>

<div class="card-stat">
<div>
<small>LOST ID</small>
<h3><?= $totalLost ?></h3>
</div>
<i class="fas fa-id-badge"></i>
</div>

</div>


<!-- CHART -->
<div class="chart-container">
<canvas id="statsChart"></canvas>
</div>

</div>



<!-- CHART JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx = document.getElementById('statsChart');

new Chart(ctx,{
type:'line',
data:{
labels: <?= json_encode($months) ?>,
datasets:[

{
label:'New Applications',
data: <?= json_encode($newData) ?>,
borderColor:'#4287f5',
backgroundColor:'rgba(66,135,245,0.3)',
fill:true,
tension:0.3
},

{
label:'Renew Applications',
data: <?= json_encode($renewData) ?>,
borderColor:'#6633ff',
backgroundColor:'rgba(102,51,255,0.3)',
fill:true,
tension:0.3
},

{
label:'Lost ID Applications',
data: <?= json_encode($lostData) ?>,
borderColor:'#FF6384',
backgroundColor:'rgba(255,99,132,0.3)',
fill:true,
tension:0.3
}

]
},
options:{
responsive:true,
maintainAspectRatio:false,
plugins:{
legend:{
position:'top'
}
},
scales:{
y:{
beginAtZero:true
}
}
}
});

</script>


<!-- SIDEBAR SCRIPT -->
<script>

document.querySelectorAll('.submenu-toggle').forEach(btn => {

btn.addEventListener('click', () => {

const parent = btn.closest('.sidebar-item');
const submenu = parent.querySelector('.submenu');
const icon = btn.querySelector('.chevron-icon');

if (submenu.style.maxHeight) {
submenu.style.maxHeight = null;
} else {
submenu.style.maxHeight = submenu.scrollHeight + "px";
}

icon.classList.toggle('rotate');

});

});

</script>

</body>
</html>