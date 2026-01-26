<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';

/* ===============================
   AUTH
================================ */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['applicant_id']) ||
    !isset($_SESSION['application_id'])
) {
    header("Location: ../../public/login_form.php");
    exit;
}

$applicant_id   = $_SESSION['applicant_id'];
$application_id = $_SESSION['application_id'];
$step = 4;

/* ===============================
   LOAD DRAFT
================================ */
$draftData = loadDraftData($step, $application_id);

$proofExists = true;

// 🔒 LOCK FORM IF SUBMITTED
if (($draftData['workflow_status'] ?? 'draft') !== 'draft') {
    http_response_code(403);
    exit('Application already submitted. Editing is disabled.');
}

/* ===============================
   LOAD documentrequirements SAFELY
================================ */
$docRow = [];

// Base columns
$sql = "
SELECT
    bodypic_path,
    barangaycert_path,
    medicalcert_path,
    old_pwd_id_path,
    affidavit_loss_path,
    cho_cert_path,
    proof_disability_path
FROM public.documentrequirements
WHERE application_id = $1
LIMIT 1
";

$res = pg_query_params($conn, $sql, [$application_id]);
if ($res && pg_num_rows($res) > 0) {
    $docRow = pg_fetch_assoc($res);
}

// Optional proof_disability_path
$proofExists = true;

// Normalize keys
$defaults = [
    'bodypic_path'          => null,
    'barangaycert_path'     => null,
    'medicalcert_path'      => null,
    'old_pwd_id_path'       => null,
    'affidavit_loss_path'   => null,
    'cho_cert_path'         => null,
    'proof_disability_path' => null,
];
$draftData = array_merge($draftData ?? [], array_merge($defaults, $docRow));

/* ===============================
   NORMALIZE TYPE
================================ */
$type = strtolower($_SESSION['application_type'] ?? 'new');
if ($type === 'renewal') $type = 'renew';

/* ===============================
   HANDLE POST
================================ */
/* ===============================
   HANDLE POST
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 🔒 REQUIRED DOCUMENT VALIDATION (ADD THIS)
    $required = [];

    if ($type === 'new') {
        $required = ['bodypic_path', 'barangaycert_path', 'medicalcert_path'];
    }
    elseif ($type === 'renew') {
        $required = ['barangaycert_path', 'medicalcert_path', 'old_pwd_id_path'];
    }
    elseif ($type === 'lost') {
        $required = ['barangaycert_path', 'medicalcert_path', 'affidavit_loss_path'];
    }

    foreach ($required as $field) {
        $val = $_POST[$field] ?? null;

        if (empty($val) || $val === '__REMOVE__') {
            http_response_code(400);
            exit('Missing required document.');
        }
    }

    // ✅ NOW it is safe to continue saving drafts & uploads


    // Save non-file draft fields
    saveDraftData($step, $_POST, $application_id);

    // Ensure documentrequirements row exists
    pg_query_params(
        $conn,
        "INSERT INTO public.documentrequirements (application_id)
         SELECT $1
         WHERE NOT EXISTS (
           SELECT 1 FROM public.documentrequirements WHERE application_id = $1
         )",
        [$application_id]
    );

    // File → column map
    $uploads = [
        'barangaycert'    => 'barangaycert_path',
        'medicalcert'     => 'medicalcert_path',
        'proofdisability' => 'proof_disability_path',
    ];
    if ($type === 'new')   $uploads['bodypic']   = 'bodypic_path';
    if ($type === 'renew') $uploads['oldpwdid']  = 'old_pwd_id_path';
    if ($type === 'lost')  $uploads['affidavit'] = 'affidavit_loss_path';

    // Upload dir
    $root = realpath(__DIR__ . '/../../');
    $uploadAbs = $root . '/uploads';
    if (!is_dir($uploadAbs)) mkdir($uploadAbs, 0775, true);

    foreach ($uploads as $field => $column) {

        // Skip proof if column missing
        if ($column === 'proof_disability_path' && !$proofExists) continue;

        $postedPath = trim($_POST[$column] ?? '');
        $newPublicPath = null;

        // Handle upload
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $orig = $_FILES[$field]['name'];
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $name = time() . '_' . $base . ($ext ? ".$ext" : '');

            if (move_uploaded_file($_FILES[$field]['tmp_name'], "$uploadAbs/$name")) {
                $newPublicPath = '/PWD-Application-System/uploads/' . $name;
            }
        }

$shouldUpdate = false;

/* 1️⃣ New file uploaded */
if ($newPublicPath !== null) {
    $valueToSave = $newPublicPath;
    $shouldUpdate = true;
}
elseif ($postedPath === '__REMOVE__') {
    $valueToSave = null;
    $shouldUpdate = true;
}
elseif (!empty($postedPath)) {
    $valueToSave = $postedPath;
    $shouldUpdate = true;
}

/* 3️⃣ Otherwise → DO NOTHING (keep existing DB value) */
if ($shouldUpdate) {
    pg_query_params(
        $conn,
        "UPDATE public.documentrequirements
         SET {$column} = $1,
             updated_at = NOW()
         WHERE application_id = $2",
        [$valueToSave, $application_id]
    );
}
    }

    // Navigation
    if (($_POST['nav'] ?? '') === 'back') {
        header('Location: form3.php?type=' . urlencode($type));
        exit;
    }
    if (($_POST['nav'] ?? '') === 'next') {
        header('Location: form5.php?type=' . urlencode($type));
        exit;
    }
}

$currentStep = 4;

// Initialize max_step if not set
$_SESSION['max_step'] = $_SESSION['max_step'] ?? 1;

// Never allow going backwards
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



      <div class="form-header">
        <h1 class="form-title">PWD Application Form</h1>
      </div>

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


      <div class="form-container" style="max-width: 800px;">
        <form method="POST" enctype="multipart/form-data">

          <?php
          // helper: is image
          function is_image_ext($p) {
              $e = strtolower(pathinfo((string)$p, PATHINFO_EXTENSION));
              return in_array($e, ['jpg','jpeg','png','gif','webp']);
          }
          ?>

    <!-- ================= WHOLE BODY PICTURE (jpg/png only) ================= -->
    <?php if ($type === 'new'): ?>
    <?php $hasBody = !empty($draftData['bodypic_path'] ?? ''); ?>
    <div class="mb-4">
      <?php if ($type === 'new'): ?>
      <label class="form-label fw-semibold required">
        Whole Body Picture (JPG/PNG only)
      </label>
      <?php endif; ?>
      <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
        <!-- Placeholder -->
        <div id="bodypicPlaceholder"
            style="<?= $hasBody ? 'display:none;' : '' ?>; cursor:pointer"
            onclick="document.getElementById('bodypic').click()">
          <img src="https://cdn-icons-png.flaticon.com/512/892/892692.png" alt="" width="60" class="mb-2" />
          <p class="fw-semibold mb-1">Drag & Drop or Choose File</p>
          <div class="small text-muted">No file selected</div>
        </div>


        <!-- Preview state (when there is a file) -->
        <div id="bodypicPreviewWrap" style="<?= $hasBody ? '' : 'display:none;' ?>">
          <img id="bodypicPreview"
              src="<?= ($hasBody && is_image_ext($draftData['bodypic_path'])) ? htmlspecialchars($draftData['bodypic_path']) : '' ?>"
              style="max-height:240px; margin-top:20px; <?= ($hasBody && is_image_ext($draftData['bodypic_path'])) ? '' : 'display:none;' ?> object-fit:contain;"
              alt="">
          <div class="small text-muted mt-2" id="bodypicFileName">
            <?= $hasBody ? htmlspecialchars(basename($draftData['bodypic_path'])) : '' ?>
          </div>
          <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeFile('bodypic', event)">Remove</button>
        </div>
      </div>

      <input type="hidden" id="bodypic_path" name="bodypic_path" value="<?= htmlspecialchars($draftData['bodypic_path'] ?? '') ?>">
      <!-- ✅ Only JPG/PNG -->
      <input type="file" id="bodypic" name="bodypic" accept=".jpg,.jpeg,.png" class="d-none">
    </div>
    <?php endif; ?>


    <?php $hasBarangay = !empty($draftData['barangaycert_path'] ?? ''); ?>

    <!-- ================= BARANGAY CERTIFICATE (jpg/png OR any file) ================= -->
    <?php $hasBarangay = !empty($draftData['barangaycert_path'] ?? ''); ?>
    <div class="mb-4">
    <label class="form-label fw-semibold required">
      Barangay Certificate (JPG/PNG or File)
    </label>
      <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
        <!-- Placeholder -->
        <div id="barangaycertPlaceholder"
            style="<?= $hasBarangay ? 'display:none;' : '' ?>; cursor:pointer"
            onclick="document.getElementById('barangaycert').click()">
          <img src="https://cdn-icons-png.flaticon.com/512/892/892692.png" alt="" width="50" class="mb-2" />
          <p class="fw-semibold mb-1">Drag & Drop or Choose File</p>
          <div class="small text-muted">No file selected</div>
        </div>

        <!-- Preview section (shown when there's a file) -->
        <div id="barangaycertPreviewWrap" style="<?= $hasBarangay ? '' : 'display:none;' ?>">
          <img id="barangaycertPreview"
              src="<?= ($hasBarangay && is_image_ext($draftData['barangaycert_path'])) ? htmlspecialchars($draftData['barangaycert_path']) : '' ?>"
              style="max-height:80px; margin-top:6px; <?= ($hasBarangay && is_image_ext($draftData['barangaycert_path'])) ? '' : 'display:none;' ?>"
              alt="">

          <div class="small text-muted mt-2" id="barangaycertFileName">
            <?= $hasBarangay ? htmlspecialchars(basename($draftData['barangaycert_path'])) : '' ?>
          </div>

          <button type="button" class="btn btn-sm btn-danger mt-2"
                  onclick="removeFile('barangaycert', event)">Remove</button>
        </div>
      </div>

      <input type="hidden" id="barangaycert_path" name="barangaycert_path" value="<?= htmlspecialchars($draftData['barangaycert_path'] ?? '') ?>">
      <!-- ✅ allow images or ANY file -->
      <input type="file" id="barangaycert" name="barangaycert"
       accept=".jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
       class="d-none">
    </div>

  <!-- ================= MEDICAL CERTIFICATE (any file, no preview) ================= -->
  <?php $hasMedical = !empty($draftData['medicalcert_path'] ?? ''); ?>
  <div class="mb-4">
  <label class="form-label fw-semibold required">
    Medical Certificate (File only)
  </label>
    <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
      <!-- Placeholder -->
      <div id="medicalcertPlaceholder"
          style="<?= $hasMedical ? 'display:none;' : '' ?>; cursor:pointer"
          onclick="document.getElementById('medicalcert').click()">
        <img src="https://cdn-icons-png.flaticon.com/512/892/892692.png" alt="" width="50" class="mb-2" />
        <p class="fw-semibold mb-1">Drag & Drop or Choose File</p>
        <div class="small text-muted">No file selected</div>
      </div>


      <!-- Preview -->
      <div id="medicalcertPreviewWrap" style="<?= $hasMedical ? '' : 'display:none;' ?>">
        <img id="medicalcertPreview"
            src="<?= ($hasMedical && is_image_ext($draftData['medicalcert_path'])) ? htmlspecialchars($draftData['medicalcert_path']) : '' ?>"
            style="max-height:240px; margin-top:20px; <?= ($hasMedical && is_image_ext($draftData['medicalcert_path'])) ? '' : 'display:none;' ?> object-fit:contain;" 
            alt="">
        <div class="small text-muted mt-2" id="medicalcertFileName">
          <?= $hasMedical ? htmlspecialchars(basename($draftData['medicalcert_path'])) : '' ?>
        </div>
        <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeFile('medicalcert', event)">Remove</button>
      </div>
    </div>
    <input type="hidden" id="medicalcert_path" name="medicalcert_path" value="<?= htmlspecialchars($draftData['medicalcert_path'] ?? '') ?>">
    <!-- ✅ any file (PDF, DOC, etc.) -->
   <input type="file" id="medicalcert" name="medicalcert"
       accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,image/*"
       class="d-none">
  </div>

  <?php $hasProof = !empty($draftData['proof_disability_path'] ?? ''); ?>

<!-- ================= PROOF OF DISABILITY ================= -->
<?php $hasProof = !empty($draftData['proof_disability_path']); ?>

<div class="mb-4">
  <label class="form-label fw-semibold">
    Proof of Disability
    <span class="text-muted fw-normal">(Upload for visible disability only)</span>
  </label>

  <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">

    <div id="proofdisabilityPlaceholder"
         style="<?= $hasProof ? 'display:none;' : '' ?>;cursor:pointer"
         onclick="document.getElementById('proofdisability').click()">
      <p class="fw-semibold mb-1">Drag & Drop or Choose Image</p>
      <div class="small text-muted">PNG / JPG only</div>
    </div>

    <div id="proofdisabilityPreviewWrap" style="<?= $hasProof ? '' : 'display:none;' ?>">
      <?php if ($hasProof && is_image_ext($draftData['proof_disability_path'])): ?>
        <img src="<?= htmlspecialchars($draftData['proof_disability_path']) ?>"
             style="max-height:220px;object-fit:contain">
      <?php endif; ?>

      <div class="small text-muted mt-2" id="proofdisabilityFileName">
        <?= $hasProof ? htmlspecialchars(basename($draftData['proof_disability_path'])) : '' ?>
      </div>

      <button type="button"
              class="btn btn-sm btn-danger mt-2"
              onclick="removeFile('proofdisability', event)">
        Remove
      </button>
    </div>
  </div>

  <input type="hidden"
         id="proofdisability_path"
         name="proof_disability_path"
         value="<?= htmlspecialchars($draftData['proof_disability_path'] ?? '') ?>">

<input type="file"
       id="proofdisability"
       name="proofdisability"
       style="position:absolute; left:-9999px;">
</div>

        <!-- CHO Certificate (read-only for applicants) -->
        <div class="mb-4">
          <label class="form-label fw-semibold">Certificate from City Health Office (CHO):</label>
          <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
            <img src="https://cdn-icons-png.flaticon.com/512/1827/1827951.png" width="50" class="mb-2" alt="">
            <p class="fw-semibold mb-1">Uploaded by CHO after verification</p>
            <?php if (!empty($draftData['cho_cert_path'])): ?>
              <div class="mt-2">
                <a href="<?= htmlspecialchars($draftData['cho_cert_path']) ?>" target="_blank" class="btn btn-sm btn-success">View Certificate</a>
              </div>
            <?php else: ?>
              <div class="text-muted mt-2">Pending upload by CHO</div>
            <?php endif; ?>
          </div>
          <input type="hidden" name="cho_cert_path" value="<?= htmlspecialchars($draftData['cho_cert_path'] ?? '') ?>">
        </div>

      <!-- Renewal only -->
      <?php if ($type === 'renew'): ?>
      <?php $hasOld = !empty($draftData['old_pwd_id_path'] ?? ''); ?>
      <div class="mb-4">
        <?php if ($type === 'renew'): ?>
        <label class="form-label fw-semibold required">
          Old PWD ID
        </label>
        <?php endif; ?>       
        <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
          <!-- Placeholder -->
          <div id="oldpwdidPlaceholder"
              style="<?= $hasOld ? 'display:none;' : '' ?>; cursor:pointer"
              onclick="document.getElementById('oldpwdid').click()">
            <img src="https://cdn-icons-png.flaticon.com/512/892/892692.png" width="50" class="mb-2" alt="">
            <p class="fw-semibold mb-1">Drag &amp; Drop or Choose File</p>
            <div class="small text-muted">No file selected</div>
          </div>

          <!-- Filename only (no image preview) -->
          <div id="oldpwdidPreviewWrap" style="<?= $hasOld ? '' : 'display:none;' ?>">
            <div class="small text-muted mt-2" id="oldpwdidFileName">
              <?= $hasOld ? htmlspecialchars(basename($draftData['old_pwd_id_path'])) : '' ?>
            </div>
                <button type="button" class="btn btn-sm btn-danger mt-2"
            onclick="removeFile('oldpwdid', event, 'old_pwd_id_path')">Remove</button>
          </div>
        </div>

        <input type="hidden" id="old_pwd_id_path" name="old_pwd_id_path" value="<?= htmlspecialchars($draftData['old_pwd_id_path'] ?? '') ?>">
        <!-- Allow image or PDF (same as before). If you want ANY file, widen accept. -->
        <input type="file" id="oldpwdid" name="oldpwdid" accept="image/*,application/pdf" class="d-none">
      </div>
      <?php endif; ?>


      <!-- Lost only -->
      <?php if ($type === 'lost'): ?>
      <?php $hasAff = !empty($draftData['affidavit_loss_path'] ?? ''); ?>
      <div class="mb-4">
      <label class="form-label fw-semibold required">
        Affidavit of Loss
      </label>
        <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">
          <!-- Placeholder -->
          <div id="affidavitPlaceholder"
              style="<?= $hasAff ? 'display:none;' : '' ?>; cursor:pointer"
              onclick="document.getElementById('affidavit').click()">
            <img src="https://cdn-icons-png.flaticon.com/512/892/892692.png" width="50" class="mb-2" alt="">
            <p class="fw-semibold mb-1">Drag &amp; Drop or Choose File</p>
            <div class="small text-muted">No file selected</div>
          </div>

          <!-- Filename only (no image preview) -->
          <div id="affidavitPreviewWrap" style="<?= $hasAff ? '' : 'display:none;' ?>">
            <div class="small text-muted mt-2" id="affidavitFileName">
              <?= $hasAff ? htmlspecialchars(basename($draftData['affidavit_loss_path'])) : '' ?>
            </div>
            <button type="button" class="btn btn-sm btn-danger mt-2"
            onclick="removeFile('affidavit', event, 'affidavit_loss_path')">Remove</button>
          </div>
        </div>

        <input type="hidden" id="affidavit_loss_path" name="affidavit_loss_path" value="<?= htmlspecialchars($draftData['affidavit_loss_path'] ?? '') ?>">
        <!-- Allow image or PDF (same as before). If you want ANY file, widen accept. -->
        <input type="file" id="affidavit" name="affidavit" accept="image/*,application/pdf" class="d-none">
      </div>
      <?php endif; ?>


    <!-- NAV -->
    <div class="d-flex justify-content-between mt-4">
      <button type="submit" name="nav" value="back" class="btn btn-outline-primary">Back</button>
      <button type="submit" name="nav" value="next" class="btn btn-primary px-4">Save & Continue</button>
    </div>

          </form>
          </div>
          
    <script>
    function setupUploadWithRemove(baseId){
      const fileInput  = document.getElementById(baseId);
      const hiddenPath = document.getElementById(baseId + '_path');
      const ph         = document.getElementById(baseId + 'Placeholder');
      const wrap       = document.getElementById(baseId + 'PreviewWrap');
      const img        = document.getElementById(baseId + 'Preview');
      const nameEl     = document.getElementById(baseId + 'FileName');

      if (!fileInput) return;

      fileInput.addEventListener('change', function(){
        const file = this.files[0];
        if (!file) return;

        hiddenPath.value = ''; // ✅ IMPORTANT
        ph.style.display = 'none';
        wrap.style.display = '';
        nameEl.textContent = file.name;

        if (img && file.type.startsWith('image/')) {
          const r = new FileReader();
          r.onload = e => { img.src = e.target.result; img.style.display = ''; };
          r.readAsDataURL(file);
        }
      });
    }

    function removeFile(baseId, ev){
      if (ev) ev.stopPropagation();
      document.getElementById(baseId).value = '';
      document.getElementById(baseId + '_path').value = '__REMOVE__';
      document.getElementById(baseId + 'PreviewWrap').style.display = 'none';
      document.getElementById(baseId + 'Placeholder').style.display = '';
    }

    document.addEventListener('DOMContentLoaded', () => {
      ['bodypic','barangaycert','medicalcert','proofdisability','oldpwdid','affidavit']
        .forEach(setupUploadWithRemove);
    });
    </script>


          <script>

          document.addEventListener('DOMContentLoaded', () => {
          setupUploadWithRemove('bodypic');
          setupUploadWithRemove('barangaycert');
          setupUploadWithRemove('medicalcert');
          setupUploadWithRemove('proofdisability');
          setupUploadWithRemove('oldpwdid');
          setupUploadWithRemove('affidavit');
        });


          </script>

          <script>
    document.querySelectorAll('.step').forEach(step => {
      step.addEventListener('click', function (e) {
        e.preventDefault();

        const targetStep = parseInt(this.dataset.step, 10);
        const maxAllowedStep = <?= (int)($_SESSION['max_step'] ?? 1) ?>;

        // 🚫 Prevent skipping ahead
        if (targetStep > maxAllowedStep) {
          alert('Please complete the previous step first.');
          return;
        }

        // ✅ Navigate safely
        window.location.href = `form${targetStep}.php?type=<?= htmlspecialchars($type) ?>`;
      });
    });
    </script>

        <script>
    document.querySelector('form').addEventListener('submit', function (e) {

      const type = "<?= $type ?>";
      let missing = [];

      const requiredFields = {
        new: ['bodypic_path', 'barangaycert_path', 'medicalcert_path'],
        renew: ['barangaycert_path', 'medicalcert_path', 'old_pwd_id_path'],
        lost: ['barangaycert_path', 'medicalcert_path', 'affidavit_loss_path']
      };

      requiredFields[type].forEach(field => {
        const el = document.querySelector(`[name="${field}"]`);
        if (!el || !el.value || el.value === '__REMOVE__') {
          missing.push(field);
        }
      });

      if (missing.length > 0) {
        e.preventDefault();
        alert('❌ Please upload all required documents before continuing.');
      }
    });
    </script>

        <script>
    document.querySelector('form').addEventListener('submit', function (e) {
      const type = <?= json_encode($type) ?>;

      const required = [];

      if (type === 'new') {
        required.push('bodypic_path');
      }
      if (type === 'renew') {
        required.push('old_pwd_id_path');
      }
      if (type === 'lost') {
        required.push('affidavit_loss_path');
      }

      // Always required
      required.push('barangaycert_path', 'medicalcert_path');

      for (const id of required) {
        const field = document.getElementById(id);
        if (!field || !field.value || field.value === '__REMOVE__') {
          e.preventDefault();
          alert('⚠️ Please upload all required documents.');
          return;
        }
      }
    });
    </script>



    <style>
      .step {
        text-decoration: none;
        color: inherit;
        cursor: pointer;
      }

      .step:hover,
      .step:visited,
      .step:active {
        text-decoration: none;
        color: inherit;
      }

      .required::after {
        content: " *";
        color: #dc3545;
        font-weight: bold;
      }


    </style>

    </body>
    </html>
