<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* AUTH */
$role = strtoupper($_SESSION['role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
      header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* GET ID */
$applicant_id = (int)($_GET['id'] ?? 0);
if (!$applicant_id) exit('Invalid applicant');

/* FETCH APPLICANT */
$res = pg_query_params($conn,"
SELECT *
FROM applicant
WHERE applicant_id = $1
",[$applicant_id]);

$app = pg_fetch_assoc($res);
if(!$app) exit('Applicant not found');

/* FETCH HISTORY */
$applications = [];
$hist = pg_query_params($conn,"
SELECT application_id, application_type, application_date, workflow_status
FROM application
WHERE applicant_id = $1
ORDER BY created_at DESC
",[$applicant_id]);

$hist = pg_query_params($conn,"
SELECT application_id, application_type, application_date, workflow_status
FROM application
WHERE applicant_id = $1
AND workflow_status <> 'draft'
ORDER BY created_at DESC
",[$applicant_id]);

while($hist && $r = pg_fetch_assoc($hist)){
    $applications[] = $r;
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Applicant Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
body {
    background:#f1f3f5;
}

.profile-title {
    color:#14255A;
}

.form-label {
    font-weight:700;
    color:#0d3b66;
}

.form-control:disabled {
    background:#f9fafb;
    font-weight:500;
}

.section-header {
    background:#eef5fb;
    border-left:4px solid #0d6efd;
    padding:12px 16px;
    font-weight:700;
    color:#0d3b66;
    margin-top:24px;
    margin-bottom:16px;
    border-radius:6px;
}

.history-card {
    background:#ffffff;
    border-radius:8px;
    padding:14px 16px;
    margin-bottom:10px;
    border:1px solid #e5e7eb;
    transition:0.2s ease;
}

.history-card:hover {
    background:#f8fafc;
    transform:translateY(-2px);
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

/* ================= BADGES ================= */

.badge-soft {
    padding: 6px 14px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.75rem;
    display: inline-block;
}

/* Completed */
.badge-success {
    background-color: rgba(25, 135, 84, 0.15) !important;
    color: #198754 !important;
}

/* Draft */
.badge-secondary {
    background-color: rgba(108, 117, 125, 0.15) !important;
    color: #6c757d !important;
}

/* CHO Approved */
.badge-info {
    background-color: rgba(13, 110, 253, 0.15) !important;
    color: #0d6efd !important;
}

/* Rejected */
.badge-danger {
    background-color: rgba(220, 53, 69, 0.15) !important;
    color: #dc3545 !important;
}

/* Pending */
.badge-warning {
    background-color: rgba(255, 193, 7, 0.2) !important;
    color: #d39e00 !important;

}

</style>
</head>

<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>

<div class="container my-4">
<div class="row justify-content-center">
<div class="col-xl-9 col-lg-10 col-md-11">

<div class="card shadow-sm border-0">

<div class="card-header bg-white border-0">
<h4 class="fw-bold text-center my-3 profile-title">
Applicant Profile
</h4>
</div>

<div class="card-body p-4">

<a href="members.php" class="btn btn-outline-secondary btn-sm mb-3">
← Back
</a>

<!-- NAME -->
<div class="row mb-3">
<div class="col-md-3">
<label class="form-label">Last Name</label>
<input class="form-control" value="<?= h($app['last_name']) ?>" disabled>
</div>

<div class="col-md-3">
<label class="form-label">First Name</label>
<input class="form-control" value="<?= h($app['first_name']) ?>" disabled>
</div>

<div class="col-md-3">
<label class="form-label">Middle Name</label>
<input class="form-control" value="<?= h($app['middle_name']) ?>" disabled>
</div>

<div class="col-md-3">
<label class="form-label">Suffix</label>
<input class="form-control" value="<?= h($app['suffix'] ?? '') ?>" disabled>
</div>
</div>

<!-- DOB / SEX / CIVIL -->
<div class="row mb-3">
<div class="col-md-3">
<label class="form-label">Date of Birth</label>
<input class="form-control"
value="<?= !empty($app['birthdate']) ? date('F d, Y', strtotime($app['birthdate'])) : '' ?>"
disabled>
</div>

<div class="col-md-3">
<label class="form-label">Sex</label>
<input class="form-control" value="<?= h($app['sex']) ?>" disabled>
</div>

<div class="col-md-3">
<label class="form-label">Civil Status</label>
<input class="form-control" value="<?= h($app['civil_status'] ?? '') ?>" disabled>
</div>

<div class="col-md-3">
<label class="form-label">PWD Number</label>
<input class="form-control" value="<?= h($app['pwd_number']) ?>" disabled>
</div>
</div>

<!-- ADDRESS -->
<div class="row mb-3">
<div class="col-md-8">
<label class="form-label">Address</label>
<input class="form-control" value="<?= h($app['barangay'] ?? '') ?>, Iligan City" disabled>
</div>

<div class="col-md-4">
<label class="form-label">Mobile No.</label>
<input class="form-control" value="<?= h($app['mobile_no'] ?? '') ?>" disabled>
</div>
</div>

<!-- EMAIL -->
<div class="row mb-4">
<div class="col-md-8">
<label class="form-label">Email Address</label>
<input class="form-control" value="<?= h($app['email_address'] ?? '') ?>" disabled>
</div>
</div>

<!-- HISTORY -->
<div class="section-header">PWD APPLICATION HISTORY</div>

<?php if(empty($applications)): ?>

<div class="text-muted">No history found</div>

<?php else: ?>

<?php foreach($applications as $a):

$type = strtolower($a['application_type'] ?? '');
$status = strtolower($a['workflow_status'] ?? '');

/* TYPE LABEL */
$label = match ($type) {
    'renew' => 'Renewal Application',
    'lost'  => 'Lost ID Application',
    default => 'New Application',
};

/* STATUS LABEL + COLOR */
[$statusLabel, $statusColor] = match ($status) {
    'pdao_approved' => ['Completed', 'success'],
    'cho_approved'  => ['CHO Approved', 'info'],
    'draft'         => ['Draft', 'secondary'],
    'rejected'      => ['Rejected', 'danger'],
    default         => ['Pending', 'warning'],
};
?>

<a href="view_application.php?id=<?= h($a['application_id']) ?>" 
   class="text-decoration-none text-dark">

<div class="history-card d-flex justify-content-between align-items-center">

    <div>
        <div class="fw-bold text-primary">
            <?= $label ?>
        </div>

        <div class="small text-muted">
            <?= !empty($a['application_date']) 
                ? date('F d, Y', strtotime($a['application_date'])) 
                : 'No submission date' ?>
        </div>
    </div>

    <div>
        <span class="badge-soft badge-<?= $statusColor ?>">
                <?= $statusLabel ?>
            </span>
    </div>

</div>

</a>

<?php endforeach; ?>

<?php endif; ?>

</div>
</div>
</div>
</div>
</div>

</body>
</html>