<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['applicant_id'])) {
    header("Location: ../../public/login_form.php");
    exit;
}

$applicant_id = (int)$_SESSION['applicant_id'];



/** -------------------------------
 * Resolve application type (url -> post -> session)
 * and normalize to: new | renew | lost
 * ------------------------------- */
$type = strtolower($_GET['type'] ?? $_POST['type'] ?? ($_SESSION['application_type'] ?? 'new'));
if ($type === 'renewal') $type = 'renew';
if (!in_array($type, ['new','renew','lost'], true)) $type = 'new';
$_SESSION['application_type'] = $type;

/** Map to DB enum */
$appTypeEnum = match ($type) {
  'new'   => 'New',
  'renew' => 'Renewal',
  'lost'  => 'Lost ID',
};

/** -------------------------------
 * Ensure we have an in-progress 
 * for this applicant + type (application_date IS NULL)
 * ------------------------------- */

// If a session id exists, validate it's still correct/in-progress
if (!empty($_SESSION['application_id'])) {
    $chk = pg_query_params(
        $conn,
        "SELECT application_id
           FROM application
          WHERE application_id   = $1
            AND applicant_id     = $2
            AND application_type = $3::application_type_enum
            AND application_date IS NULL",
        [$_SESSION['application_id'], $applicant_id, $appTypeEnum]
    );
    if (!$chk || !pg_fetch_row($chk)) {
        unset($_SESSION['application_id']);
    }
}

if (empty($_SESSION['application_id'])) {
    // Reuse latest in-progress app for this type
    $res = pg_query_params(
        $conn,
        "SELECT application_id
           FROM application
          WHERE applicant_id     = $1
            AND application_type = $2::application_type_enum
            AND application_date IS NULL
          ORDER BY created_at DESC
          LIMIT 1",
        [$applicant_id, $appTypeEnum]
    );
    if ($res && ($row = pg_fetch_assoc($res))) {
        $_SESSION['application_id'] = (int)$row['application_id'];
    } else {
        // Create new in-progress app
        $ins = pg_query_params(
            $conn,
            "INSERT INTO application (applicant_id, application_type, application_date, created_at)
             VALUES ($1, $2::application_type_enum, NULL, NOW())
             RETURNING application_id",
            [$applicant_id, $appTypeEnum]
        );
        if (!$ins) die('DB Error creating application: ' . pg_last_error($conn));
        $_SESSION['application_id'] = (int)pg_fetch_result($ins, 0, 'application_id');
    }
}

$application_id = (int)$_SESSION['application_id'];

/** -------------------------------
 * Load Step 1 draft (for preload)
 * ------------------------------- */
$step = 1;
$draftData = loadDraftData($step, $application_id) ?? [];

// 🔒 LOCK FORM IF ALREADY SUBMITTED
if (($draftData['workflow_status'] ?? 'draft') !== 'draft') {
    http_response_code(403);
    exit('Application already submitted. Editing is disabled.');
}


/** If draft lacks pic_1x1_path, read from application row */

$currentPic = $draftData['pic_1x1_path'] ?? null;

/** -------------------------------
 * Handle POST (save step 1)
 * ------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData   = $_POST;
    $photoPath  = null; // optional profile_photo (draft only)
    $pic1x1Path = null; // main 1x1 photo (persist on application)

    // ---- Profile photo (optional) -> keep only in draft ----
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $photosFsDir  = realpath(__DIR__ . '/../../') . '/uploads/photos/';
        $photosWebDir = '/uploads/photos/';
        if (!is_dir($photosFsDir)) { mkdir($photosFsDir, 0777, true); }

        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
            $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $fs   = $photosFsDir . $file;
            $web  = $photosWebDir . $file;
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fs)) {
                $photoPath = $web;
                $formData['profile_photo_path'] = $web;
            }
        }
    }

    // ---- 1x1 photo (optional) -> save to application + draft ----
    if (isset($_FILES['pic_1x1']) && $_FILES['pic_1x1']['error'] === UPLOAD_ERR_OK) {
        $fsDir  = realpath(__DIR__ . '/../../') . '/uploads/';
        $webDir = '/uploads/';
        if (!is_dir($fsDir)) { mkdir($fsDir, 0777, true); }

        $ext = strtolower(pathinfo($_FILES['pic_1x1']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
            $file = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $fs   = $fsDir . $file;
            $web  = $webDir . $file;
            if (move_uploaded_file($_FILES['pic_1x1']['tmp_name'], $fs)) {
                $pic1x1Path = $web;
                // persist on application row
                pg_query_params(
                $conn,
                "INSERT INTO documentrequirements (application_id, pic_1x1_path)
                VALUES ($1, $2)
                ON CONFLICT (application_id)
                DO UPDATE SET pic_1x1_path = EXCLUDED.pic_1x1_path",
                [$application_id, $web]
                 );
                // also in draft for preload
                $formData['pic_1x1_path'] = $web;
            }
        }
    }

    // If no new upload, keep whatever was already in draft
    if (empty($formData['pic_1x1_path']) && !empty($draftData['pic_1x1_path'])) {
        $formData['pic_1x1_path'] = $draftData['pic_1x1_path'];
    }

    if (empty($formData['pic_1x1_path'])) {
    $_SESSION['error'] = '1x1 photo is required.';
    header("Location: form1.php?type=" . urlencode($type));
    exit;
}


    // Save entire step-1 payload (JSONB merge in DraftHelper)
    saveDraftData($step, $formData, $application_id); 

    // (Optional) stash some fields in session for later steps
    $_SESSION['applicant'] = [
        'application_type' => $type,
        'last_name'        => $_POST['last_name']      ?? null,
        'first_name'       => $_POST['first_name']     ?? null,
        'middle_name'      => $_POST['middle_name']    ?? null,
        'suffix'           => $_POST['suffix']         ?? null,
        'birthdate'        => $_POST['birthdate']      ?? null,
        'sex'              => $_POST['sex']            ?? null,
        'civil_status'     => $_POST['civil_status']   ?? null,
        'house_no_street'  => $_POST['house_no_street']?? null,
        'barangay'         => $_POST['barangay']       ?? null,
        'municipality'     => $_POST['municipality']   ?? null,
        'province'         => $_POST['province']       ?? null,
        'region'           => $_POST['region']         ?? null,
        'landline_no'      => $_POST['landline_no']    ?? null,
        'mobile_no'        => $_POST['mobile_no']      ?? null,
        'email_address'    => $_POST['email_address']  ?? null,
        'pic_1x1_path'     => $formData['pic_1x1_path'] ?? null,
    ];

    $_SESSION['causedetail'] = [ 'cause_detail' => $_POST['cause'] ?? null ];
    $_SESSION['causedisability'] = [ 'cause_disability' => $_POST['cause_description'] ?? null ];
    $_SESSION['disability'] = [ 'disability_type' => $_POST['disability_type'] ?? null ];

    // useful if you reference later
    $_SESSION['draft_photo'] = $pic1x1Path ?: $photoPath;

    // Go to step 2, keep ?type
    header("Location: form2.php?type=" . urlencode($type));
    exit;
}


$currentStep = 1;

// This controls how far the user is allowed to click
$_SESSION['max_step'] = $_SESSION['max_step'] ?? 1;

// NEVER allow max_step to go backwards
if ($_SESSION['max_step'] < $currentStep) {
    $_SESSION['max_step'] = $currentStep;
}


?>




<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PWD Online Application</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

  <link rel="stylesheet" href="../../assets/css/global/forms.css">

</head>

<body>
  <?php include __DIR__ . '/../../hero/navbar.php'; ?>

  

<h1 class="form-title">PWD Application Form</h1>

 <div class="step-indicator">

  <a href="#" class="step <?= $currentStep === 1 ? 'active' : '' ?>" data-step="1">
    <div class="circle">1</div>
    <div class="label">Personal Information</div>
  </a>

  <a href="#" class="step <?= $currentStep === 2 ? 'active' : '' ?>" data-step="2">
    <div class="circle">2</div>
    <div class="label">Affiliation Section</div>
  </a>

  <a href="#" class="step <?= $currentStep === 3 ? 'active' : '' ?>" data-step="3">
    <div class="circle">3</div>
    <div class="label">Approval Section</div>
  </a>

  <a href="#" class="step <?= $currentStep === 4 ? 'active' : '' ?>" data-step="4">
    <div class="circle">4</div>
    <div class="label">Upload Documents</div>
  </a>

  <a href="#" class="step <?= $currentStep === 5 ? 'active' : '' ?>" data-step="5">
    <div class="circle">5</div>
    <div class="label">Submission Complete</div>
    </a>

  </div>


<main class="form-container">
   <form id="appForm" method="POST" action="form1.php" enctype="multipart/form-data">
    <!-- Row 1 -->
    <div class="row g-3 mb-3">
      <?php
        $applicationType = isset($_GET['type']) ? $_GET['type'] : 'new';
        $_SESSION['application_type'] = $applicationType;


    $applicationLabel = match($applicationType) {
      'new' => 'New Application',
      'renew' => 'Renewal Application',
      'lost' => 'Lost ID Application',
      default => ucfirst($applicationType) . ' Application'
    };
  ?>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Application Type</label>
    <input type="hidden" name="applicantType" value="<?php echo htmlspecialchars($applicationType); ?>">
    <div class="form-control bg-light text-dark" style="font-size: 0.9rem;">
      <?php echo $applicationLabel; ?>
    </div>
  </div>

      <div class="col-md-4">
        <label class="form-label fw-semibold">
          Persons with Disability Number
        </label>
        <input
          type="text"
          class="form-control"
          placeholder="To be filled by PDAO once approved"
          disabled>
      </div>

      <div class="col-md-3">
        <label class="form-label fw-semibold">
          Date Applied
        </label>
        <input
          type="date"
          class="form-control"
          value="<?= date('Y-m-d') ?>"
          readonly>
      </div>

<div class="col-md-2">
  <div class="photo-box mx-auto text-center position-relative"
       style="cursor:pointer; overflow:hidden;"
       onclick="document.getElementById('photoInput').click();">

    <!-- Required indicator -->
    <?php if (empty($currentPic)): ?>
      <span class="photo-required">*</span>
    <?php endif; ?>

    <span id="uploadText" <?= !empty($currentPic) ? 'style="display:none;"' : '' ?>>
      Upload Photo
    </span>

    <img id="previewImg"
         src="<?= htmlspecialchars($currentPic ?? '') ?>"
         style="<?= !empty($currentPic) ? '' : 'display:none;' ?>;
                width:100%;height:100%;object-fit:cover;border-radius:6px;">
  </div>

  <input type="hidden" name="pic_1x1_path" value="<?= htmlspecialchars($currentPic ?? '') ?>">
  <input type="file" id="photoInput" name="pic_1x1" accept="image/*"
         style="display:none;" onchange="previewPhoto(event)">
</div>


      </div>

      <!-- Row 2 -->

<div class="row g-3 mb-3" style="margin-top: -55px;">
  <div class="col-md-3">
    <label class="form-label fw-semibold required">Last Name</label>
    <input
      type="text"
      name="last_name"
      id="last_name"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['last_name'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold required">First Name</label>
    <input
      type="text"
      name="first_name"
      id="first_name"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['first_name'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold">Middle Name</label>
    <input
      type="text"
      name="middle_name"
      id="middle_name"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['middle_name'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold">Suffix</label>
    <input
      type="text"
      name="suffix"
      id="suffix"
      class="form-control"
      value="<?= htmlspecialchars($draftData['suffix'] ?? '') ?>">
  </div>
</div>

      <!-- Row 3 -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label fw-semibold required">Date of Birth</label>
      <input
        type="date"
        name="birthdate"
        class="form-control"
        required
        value="<?= htmlspecialchars($draftData['birthdate'] ?? '') ?>">

    </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold required">Sex</label>
          <select name="sex" class="form-select" required>
            <option value="">Please Select</option>
            <option value="Male" <?= ($draftData['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= ($draftData['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
          </select> 

        </div>
        <div class="col-md-3">
        <label class="form-label fw-semibold required">Civil Status</label>
        <select name="civil_status" class="form-select" required>
          <option value="">Please Select</option>
          <option value="Single" <?= ($draftData['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
          <option value="Married" <?= ($draftData['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
          <option value="Separated" <?= ($draftData['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
          <option value="Widow/er" <?= ($draftData['civil_status'] ?? '') === 'Widow/er' ? 'selected' : '' ?>>Widow/er</option>
        </select>

          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold required">Type of Disability</label>
              <select name="disability_type" class="form-select" required>
             <option value="">Please Select</option>
              <option value="Deaf or Hard of Hearing" <?= ($draftData['disability_type'] ?? '') === 'Deaf or Hard of Hearing' ? 'selected' : '' ?>>Deaf or Hard of Hearing</option>
              <option value="Intellectual Disability" <?= ($draftData['disability_type'] ?? '') === 'Intellectual Disability' ? 'selected' : '' ?>>Intellectual Disability</option>
              <option value="Learning Disability" <?= ($draftData['disability_type'] ?? '') === 'Learning Disability' ? 'selected' : '' ?>>Learning Disability</option>
              <option value="Mental Disability" <?= ($draftData['disability_type'] ?? '') === 'Mental Disability' ? 'selected' : '' ?>>Mental Disability</option>
              <option value="Physical Disability (Orthopedic)" <?= ($draftData['disability_type'] ?? '') === 'Physical Disability (Orthopedic)' ? 'selected' : '' ?>>Physical Disability (Orthopedic)</option>
              <option value="Psychosocial Disability" <?= ($draftData['disability_type'] ?? '') === 'Psychosocial Disability' ? 'selected' : '' ?>>Psychosocial Disability</option>
              <option value="Speech and Language Impairment" <?= ($draftData['disability_type'] ?? '') === 'Speech and Language Impairment' ? 'selected' : '' ?>>Speech and Language Impairment</option>
              <option value="Visual Disability" <?= ($draftData['disability_type'] ?? '') === 'Visual Disability' ? 'selected' : '' ?>>Visual Disability</option>
              <option value="Cancer (RA11215)" <?= ($draftData['disability_type'] ?? '') === 'Cancer (RA11215)' ? 'selected' : '' ?>>Cancer (RA11215)</option>
              <option value="Rare Disease (RA10747)" <?= ($draftData['disability_type'] ?? '') === 'Rare Disease (RA10747)' ? 'selected' : '' ?>>Rare Disease (RA10747)</option>
            </select>
        </div>
      </div>

<!-- Row 4 -->
<div class="row g-3 mb-3 align-items-end">
  <!-- Cause of Disability -->
  <div class="col-md-3">
    <label class="form-label fw-semibold text-primary required">
      Cause of Disability
    </label>

    <div class="d-flex gap-3 mb-2" style="font-size: 0.75rem;">
      <div class="form-check mb-0">
        <input
          class="form-check-input"
          type="radio"
          id="causeCongenital"
          name="cause"
          value="Congenital/Inborn"
          required
          onchange="updateOptions(this.value)"
          <?= ($draftData['cause_detail'] ?? '') === 'Congenital/Inborn' ? 'checked' : '' ?>>
        <label class="form-check-label" for="causeCongenital">
          Congenital/Inborn
        </label>
      </div>

      <div class="form-check mb-0">
        <input
          class="form-check-input"
          type="radio"
          id="causeAcquired"
          name="cause"
          value="Acquired"
          required
          onchange="updateOptions(this.value)"
          <?= ($draftData['cause_detail'] ?? '') === 'Acquired' ? 'checked' : '' ?>>
        <label class="form-check-label" for="causeAcquired">
          Acquired
        </label>
      </div>
    </div>

    <select
      id="cause_description"
      name="cause_description"
      class="form-select"
      required>
      <option value="">Please Select</option>
    </select>
  </div>

  <!-- House No. & Street -->
  <div class="col-md-3">
    <label class="form-label fw-semibold required">House No. and Street</label>
    <input
      type="text"
      name="house_no_street"
      id="house_no_street"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['house_no_street'] ?? '') ?>">
  </div>

  <!-- Barangay -->
  <div class="col-md-3">
    <label class="form-label fw-semibold required">Barangay</label>
    <input
      type="text"
      name="barangay"
      id="barangay"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['barangay'] ?? '') ?>">
  </div>

  <!-- Municipality (NOT REQUIRED) -->
  <div class="col-md-3">
    <label class="form-label fw-semibold">Municipality</label>
    <input
      type="text"
      name="municipality"
      id="municipality"
      class="form-control"
      value="<?= htmlspecialchars($draftData['municipality'] ?? '') ?>">
  </div>
</div>



      <!-- Row 5 -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label fw-semibold required">Province</label>
    <input
      type="text"
      name="province"
      id="province"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['province'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold required">Region</label>
    <input
      type="text"
      name="region"
      id="region"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['region'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold required">Landline No.</label>
    <input
      type="tel"
      name="landline_no"
      id="landline_no"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['landline_no'] ?? '') ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold required">Mobile No.</label>
    <input
      type="tel"
      name="mobile_no"
      id="mobile_no"
      class="form-control"
      required
      value="<?= htmlspecialchars($draftData['mobile_no'] ?? '') ?>">
  </div>
</div>


      <!-- Row 6 -->
      <div class="mb-3">
        <label for="email_address" class="form-label fw-semibold required">
          E-mail Address
        </label>
        <input
          type="email"
          name="email_address"
          id="email_address"
          class="form-control"
          placeholder="example@domain.com"
          required
          value="<?= htmlspecialchars($draftData['email_address'] ?? '') ?>">
      </div>



      <div class="text-end">
        <button type="submit" class="btn btn-primary px-4">Next</button>
      </div>
    </form>
  </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
  let navigating = false;
      </script>

  <script>
    const causeSelect = document.getElementById('cause_description');

    function updateOptions(type) {
      let options = [];

        if (type === "Congenital/Inborn"){
        options = ["Autism", "ADHD", "Cerebral Palsy", "Down Syndrome"];
      } else if (type === "Acquired") {
        options = ["Chronic Illness", "Cerebral Palsy", "Injury"];
      }

      causeSelect.innerHTML = '<option value="">Please Select</option>';
      options.forEach(opt => {
        const optionElement = document.createElement('option');
        optionElement.textContent = opt;
        optionElement.value = opt;
        causeSelect.appendChild(optionElement);
      });
    }

    window.addEventListener('DOMContentLoaded', () => {
      const savedCause = "<?= $draftData['cause'] ?? '' ?>";
      const savedDesc = "<?= $draftData['cause_description'] ?? '' ?>";

      if (savedCause) {
        const radio = document.querySelector(`input[name="cause"][value="${savedCause}"]`);
        if (radio) {
          radio.checked = true;
          updateOptions(savedCause); // This repopulates dropdown
        }
      
        if (savedDesc) {
          setTimeout(() => {
            causeSelect.value = savedDesc;
          }, 100);
        }
      }
    });
  </script>

  <script>
  window.currentStep = <?= (int)$currentStep ?>;
  window.maxAllowedStep = <?= (int)($_SESSION['max_step'] ?? 1) ?>;
</script>


<script>
document.querySelectorAll('.step').forEach(step => {
  step.addEventListener('click', function (e) {
    e.preventDefault();
    navigating = true;

    const targetStep = parseInt(this.dataset.step, 10);

    if (targetStep > window.maxAllowedStep) {
      navigating = false;
      alert('Please complete the previous step first.');
      return;
    }

    window.location.href = `form${targetStep}.php?type=${window.appType}`;
  });
});
</script>

<script>
    document.addEventListener('DOMContentLoaded', () => {
      console.log('Autosave script loaded');

      const form = document.getElementById('appForm'); // make sure <form> has id="appForm"
      if (!form) {
        console.error('Autosave: form not found');
        return;
      }

      // PHP variables so JS knows them
      const APPLICATION_ID = <?= json_encode($_SESSION['application_id'] ?? null) ?>;
      const STEP = 1; // this page is Form 1

      const debounce = (fn, ms = 400) => {
        let t;
        return (...args) => {
          clearTimeout(t);
          t = setTimeout(() => fn(...args), ms);
        };
      };

      const send = () => {
        const obj = Object.fromEntries(new FormData(form).entries());
        obj.application_id = APPLICATION_ID; // Send ID to autosave.php

        fetch('./autosave.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body:
            'formData=' + encodeURIComponent(JSON.stringify(obj)) +
            '&step=' + STEP +
            '&application_id=' + encodeURIComponent(APPLICATION_ID)
        })
        .then(r => r.json())
        .then(d => console.log('Autosave response:', d))
        .catch(e => console.error('Autosave failed:', e));
      };

form.addEventListener('input', debounce(() => {
  if (navigating) return;
  send();
}));

form.addEventListener('change', debounce(() => {
  if (navigating) return;
  send();
}));

    </script>

    <script>
  window.appType = <?= json_encode($type) ?>;
</script>

    <script>
  // Make it GLOBAL so inline onchange can find it
  window.previewPhoto = function (event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    const ok = ['image/jpeg','image/png','image/gif','image/webp','image/jpg'];
    if (!ok.includes(file.type)) {
      alert('Please select an image (JPG/PNG/GIF/WebP).');
      event.target.value = '';
      return;
    }

    const img = document.getElementById('previewImg');
    const txt = document.getElementById('uploadText');
    if (img) { img.src = URL.createObjectURL(file); img.style.display = 'block'; }
    if (txt) { txt.style.display = 'none'; }
  };
</script>

<style>
.step {
  text-decoration: none;
  color: inherit;
}

.step:hover,
.step:visited,
.step:active {
  text-decoration: none;
  color: inherit;
}

.required::after {
    content: " *";
    color: #dc3545; /* Bootstrap red */
    font-weight: bold;

    .photo-box {
  position: relative;
}

.photo-box .required-text {
  position: absolute;
  bottom: 6px;
  right: 8px;
  font-size: 0.75rem;
  color: #dc3545;
  font-weight: 600;
}

.photo-box:hover {
  border-color: #0d6efd;
  background-color: #f8fbff;
}

  }
</style>




</body>
</html>
