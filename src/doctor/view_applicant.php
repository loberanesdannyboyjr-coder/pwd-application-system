<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

function h($s){
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ===============================
   AUTH
   =============================== */
$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['username']) || !in_array($role, ['doctor','CHO','ADMIN'], true)) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

/* ===============================
   VALIDATE application_id
   =============================== */
$application_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$application_id) {
    echo 'Invalid application.';
    exit;
}

/* ===============================
   FETCH APPLICATION + APPLICANT + DRAFT
   =============================== */
$sql = "
SELECT
    a.application_id,
    a.application_type,
    a.application_date,
    a.pic_1x1_path,
    ap.applicant_id,
    ap.first_name,
    ap.middle_name,
    ap.last_name,
    ap.barangay,
    d.data AS draft_data
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
       ON d.application_id = a.application_id
WHERE a.application_id = $1
ORDER BY d.updated_at DESC
LIMIT 1
";

$res = pg_query_params($conn, $sql, [$application_id]);
$row = pg_fetch_assoc($res);

if (!$row) {
    echo 'Invalid application.';
    exit;
}

$applicant_id = $row['applicant_id'];

/* ===============================
   PARSE DRAFT JSON (SAFE)
   =============================== */
$draft = [];
if (!empty($row['draft_data'])) {
    $json = json_decode($row['draft_data'], true);
    if (is_array($json)) {
        $draft = $json;
    }
}

/* ===============================
   PHOTO URL
   =============================== */
$photo_url = '';
if (!empty($row['pic_1x1_path'])) {
    $photo_url = rtrim(APP_BASE_URL, '/') . '/' . ltrim($row['pic_1x1_path'], '/');
}

/* ===============================
   FETCH APPLICATION HISTORY
   =============================== */
$applications = [];
$hist_sql = "
SELECT application_id, application_type, application_date
FROM application
WHERE applicant_id = $1
ORDER BY application_date DESC
";
$hist_res = pg_query_params($conn, $hist_sql, [$applicant_id]);
while ($hist_res && $r = pg_fetch_assoc($hist_res)) {
    $applications[] = $r;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Applicant Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* ===== LABELS ===== */
.form-label,
label {
  font-weight: 700;
  color: #0d3b66;
}

/* ===== DISABLED INPUT LOOK ===== */
.form-control:disabled {
  background: #f8f9fa;
  color: #333;
}

/* ===== SECTION HEADERS ===== */
.section-header {
  background: #f1f8fc;
  border-left: 4px solid #0d6efd;
  padding: 12px 16px;
  font-weight: 700;
  color: #0d3b66;
  margin-top: 24px;
  margin-bottom: 16px;
}

/* ===== HISTORY ITEMS ===== */
.history-item {
  background: #f5f5f5;
  border-radius: 6px;
  padding: 14px 16px;
  margin-bottom: 10px;
  font-weight: 600;
  color: #0d3b66;
}

/* ===== PHOTO ===== */
.photo-box {
  width: 110px;
  height: 110px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.photo-box img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 6px;
}

/* ===== BACK BUTTON ===== */
.btn-outline-secondary {
  font-weight: 600;
}

/* ===== REMOVE VISUAL CLUTTER ===== */
.card {
  border-radius: 10px;
}
</style>

</head>

<body>

<?php include __DIR__ . '/../../hero/navbar_admin.php'; ?>

<div class="container my-4">

<a href="applications.php" class="btn btn-outline-secondary mb-4">← Back</a>

<div class="card shadow-sm border-0">
<div class="card-body">

<!-- BASIC INFO -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <label>Date Applied</label>
    <input class="form-control"
           value="<?= $row['application_date'] ? date('Y-m-d', strtotime($row['application_date'])) : '' ?>"
           disabled>
  </div>

  <div class="col-md-3">
    <label>Patient ID</label>
    <input class="form-control" value="<?= h($row['application_id']) ?>" disabled>
  </div>

  <div class="col-md-6 text-end">
    <div class="photo-box ms-auto">
      <?php if ($photo_url): ?>
        <img src="<?= h($photo_url) ?>" alt="1x1 Photo">
      <?php else: ?>
        <div class="border p-3 text-muted small">1x1 Photo</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- NAME ROW -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label">Last Name</label>
    <input class="form-control" value="<?= h($row['last_name']) ?>" disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">First Name</label>
    <input class="form-control" value="<?= h($row['first_name']) ?>" disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Middle Name</label>
    <input class="form-control" value="<?= h($row['middle_name']) ?>" disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Suffix</label>
    <input class="form-control" value="" disabled>
  </div>
</div>

<!-- DOB / SEX / CIVIL STATUS -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label">Date of Birth</label>
    <input class="form-control" value="<?= h($draft['date_of_birth'] ?? '') ?>" disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Sex</label>
    <input class="form-control" value="<?= h($draft['sex'] ?? '') ?>" disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Civil Status</label>
    <input class="form-control" value="<?= h($draft['civil_status'] ?? '') ?>" disabled>
  </div>
</div>

<!-- ADDRESS + MOBILE NO -->
<div class="row g-3 mb-3">
  <div class="col-md-8">
    <label class="form-label">Address</label>
    <input class="form-control"
           value="<?= h($row['barangay']) ?>, Iligan City"
           disabled>
  </div>

  <div class="col-md-4">
    <label class="form-label">Mobile No.</label>
    <input class="form-control"
           value="<?= h($draft['mobile_no'] ?? '') ?>"
           disabled>
  </div>
</div>

<!-- EMAIL + NATIONAL ID -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <label class="form-label">E-mail Address</label>
    <input class="form-control"
           value="<?= h($draft['email'] ?? '') ?>"
           disabled>
  </div>

  <div class="col-md-4">
    <label class="form-label">National ID</label>
    <input class="form-control"
           value="<?= h($draft['national_id'] ?? '') ?>"
           disabled>
  </div>
</div>

<div class="section-header mb-3">IN CASE OF EMERGENCY</div>
<div class="row g-3 mb-4">
  <div class="col-md-6"><input class="form-control" disabled></div>
  <div class="col-md-6"><input class="form-control" disabled></div>
</div>

<div class="section-header mb-3">PWD APPLICATION HISTORY</div>

<?php foreach ($applications as $a): ?>
<a href="view_a_medical.php?id=<?= h($a['application_id']) ?>" class="text-decoration-none text-dark">
  <div class="history-item d-flex justify-content-between">
    <strong><?= strtoupper(h($a['application_type'])) ?> APPLICATION</strong>
    <span class="text-muted">
      <?= $a['application_date'] ? date('F d, Y', strtotime($a['application_date'])) : '—' ?>
    </span>
  </div>
</a>
<?php endforeach; ?>

</div>
</div>
</div>

</body>
</html>