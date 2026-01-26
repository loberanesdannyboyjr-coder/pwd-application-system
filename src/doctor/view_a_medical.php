<?php
// src/doctor/view_a_medical.php
// CHO / Doctor — MEDICAL ONLY VIEW (Backup / New UI)

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$session_user_id = $_SESSION['user_id'] ?? null;
$session_role = strtoupper($_SESSION['role'] ?? '');

if (empty($session_user_id) || !in_array($session_role, ['CHO','DOCTOR','ADMIN'], true)) {
    header('Location: ' . rtrim(APP_BASE_URL, '/') . '/src/auth/signin.php');
    exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE); }

$app_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$app_id) {
    echo '<div class="alert alert-danger">Invalid application ID.</div>';
    exit;
}

/* ===============================
   FETCH APPLICATION
   =============================== */
$sql = "SELECT a.*, ap.*
        FROM application a
        LEFT JOIN applicant ap ON a.applicant_id = ap.applicant_id
        WHERE a.application_id = $1
        LIMIT 1";
$res = pg_query_params($conn, $sql, [$app_id]);
$app = pg_fetch_assoc($res);
if (!$app) {
    echo '<div class="alert alert-warning">Application not found.</div>';
    exit;
}

/* ===============================
   FETCH DOCUMENTS
   =============================== */
$docs = null;
$docs_res = pg_query_params(
    $conn,
    "SELECT * FROM documentrequirements WHERE application_id = $1 LIMIT 1",
    [$app_id]
);
if ($docs_res && pg_num_rows($docs_res)) {
    $docs = pg_fetch_assoc($docs_res);
}

/* ===============================
   FETCH DRAFT (MEDICAL DATA)
   =============================== */
$draft = [];
$draft_res = pg_query_params(
    $conn,
    "SELECT data FROM application_draft WHERE application_id = $1 ORDER BY updated_at DESC",
    [$app_id]
);
while ($r = pg_fetch_assoc($draft_res)) {
    $json = json_decode($r['data'], true);
    if (is_array($json)) {
        $draft = array_merge($draft, $json);
    }
}

/* ===============================
   NORMALIZE DATA
   =============================== */
$data = array_merge(
    array_change_key_case($app, CASE_LOWER),
    array_change_key_case($docs ?? [], CASE_LOWER),
    array_change_key_case($draft, CASE_LOWER)
);

/* ===============================
   DATE APPLIED (SAFE)
   =============================== */
$dateApplied = '';

// Priority order
if (!empty($app['application_date'])) {
    $dateApplied = $app['application_date'];
} elseif (!empty($app['created_at'])) {
    $dateApplied = $app['created_at'];
} elseif (!empty($data['application_date'])) {
    $dateApplied = $data['application_date'];
}

// Format for display (dd/mm/yyyy)
if ($dateApplied) {
    $ts = strtotime($dateApplied);
    if ($ts !== false) {
        $dateApplied = date('d/m/Y', $ts);
    }
}


/* ===============================
   FILE SERVING — MED CERT (VIEW ONLY)
================================ */
if (
    ($_GET['file_action'] ?? '') === 'view' &&
    !empty($_GET['file'])
) {
    $requested = basename($_GET['file']);
    $stored = $data['medicalcert_path'] ?? '';

    if (!$stored || basename($stored) !== $requested) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    $candidates = [
        '/var/www/html/PWD-Application-System/uploads/' . $requested,
        __DIR__ . '/../../uploads/' . $requested
    ];

    $filePath = null;
    foreach ($candidates as $p) {
        if (file_exists($p)) {
            $filePath = $p;
            break;
        }
    }

    if (!$filePath) {
        http_response_code(404);
        echo 'File not available.';
        exit;
    }

    $mime = 'application/pdf';
    if (function_exists('mime_content_type')) {
        $m = mime_content_type($filePath);
        if ($m) $mime = $m;
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    @ob_end_clean();
    readfile($filePath);
    exit;
}

/* ===============================
   1x1 PHOTO
   =============================== */
$pic = $data['pic_1x1_path'] ?? '';
$pic_url = $pic ? rtrim(APP_BASE_URL,'/') . '/' . ltrim($pic,'/') : '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CHO Medical Assessment</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body{
  background:#f6f7f9;
}

/* =========================
   1x1 PHOTO
========================= */
.photo-box{
  width:110px;
  height:110px;
  display:flex;
  align-items:center;
  justify-content:center;
}

.photo-box img{
  width:100%;
  height:100%;
  object-fit:cover;
  border-radius:6px; /* optional */
}

/* =========================
   UPLOAD BOX (MED + PWD)
========================= */
.upload-box{
  width:100%;
  min-height:140px;              /* SAME SIZE */
  border:1px solid #e0e0e0;
  border-radius:6px;
  padding:28px;
  background:#f6f6f6;
  cursor:pointer;

  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;

  transition:0.2s ease;
}

.upload-box:hover{
  background:#ededed;
}

/* =========================
   TEXT COLOR (GLOBAL)
========================= */
.text-primary-custom,
.upload-box,
.upload-box *{
  color:#14255A;
}

/* =========================
   PRIMARY BUTTON
========================= */
.btn-primary,
.btn-primary:hover,
.btn-primary:focus{
  background-color:#14255A !important;
  border-color:#14255A !important;
}

/* =========================
   TOGGLE / RADIO BUTTONS
========================= */
.btn-check:checked + .btn{
  background-color:#14255A !important;
  border-color:#14255A !important;
  color:#fff !important;
}

.btn-outline-secondary:hover,
.btn-outline-warning:hover,
.btn-outline-success:hover{
  background-color:#14255A !important;
  border-color:#14255A !important;
  color:#fff !important;
}

/* =========================
   FORM LABELS & HEADINGS
========================= */
.form-label,
.section-title,
.upload-label{
  font-weight:700;
  color:#14255A;
}

/* Upload section titles */
label.form-label{
  margin-bottom:6px;
}

/* Radio / toggle group titles */
.form-label.d-block{
  font-weight:700;
  color:#14255A;
}

/* PWD certificate text */
.upload-box,
.upload-box span,
.upload-box div{
  font-weight:600;
  color:#14255A;
}

.toggle-group {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.toggle-group input[type="radio"] {
  display: none;
}

.toggle-btn {
  min-width: 160px;
  padding: 12px 18px;
  text-align: center;
  border: 1px solid #dcdcdc;
  background: #f5f5f5;
  color: #000;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

/* Hover */
.toggle-btn:hover {
  background: #ff8c3a;
  color: #fff;
  border-color: #ff8c3a;
}

/* Selected */
.toggle-group input[type="radio"]:checked + .toggle-btn {
  background: #ff8c3a;
  color: #fff;
  border-color: #ff8c3a;
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
    <h4 class="fw-bold text-center text-primary my-3">
      PWD New Application
    </h4>
  </div>

  <div class="card-body p-4">

<!-- HEADER ROW -->
<div class="row align-items-start mb-4">

  <!-- Back button -->
<div class="d-flex justify-content-start mb-3">
  <a href="<?= h(
      rtrim(APP_BASE_URL, '/') .
      '/src/doctor/view_applicant.php?id=' .
      urlencode($app_id)
  ) ?>"
  class="btn btn-outline-secondary btn-sm px-4">
    ← Back
  </a>
</div>



  <!-- EMPTY SPACER COLUMN -->




<form method="post"
      action="<?= h(rtrim(APP_BASE_URL,'/') . '/api/cho_medical_action.php') ?>"
      enctype="multipart/form-data">

<input type="hidden" name="application_id" value="<?= h($app_id) ?>">
<input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

<!-- ROW 1: Date Applied | Patient ID | Photo -->
<div class="row align-items-start g-3 mb-3">

  <div class="col-md-3">
    <label class="form-label">Date Applied</label>
    <input type="text"
           class="form-control"
           value="<?= h($dateApplied) ?>"
           disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Patient ID</label>
    <input type="text"
           class="form-control"
           value="<?= h($app['pwd_number'] ?? '') ?>"
           disabled>
  </div>

  <div class="col-md-4"></div>

  <div class="col-md-2 d-flex justify-content-end">
    <div class="photo-box">
      <?php if ($pic_url): ?>
        <img src="<?= h($pic_url) ?>" alt="1x1 Photo">
      <?php else: ?>
        <span class="text-muted small">1×1 Photo</span>
      <?php endif; ?>
    </div>
  </div>

</div>




<!-- ROW 2: Name Fields -->
<div class="row g-3 mb-3">

  <div class="col-md-3">
    <label class="form-label">Last Name</label>
    <input class="form-control"
           value="<?= h($data['last_name'] ?? '') ?>"
           disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">First Name</label>
    <input class="form-control"
           value="<?= h($data['first_name'] ?? '') ?>"
           disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Middle Name</label>
    <input class="form-control"
           value="<?= h($data['middle_name'] ?? '') ?>"
           disabled>
  </div>

  <div class="col-md-3">
    <label class="form-label">Suffix</label>
    <input class="form-control"
           value="<?= h($data['suffix'] ?? '') ?>"
           disabled>
  </div>

</div>

<!-- Upload Medical Certificate -->
<div class="mt-3">
  <label class="form-label">Medical Certificate</label>

  <?php if (!empty($data['medicalcert_path'])):
      $basename = basename($data['medicalcert_path']);
      $viewUrl = h($_SERVER['PHP_SELF']) . '?id=' . h($app_id)
               . '&file_action=view&file=' . h($basename);
  ?>
    <div class="upload-box"
         onclick="window.open('<?= $viewUrl ?>','_blank')">
      <i class="bi bi-file-earmark-pdf fs-4 mb-1"></i>
      <div>View Medical Certificate</div>
    </div>
  <?php else: ?>
    <div class="text-muted fst-italic">No medical certificate uploaded.</div>
  <?php endif; ?>
</div>

<div class="row mt-4">

<div class="row mt-4 g-4">

  <!-- Diagnosis -->
  <div class="col-md-6">
    <label class="form-label fw-bold">Diagnosis</label>
    <input type="text"
           name="diagnosis"
           class="form-control"
           value="<?= h($data['diagnosis'] ?? '') ?>">
  </div>

  <!-- Physical Location -->
  <div class="col-md-6">
    <label class="form-label fw-bold">Physical Location</label>

    <div class="toggle-group">
      <input type="radio"
             name="physical_location"
             id="loc_outside"
             value="outside"
             <?= ($data['physical_location'] ?? '') === 'outside' ? 'checked' : '' ?>>

      <label for="loc_outside" class="toggle-btn">
        OUTSIDE ILIGAN
      </label>

      <input type="radio"
             name="physical_location"
             id="loc_iligan"
             value="iligan"
             <?= ($data['physical_location'] ?? '') === 'iligan' ? 'checked' : '' ?>>

      <label for="loc_iligan" class="toggle-btn">
        ILIGAN BASED
      </label>
    </div>
  </div>

</div>





<div class="row mt-3">
  <div class="col-md-6">
    <label class="form-label fw-bold">Certifying Physician</label>
    <select class="form-select" name="certifying_physician">
      <option value="">Select physician</option>
    </select>
  </div>
</div>

<div class="row mt-4"> 
<!-- APPLICATION STATUS -->
<div class="mt-4">
<label class="form-label fw-bold">Application Status</label>
<div class="toggle-group">
  <?php
  $status = $data['medical_status'] ?? '';
  foreach (['pending','denied','accepted'] as $s):
  ?>
    <input type="radio" name="medical_status" id="stat_<?= $s ?>" value="<?= $s ?>"
           <?= $status === $s ? 'checked' : '' ?>>
    <label for="stat_<?= $s ?>" class="toggle-btn"><?= strtoupper($s) ?></label>
  <?php endforeach; ?>
</div>
</div>

<!-- PENDING -->
<div id="pending-section" class="mt-4 d-none">
  <label class="form-label fw-bold">Reason for Pending</label>
  <textarea name="pending_reason" class="form-control" rows="3"><?= h($data['pending_reason'] ?? '') ?></textarea>
</div>

<!-- ASSESSING PHYSICIAN (REQUIRED FOR PENDING / DENIED / ACCEPTED) -->
<div id="assessing-section" class="row mt-4 g-3 d-none">
  <div class="col-md-8">
    <label class="form-label fw-bold">Assessing Physician</label>
    <select class="form-select" name="assessing_physician" id="assessing_physician">
      <option value="">Select assessing physician</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label fw-bold">PRC ID</label>
    <input type="text" class="form-control" name="prc_id" id="prc_id">
  </div>
</div>

<!-- ACCEPTED -->
<div id="accepted-section" class="d-none">

<div class="row mt-4">
  <div class="col-md-7">
    <label class="form-label fw-bold">Disability Type</label>
    <select name="disability_type" class="form-select">
      <option value="">Select</option>
      <?php foreach (['Physical','Visual','Hearing','Intellectual','Psychosocial','Multiple'] as $t): ?>
        <option value="<?= $t ?>" <?= ($data['disability_type'] ?? '') === $t ? 'selected' : '' ?>>
          <?= $t ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div>


<div class="mt-3">
  <label class="form-label fw-bold">PWD Certificate</label>
  <label class="upload-box">
    <input type="file" name="pwd_certificate" hidden>
    Upload PWD Certificate
  </label>
</div>

</div>

<div class="d-flex justify-content-end mt-4">
  <button class="btn btn-primary px-4">SAVE</button>
</div>

</form>
</div>
</div>
</div>

<script>
function toggleMedicalSections(){
  const status = document.querySelector('input[name="medical_status"]:checked')?.value;

  const pending = document.getElementById('pending-section');
  const accepted = document.getElementById('accepted-section');
  const assessing = document.getElementById('assessing-section');

  const physician = document.getElementById('assessing_physician');
  const prc = document.getElementById('prc_id');

  // Hide all by default
  pending.classList.add('d-none');
  accepted.classList.add('d-none');
  assessing.classList.add('d-none');

  // Remove required first
  physician.required = false;
  prc.required = false;

  if (status === 'pending') {
    pending.classList.remove('d-none');
    assessing.classList.remove('d-none');
    physician.required = true;
    prc.required = true;
  }

  if (status === 'denied') {
    assessing.classList.remove('d-none');
    physician.required = true;
    prc.required = true;
  }

  if (status === 'accepted') {
    accepted.classList.remove('d-none');
    assessing.classList.remove('d-none');
    physician.required = true;
    prc.required = true;
  }
}

toggleMedicalSections();

document.querySelectorAll('input[name="medical_status"]').forEach(radio=>{
  radio.addEventListener('change', toggleMedicalSections);
});
</script>


</body>
</html>
