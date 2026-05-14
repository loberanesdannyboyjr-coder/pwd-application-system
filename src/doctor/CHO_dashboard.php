<?php
/** CHO Dashboard - displays statistics and charts based on workflow status */

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['doctor','CHO','ADMIN'])) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ===============================
   DASHBOARD STATISTICS
================================ */

$stats = [
    'pwds' => 0,
    'new' => 0,
    'renew' => 0,
    'lost' => 0
];

/* Official PWD members (UNIQUE APPLICANTS ONLY) */
$pwdRes = pg_query($conn,"
    SELECT COUNT(DISTINCT a.applicant_id)
    FROM application a
    JOIN applicant ap ON ap.applicant_id = a.applicant_id
    WHERE a.workflow_status = 'pdao_approved'
    AND ap.pwd_number IS NOT NULL
    AND ap.pwd_number <> ''
");

if($pwdRes){
    $stats['pwds'] = (int) pg_fetch_result($pwdRes,0,0);
}

/* Workflow filter */
$workflowStatuses = ['cho_approved','pdao_approved'];
$workflowFilter = "('" . implode("','",$workflowStatuses) . "')";

/* New */
$newRes = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='new'
    AND workflow_status IN $workflowFilter
");
if($newRes){
    $stats['new'] = (int) pg_fetch_result($newRes,0,0);
}

/* Renewal */
$renewRes = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='renew'
    AND workflow_status IN $workflowFilter
");
if($renewRes){
    $stats['renew'] = (int) pg_fetch_result($renewRes,0,0);
}

/* Lost ID */
$lostRes = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='lost'
    AND workflow_status IN $workflowFilter
");
if($lostRes){
    $stats['lost'] = (int) pg_fetch_result($lostRes,0,0);
}

/* ===============================
   CHART DATA
================================ */

$chartData = [
    'new' => array_fill(0,12,0),
    'renew' => array_fill(0,12,0),
    'lost' => array_fill(0,12,0)
];

$monthLabels = [];

for($i=11;$i>=0;$i--){

    $date = new DateTime();
    $date->modify("-$i months");

    $monthLabels[] = $date->format('M Y');

    $monthStart = $date->format('Y-m-01');
    $monthEnd = $date->format('Y-m-t');

    $idx = 11-$i;

    $res = pg_query($conn,"
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text)='new'
    AND workflow_status IN $workflowFilter
    AND application_date BETWEEN '$monthStart' AND '$monthEnd'
");

    if($res) $chartData['new'][$idx]=(int)pg_fetch_result($res,0,0);

    $res = pg_query($conn,"
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text)='renew'
        AND workflow_status IN $workflowFilter
        AND application_date BETWEEN '$monthStart' AND '$monthEnd'
    ");
    if($res) $chartData['renew'][$idx]=(int)pg_fetch_result($res,0,0);

    $res = pg_query($conn,"
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text)='lost'
        AND workflow_status IN $workflowFilter
        AND application_date BETWEEN '$monthStart' AND '$monthEnd'
    ");
    if($res) $chartData['lost'][$idx]=(int)pg_fetch_result($res,0,0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>CHO Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="../../assets/css/global/base.css">
<link rel="stylesheet" href="../../assets/css/global/layout.css">
<link rel="stylesheet" href="../../assets/css/global/component.css">

</head>

<body>

<div class="layout">

<?php include __DIR__.'/../../includes/cho_sidebar.php'; ?>

<div class="main-content">

<div class="cards">

<div class="card-stat">
<small>PWDs</small>
<h3><?= $stats['pwds'] ?></h3>
</div>

<div class="card-stat">
<small>NEW</small>
<h3><?= $stats['new'] ?></h3>
</div>

<div class="card-stat">
<small>RENEW</small>
<h3><?= $stats['renew'] ?></h3>
</div>

<div class="card-stat">
<small>LOST ID</small>
<h3><?= $stats['lost'] ?></h3>
</div>
</div>

<div class="chart-container">
<canvas id="statsChart"></canvas>
</div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx = document.getElementById('statsChart').getContext('2d');
new Chart(ctx,{
type:'line',

data:{
labels:<?= json_encode($monthLabels) ?>,
datasets:[
{
label:'New Applications',
data:<?= json_encode($chartData['new']) ?>,
backgroundColor:'rgba(66,135,245,0.3)',
borderColor:'#4287f5',
borderWidth:2,
pointRadius:4,
fill:true,
tension:0.3
},
{
label:'Renew Applications',
data:<?= json_encode($chartData['renew']) ?>,
backgroundColor:'rgba(102,51,255,0.3)',
borderColor:'#6633ff',
borderWidth:2,
pointRadius:4,
fill:true,
tension:0.3
},
{
label:'Lost ID Applications',
data:<?= json_encode($chartData['lost']) ?>,
backgroundColor:'rgba(255,99,132,0.3)',
borderColor:'#FF6384',
borderWidth:2,
pointRadius:4,
fill:true,
tension:0.3
}
]
},

options:{
responsive:true,
maintainAspectRatio:false,
scales:{
y:{
beginAtZero:true,
suggestedMax:5,
ticks:{
stepSize:1,
precision:0
}
}
}
}

});

</script>
<script src="../../assets/js/sidebar.js"></script>

<style>
body{
    overflow-x:hidden;
}

/* MAIN CONTENT */
/* NORMAL SIDEBAR */
.main-content{
    flex:1;
    min-height:100vh;
    background:#f5f7fb;
    padding:20px 15px;

    margin-left:64px; /* sidebar collapsed width */
    transition: margin-left .3s;
}

/* WHEN SIDEBAR COLLAPSES */
body.sidebar-collapsed .main-content{
    margin-left:80px;
    width:calc(100% - 80px);
}

body.sidebar-expanded .main-content{
    margin-left:256px; /* sidebar expanded */
}
/* CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:20px;
    margin-bottom:20px;
}

.card-stat{
    background:#3533b4;
    color:white;
    padding:18px 22px;
    border-radius:8px;

    display:flex;             
    justify-content:space-between;
    align-items:center;

    box-shadow:0 4px 10px rgba(0,0,0,0.15);
}

.card-stat small{
    font-size:14px;
    opacity:0.9;
}

.card-stat h3{
    font-size:24px;
    margin:0;
}

/* CHART */
.chart-container{
    background:white;
    padding:20px;
    border-radius:8px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    width:100%;
    height:450px;
    margin-top:10px;
}

.chart-container canvas{
    width:100% !important;
    height:100% !important;
}

.layout{
    display:flex;
    min-height:100vh;
}

/* RESPONSIVE */
@media (max-width:1100px){

.main-content{
    margin-left:80px;
    width:calc(100vw - 80px);
}


}

@media (max-width:600px){


.main-content{
    margin-left:0;
    width:100vw;
    padding:20px;
}

}

</style>


</body>
</html>