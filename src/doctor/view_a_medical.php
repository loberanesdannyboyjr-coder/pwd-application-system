<?php
// src/doctor/view_a_medical.php
// CHO / Doctor — MEDICAL ONLY VIEW (Backup / New UI)

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

  function h($s){
      return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
  }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

$session_user_id = $_SESSION['user_id'] ?? null;
$session_role = strtoupper($_SESSION['role'] ?? '');

$app_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($app_id <= 0) {
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

  .page-title {
    color: #14255A;
}

  .photo-box {
      width: 120px;
      height: 120px;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      overflow: hidden;
      background: #f9fafb;
      display: flex;
      align-items: center;
      justify-content: center;
  }

  .photo-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
  }

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

  .text-primary-custom,
  .upload-box,
  .upload-box *{
    color:#14255A;
  }

  .btn-primary,
  .btn-primary:hover,
  .btn-primary:focus{
    background-color:#14255A !important;
    border-color:#14255A !important;
  }

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

/* Hover */
#loc_outside:hover + label,
#loc_iligan:hover + label,
#loc_outside + label:hover,
#loc_iligan + label:hover {
  background-color: #14255A;
  border-color: #14255A;
  color: #fff;
}

/* Selected */
#loc_outside:checked + label,
#loc_iligan:checked + label {
  background-color: #14255A;
  border-color: #14255A;
  color: #fff;
}


/* Hover */
#stat_approved + label:hover {
  background-color: #198754;
  border-color: #198754;
  color: #fff;
}

/* Selected */
#stat_approved:checked + label {
  background-color: #198754;
  border-color: #198754;
  color: #fff;
}


/* Hover */
#stat_denied + label:hover {
  background-color: #dc3545;
  border-color: #dc3545;
  color: #fff;
}

/* Selected */
#stat_denied:checked + label {
  background-color: #dc3545;
  border-color: #dc3545;
  color: #fff;
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
  transition: 0.2s ease;
}

/* Hide native radio circle completely */
.toggle-group input[type="radio"] {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

  /* APPROVE = GREEN */
#stat_approved:checked + label {
  background-color: #198754 !important;   /* Bootstrap green */
  border-color: #198754 !important;
  color: #fff !important;
}

/* DISAPPROVE = RED */
#stat_denied:checked + label {
  background-color: #dc3545 !important;   /* Bootstrap red */
  border-color: #dc3545 !important;
  color: #fff !important;
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
<?php
$type = strtolower($app['application_type'] ?? '');

$title = match ($type) {
    'new'      => 'PWD New Application',
    'renewal'  => 'PWD Renewal Application',
    'lost'     => 'PWD Lost ID Application',
    default    => 'PWD Application',
};
?>

<h4 class="fw-bold text-center my-3 page-title">
  <?= h($title) ?>
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
      action="<?= h(rtrim(APP_BASE_URL,'/') . '/api/cho_action.php') ?>">

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

  <!--
    <div class="col-md-3">
      <label class="form-label">Patient ID</label>
      <input type="text"
            class="form-control"
            value="<?= h($app['pwd_number'] ?? '') ?>"
            disabled>
    </div>
  -->

    <!-- Right Side (Photo) -->
    <div class="col-md-2 ms-auto d-flex justify-content-end">
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
      <div class="col-md-8">
        <label class="form-label fw-bold">Certifying Physician</label>

        <select class="form-select"
                name="certifying_physician"
                id="certifying_physician">

          <option value="">Select physician</option>
          <option value="Dr. Alejandro M. Reyes">Dr. Alejandro M. Reyes</option>
          <option value="Dr. Maria Lourdes P. Santos">Dr. Maria Lourdes P. Santos</option>
          <option value="Dr. Ramon C. Villanueva">Dr. Ramon C. Villanueva</option>
        </select>

        <!-- Hidden only (for PDF use) -->
        <input type="hidden" name="certifying_prc_id" id="certifying_prc_id">
        <input type="hidden" name="certifying_signature" id="certifying_signature">
      </div>
    </div>


<div class="row mt-4">
  <div class="col-12">
    <label class="form-label fw-bold">Application Status</label>

    <div class="toggle-group mb-3">
      <?php $status = $data['medical_status'] ?? ''; ?>

      <input type="radio" name="medical_status" id="stat_approved" value="accepted"
          <?= $status === 'accepted' ? 'checked' : '' ?>>
      <label for="stat_approved" class="toggle-btn approve-btn">
          APPROVE
      </label>

      <input type="radio" name="medical_status" id="stat_denied" value="denied"
          <?= $status === 'denied' ? 'checked' : '' ?>>
      <label for="stat_denied" class="toggle-btn disapprove-btn">
          DISAPPROVE
      </label>
    </div>
  </div>
</div>

    <?php if (!empty($data['pwd_cert_path'])): ?>
      <div class="upload-box"
          onclick="window.open('<?= APP_BASE_URL . '/' . $data['pwd_cert_path'] ?>','_blank')">
        View Generated PWD Certificate
      </div>
    <?php endif; ?>


  <!-- DISAPPROVE REMARKS -->
<div id="disapprove-section" class="mt-4 d-none">
  <label class="form-label fw-bold">Reason for Disapproval</label>
  <textarea name="disapprove_reason"
            class="form-control"
            rows="3"
            placeholder="Enter reason for disapproval..."><?= h($data['disapprove_reason'] ?? '') ?></textarea>
</div>

    <!-- ASSESSING PHYSICIAN -->
        <div id="assessing-section" class="row mt-4 g-3 d-none">
    <div class="col-md-8">
      <label class="form-label fw-bold">Assessing Physician</label>
      <select class="form-select" name="assessing_physician" id="assessing_physician">
        <option value="">Select assessing physician</option>
        <option value="Dr. Alejandro M. Reyes">Dr. Alejandro M. Reyes</option>
        <option value="Dr. Maria Lourdes P. Santos">Dr. Maria Lourdes P. Santos</option>
        <option value="Dr. Ramon C. Villanueva">Dr. Ramon C. Villanueva</option>
      </select>

      <!-- Hidden inputs MUST be OUTSIDE select -->
      <input type="hidden" name="assessing_prc_id" id="assessing_prc_id">
      <input type="hidden" name="assessing_signature" id="assessing_signature">
    </div>

    <div class="col-md-4">
      <label class="form-label fw-bold">PRC ID</label>
      <input type="text" class="form-control" id="assessing_prc_display" disabled>
    </div>
  </div>

  <!-- APPROVED -->
  <div id="approved-section" class="d-none">

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

  <button type="button"
          class="btn btn-primary w-100"
          onclick="openPreview()">
      Preview PWD Certificate
  </button>
</div>

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
    function toggleMedicalSections() {
      const status = document.querySelector('input[name="medical_status"]:checked')?.value;

      const approved = document.getElementById('approved-section');
      const assessing = document.getElementById('assessing-section');
      const disapprove = document.getElementById('disapprove-section');

      const physician = document.getElementById('assessing_physician'); 
      const disapproveReason = document.querySelector('[name="disapprove_reason"]');

      // Hide everything first
      approved.classList.add('d-none');
      assessing.classList.add('d-none');
      disapprove.classList.add('d-none');

      // Remove required first
      physician.required = false;
      disapproveReason.required = false;

      // =========================
      // APPROVE (DB = accepted)
      // =========================
      if (status === 'accepted') {
        approved.classList.remove('d-none');
        assessing.classList.remove('d-none');

        physician.required = true;
      }

      // =========================
      // DISAPPROVE (DB = denied)
      // =========================
      if (status === 'denied') {
        disapprove.classList.remove('d-none');
        assessing.classList.remove('d-none');

        physician.required = true;
        disapproveReason.required = true;
      }
    }

    // Run on page load
    toggleMedicalSections();

    // Listen for changes
    document.querySelectorAll('input[name="medical_status"]').forEach(radio => {
      radio.addEventListener('change', toggleMedicalSections);
    });
    </script>


<script>
function openPreview() {

    const form = document.createElement("form");
    form.method = "POST";
    form.action = "<?= rtrim(APP_BASE_URL,'/') ?>/api/cho/preview_pwd_certificate.php";
    form.target = "_blank";

        const fields = {
        application_id: "<?= h($app_id) ?>",
        diagnosis: document.querySelector("input[name='diagnosis']").value,
        certifying_physician: document.querySelector("#certifying_physician").value,
        assessing_physician: document.querySelector("#assessing_physician").value,
        disability_type: document.querySelector("select[name='disability_type']").value,

        certifying_prc_id: document.querySelector("#certifying_prc_id").value,
        assessing_prc_id: document.querySelector("#assessing_prc_id").value,

        certifying_signature: document.querySelector("#certifying_signature").value,
        assessing_signature: document.querySelector("#assessing_signature").value
    };

    for (const key in fields) {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}


</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const doctors = {
        "Dr. Alejandro M. Reyes": {
            prc: "0987654",
            signature: "/assets/signatures/reyes_signature.png"
        },
        "Dr. Maria Lourdes P. Santos": {
            prc: "1123456",
            signature: "/assets/signatures/santos_signature.png"
        },
        "Dr. Ramon C. Villanueva": {
            prc: "1345678",
            signature: "/assets/signatures/villanueva_signature.png"
        }
    };


    const assessingSelect = document.getElementById("assessing_physician");
    const certifyingSelect = document.getElementById("certifying_physician");

    certifyingSelect.addEventListener("change", function () {

    const selected = this.value;

    if (doctors[selected]) {

        document.getElementById("certifying_prc_id").value = doctors[selected].prc;
        document.getElementById("certifying_signature").value = doctors[selected].signature;

    } else {

        document.getElementById("certifying_prc_id").value = "";
        document.getElementById("certifying_signature").value = "";
    }
});

    assessingSelect.addEventListener("change", function () {

        const selected = this.value;

        if (doctors[selected]) {

            // Fill hidden inputs
            document.getElementById("assessing_prc_id").value = doctors[selected].prc;
            document.getElementById("assessing_signature").value = doctors[selected].signature;

            // Fill visible PRC display
            document.getElementById("assessing_prc_display").value = doctors[selected].prc;

        } else {

            document.getElementById("assessing_prc_id").value = "";
            document.getElementById("assessing_signature").value = "";
            document.getElementById("assessing_prc_display").value = "";
        }
    });
});
</script>