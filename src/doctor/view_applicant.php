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
JOIN applicant ap 
    ON ap.applicant_id = a.applicant_id
LEFT JOIN (
    SELECT application_id,
           jsonb_object_agg(key, value) AS data
    FROM (
        SELECT ad.application_id, key, value
        FROM application_draft ad,
        jsonb_each(ad.data::jsonb)
    ) merged
    GROUP BY application_id
) d ON d.application_id = a.application_id
WHERE a.application_id = $1
LIMIT 1
";


$res = pg_query_params($conn, $sql, [$application_id]);

if (!$res) {
    die("Query failed: " . pg_last_error($conn));
}

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
$photo_path = $draft['pic_1x1_path'] ?? $row['pic_1x1_path'] ?? '';
$photo_url = '';

if (!empty($photo_path)) {
    $photo_url = rtrim(APP_BASE_URL, '/') . '/' . ltrim($photo_path, '/');
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

body {
  background: #f1f3f5; /* grayish background */
}

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

.profile-title {
    color: #14255A;
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
  <div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10 col-md-11">

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-0">
          <h4 class="fw-bold text-center my-3 profile-title">
              Applicant Profile
          </h4>
        </div>

        <div class="card-body p-4">


<!-- HEADER ROW: Back + Photo -->
<div class="d-flex justify-content-between align-items-start mb-4">

  <!-- Back Button -->
  <a href="applications.php"
     class="btn btn-outline-secondary btn-sm px-4">
     ← Back
  </a>

  <!-- 1x1 Photo -->
  <div class="photo-box">
    <?php if ($photo_url): ?>
      <img src="<?= h($photo_url) ?>" alt="1x1 Photo">
    <?php else: ?>
      <div class="border p-3 text-muted small">1x1 Photo</div>
    <?php endif; ?>
  </div>

</div>


<!-- NAME -->
<div class="row mb-3">
  <div class="col-md-3">
    <label class="form-label">Last Name</label>
    <input class="form-control" value="<?= h($draft['last_name'] ?? $row['last_name']) ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">First Name</label>
    <input class="form-control" value="<?= h($draft['first_name'] ?? $row['first_name']) ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">Middle Name</label>
    <input class="form-control" value="<?= h($draft['middle_name'] ?? $row['middle_name']) ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">Suffix</label>
    <input class="form-control" disabled>
  </div>
</div>

<!-- DOB / SEX / CIVIL / EDUC -->
<div class="row mb-3">
  <div class="col-md-3">
    <label class="form-label">Date of Birth</label>
    <input class="form-control" value="<?= h($draft['birthdate'] ?? '') ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">Sex</label>
    <input class="form-control" value="<?= h($draft['sex'] ?? '') ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">Civil Status</label>
    <input class="form-control" value="<?= h($draft['civil_status'] ?? '') ?>" disabled>
  </div>
  <div class="col-md-3">
    <label class="form-label">Educational Attainment</label>
    <input class="form-control" value="<?= h($draft['educational_attainment'] ?? '') ?>" disabled>
  </div>
</div>

<!-- ADDRESS / MOBILE -->
<div class="row mb-3">
  <div class="col-md-8">
    <label class="form-label">Address</label>
    <input class="form-control"
           value="<?= h($draft['barangay'] ?? $row['barangay']) ?>, Iligan City"
           disabled>
  </div>
  <div class="col-md-4">
    <label class="form-label">Mobile No.</label>
    <input class="form-control" value="<?= h($draft['mobile_no'] ?? '') ?>" disabled>
  </div>
</div>

<!-- EMAIL  -->
<div class="row mb-4">
  <div class="col-md-8">
    <label class="form-label">E-mail Address</label>
    <input class="form-control" value="<?= h($draft['email_address'] ?? '') ?>" disabled>
  </div>
</div>


<!-- ================= EMERGENCY ================= -->
<div class="section-header">IN CASE OF EMERGENCY</div>

<div class="row mb-4 mt-3">
  <div class="col-md-6">
    <label class="form-label">Contact Person's Name</label>
    <input class="form-control"
value="<?= h($draft['contact_person_name'] ?? '') ?>" disabled>
  </div>
  <div class="col-md-6">
    <label class="form-label">Contact Person's No.</label>   
<input class="form-control"
value="<?= h($draft['contact_person_no'] ?? '') ?>" disabled>
  </div>
</div>


<!-- ================= HISTORY ================= -->
<div class="section-header">PWD APPLICATION HISTORY</div>

<div class="mt-3">
<?php foreach ($applications as $a): ?>
  <a href="view_a_medical.php?id=<?= h($a['application_id']) ?>" class="text-decoration-none text-dark">
    <div class="history-item d-flex justify-content-between align-items-center">
      <span><strong>PWD ID <?= strtoupper(h($a['application_type'])) ?> APPLICATION</strong></span>
      <span class="text-muted">
        <?= $a['application_date'] ? date('F d, Y', strtotime($a['application_date'])) : '—' ?>
        →
      </span>
    </div>
  </a>
<?php endforeach; ?>
</div>

</div>


        </div>
      </div>

    </div>
  </div>
</div>


 </body>
 </html>