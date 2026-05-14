<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';
require_once __DIR__ . '/../../includes/audit_log.php';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

function build_url($path){
    if (!$path) return '';
    if (parse_url($path, PHP_URL_SCHEME)) return $path;
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
}

$role = strtoupper($_SESSION['role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ===============================
   VALIDATE ID
================================ */
if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    exit('Invalid application');
}

$app_id = (int) $_GET['id'];

logAction($conn, 'Viewed application', $app_id);

function normalize_row($row): array {
    if (!is_array($row)) return []; // 🔥 prevent crash
    $out = [];
    foreach ($row as $k => $v) {
        $k = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $k));
        $out[$k] = $v;
    }
    return $out;
}

/* ===============================
   FETCH MAIN
================================ */
$application = [];
$applicant_id = null;

$res = pg_query_params($conn,"
SELECT a.*, ap.*
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
WHERE a.application_id = $1
LIMIT 1
",[$app_id]);

if ($res && pg_num_rows($res)) {
    $application = pg_fetch_assoc($res);
    $applicant_id = $application['applicant_id'];
} else {
    exit('Application not found');
}

/*  FETCH ALL TABLES*/
$docs = [];
$aff = [];
$cert = [];
$em = [];
$disability = [];
$family = [];
$acc = [];

/* DOCUMENTS */
$res = pg_query_params($conn,"SELECT * FROM documentrequirements WHERE application_id=$1",[$app_id]);
if ($res && pg_num_rows($res)) $docs = pg_fetch_assoc($res);

/* AFFILIATION */
$res = pg_query_params($conn,"SELECT * FROM affiliation WHERE applicant_id=$1",[$applicant_id]);
if ($res && pg_num_rows($res)) $aff = pg_fetch_assoc($res);

/* CERTIFICATION */
$res = pg_query_params($conn,"SELECT * FROM certification WHERE application_id=$1",[$app_id]);
if ($res && pg_num_rows($res)) $cert = pg_fetch_assoc($res);

/* EMERGENCY */
$res = pg_query_params($conn,"SELECT * FROM emergencycontact WHERE applicant_id=$1",[$applicant_id]);
if ($res && pg_num_rows($res)) $em = pg_fetch_assoc($res);

/* FAMILY BACKGROUND */
$res = pg_query_params($conn,"SELECT * FROM familybackground WHERE applicant_id=$1",[$applicant_id]);
if ($res && pg_num_rows($res)) $family = pg_fetch_assoc($res);

/* ACCOMPLISHED BY */
$res = pg_query_params($conn,"SELECT * FROM accomplishedby WHERE application_id=$1",[$app_id]);
if ($res && pg_num_rows($res)) $acc = pg_fetch_assoc($res);

/* DISABILITY + CAUSE */
$res = pg_query_params($conn,"
SELECT d.disability_type, cd.cause_detail
FROM disability d
LEFT JOIN causedetail cd 
    ON cd.cause_detail_id = d.cause_detail_id
WHERE d.application_id = $1
LIMIT 1
",[$app_id]);

if ($res && pg_num_rows($res)) {
    $disability = pg_fetch_assoc($res);
}

/* ===============================
   FILES
================================ */
$fileMap = [
    'bodypic_path' => 'Whole Body Picture',
    'barangaycert_path' => 'Barangay Certificate',
    'medicalcert_path' => 'Medical Certificate',
    'proof_disability_path' => 'Proof of Disability',
    'old_pwd_id_path' => 'Old PWD ID',
    'affidavit_loss_path' => 'Affidavit of Loss' 
];

$files = [];

foreach ($fileMap as $key => $label) {
    if (!empty($docs[$key])) {
        $files[] = [
            'label' => $label,
            'path' => $docs[$key]
        ];
    }
}

/* file download */
if (!empty($_GET['file_action']) && in_array($_GET['file_action'], ['view','download'])) {

    $requested = basename($_GET['file'] ?? '');

    foreach ($files as $f) {
        if (basename($f['path']) === $requested) {

            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($f['path'], '/');

            if (is_file($filePath)) {

                header('Content-Type: ' . mime_content_type($filePath));
                header('Content-Disposition: ' .
                    ($_GET['file_action'] === 'view' ? 'inline' : 'attachment') .
                    '; filename="' . basename($filePath) . '"');

                readfile($filePath);
                exit;
            }
        }
    }

    http_response_code(404);
    exit;
}

/*   MERGE DATA */
$draftData = array_merge(
    normalize_row($application),
    normalize_row($docs),
    normalize_row($aff),
    normalize_row($cert),
    normalize_row($em),
    normalize_row($disability),
    normalize_row($family),
    normalize_row($acc)
);

/* ===============================
   FIXED FIELDS
================================ */

/* disability */
if (!empty($disability['disability_type'])) {
    $draftData['disability_label'] = $disability['disability_type'];
}

/* cause */
if (!empty($disability['cause_detail'])) {
    $draftData['cause_description'] = $disability['cause_detail'];
    $draftData['cause'] = stripos($disability['cause_detail'], 'congenital') !== false
        ? 'Congenital/Inborn'
        : 'Acquired';
}

/* accomplished by normalize */
if (!empty($draftData['accomplished_by'])) {
    $draftData['accomplished_by'] = strtolower($draftData['accomplished_by']);
}

$pic_candidate = '';

if (!empty($docs['pic_1x1_path'])) {
    $pic_candidate = $docs['pic_1x1_path'];
} elseif (!empty($application['pic_1x1_path'])) {
    $pic_candidate = $application['pic_1x1_path'];
} elseif (!empty($draftData['pic_1x1_path'])) {
    $pic_candidate = $draftData['pic_1x1_path'];
}

$draftData['pic_url'] = $pic_candidate ? build_url($pic_candidate) : '';

/* files */
$draftData['files'] = $files;

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
body { background:#f6f7f9; }
#mainContent { margin-left:260px; padding:30px; }
.form-summary { max-width:1100px; margin:auto; }

</style>
</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div id="mainContent">

<style>
body { background:#f4f6fb; }

/* Header */
.page-header{
    background:white;
    padding:18px 22px;
    border-radius:10px;
    box-shadow:0 2px 8px rgba(0,0,0,0.05);
    margin-bottom:16px;
}

body.sidebar-expanded #mainContent{
    margin-left:256px;
}

/* Status badge */
.status-badge{
    padding:6px 14px;
    border-radius:999px;
    font-size:0.8rem;
    font-weight:600;
}

/* Section card */
.section-card{
    border-radius:10px;
    border:none;
    box-shadow:0 3px 10px rgba(0,0,0,0.06);
}

/* Header gradient */
.card-header-custom{
    background:linear-gradient(90deg,#2d6be6,#5b9df7);
    color:white;
    font-weight:600;
    border-top-left-radius:10px;
    border-top-right-radius:10px;
}

/* Back button */
.back-btn{
    text-decoration:none;
    font-weight:500;
}

.section-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: #2563eb;
    text-transform: uppercase;

    background: #f1f5f9;
    border-left: 4px solid #2563eb;

    padding: 10px 14px;
    border-radius: 6px;

    margin-top: 20px;
    margin-bottom: 12px;
}
</style>

<div class="container-fluid">

<?php
$status = strtolower($application['workflow_status'] ?? '');

[$statusLabel, $statusColor] = match($status){
    'pdao_approved' => ['Approved','success'],
    'cho_approved'  => ['Ready for Approval','primary'],
    'cho_review'    => ['Under Review','warning'],
    'rejected'      => ['Rejected','danger'],
    default         => ['Draft','secondary'],
};
?>

<!-- HEADER -->
<div class="page-header d-flex justify-content-between align-items-center">

    <div>
        <h5 class="fw-bold mb-1">Application Review</h5>
        <small class="text-muted">
            Application ID: <?= h($app_id) ?>
        </small>
    </div>

    <span class="badge bg-<?= $statusColor ?> status-badge">
        <?= $statusLabel ?>
    </span>

</div>

<!-- BACK -->
<a href="members.php" class="back-btn text-primary d-inline-flex align-items-center mb-3">
    <i class="bi bi-arrow-left me-2"></i> Back to Members
</a>

<!-- MAIN CONTENT -->
<div class="container-lg">

    <div class="card section-card">

        <div class="card-header card-header-custom">
            Application Details
        </div>

        <div class="card-body">
            <?php include __DIR__ . '/partials/form5_readonly.php'; ?>
        </div>

    </div>

</div>

</div>
</div>

</body>
</html>