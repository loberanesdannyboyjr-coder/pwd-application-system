<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';
require_once '../../includes/UploadHelper.php';


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

$type = strtolower($_SESSION['application_type'] ?? 'new');
if ($type === 'renewal') $type = 'renew';

/* ===============================
   LOAD DRAFT
================================ */
$draftData = loadDraftData($step, $application_id);

if ($type !== 'new') {

    $res = pg_query_params(
        $conn,
        "SELECT ad.data
         FROM application a
         JOIN application_draft ad 
           ON a.application_id = ad.application_id
         WHERE a.applicant_id = $1
           AND a.workflow_status = 'pdao_approved'
           AND ad.step = 4
         ORDER BY a.created_at DESC
         LIMIT 1",
        [$applicant_id]
    );

    if ($res && pg_num_rows($res) > 0) {
        $row = pg_fetch_assoc($res);
        $approvedData = json_decode($row['data'], true);

        $draftData = array_merge($approvedData, $draftData ?? []);
    }
}

$proofExists = true;

// 🔒 LOCK FORM IF SUBMITTED
if (($draftData['workflow_status'] ?? 'draft') !== 'draft') {
    http_response_code(403);
    exit('Application already submitted. Editing is disabled.');
}

// ✅ Always prioritize CURRENT application first
$sourceApplicationId = $application_id;

// Only fallback to previous IF current has no record yet
$checkRes = pg_query_params(
    $conn,
    "SELECT 1 FROM documentrequirements WHERE application_id = $1",
    [$application_id]
);

if ($type !== 'new' && (!$checkRes || pg_num_rows($checkRes) === 0)) {

    $prevRes = pg_query_params(
        $conn,
        "SELECT application_id
         FROM application
         WHERE applicant_id = $1
           AND workflow_status = 'pdao_approved'
         ORDER BY created_at DESC
         LIMIT 1",
        [$applicant_id]
    );

    if ($prevRes && pg_num_rows($prevRes) > 0) {
        $sourceApplicationId = pg_fetch_result($prevRes, 0, 'application_id');
    }
}

// 🔥 Load documents from correct source
$sql = "
SELECT
    bodypic_path,
    pic_1x1_path,
    barangaycert_path,
    medicalcert_path,
    old_pwd_id_path,
    affidavit_loss_path,
    proof_disability_path
FROM public.documentrequirements
WHERE application_id = $1
LIMIT 1
";

$res = pg_query_params($conn, $sql, [$sourceApplicationId]);

if (!$res || pg_num_rows($res) === 0) {

    // fallback ONLY if no record yet
    if ($type !== 'new') {
        $prevRes = pg_query_params(
            $conn,
            "SELECT application_id
             FROM application
             WHERE applicant_id = $1
               AND workflow_status = 'pdao_approved'
             ORDER BY created_at DESC
             LIMIT 1",
            [$applicant_id]
        );

        if ($prevRes && pg_num_rows($prevRes) > 0) {
            $sourceApplicationId = pg_fetch_result($prevRes, 0, 'application_id');

            $res = pg_query_params($conn, $sql, [$sourceApplicationId]);
        }
    }
}
$docRow = [];
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
    'proof_disability_path' => null,
    'pic_1x1_path' => null,
];
$draftData = array_merge($defaults, $docRow, $draftData ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formData = $_POST;

    // 🔥 Normalize remove flags
    foreach ($formData as $k => $v) {
        if ($v === '__REMOVE__') {
            $formData[$k] = null;
        }
    }

    /* ===============================
       UPLOADS
    =============================== */

    function handleUpload($key, $field, $types) {
        return uploadAndReplace(
            $GLOBALS['conn'],
            $GLOBALS['application_id'],
            $key,
            $field,
            $types
        );
    }

    // Always allowed
    if ($p = handleUpload('barangaycert','barangaycert_path',['jpg','jpeg','png','pdf','doc','docx'])) {
        $formData['barangaycert_path'] = $p;
    }

    if ($p = handleUpload('medicalcert','medicalcert_path',['jpg','jpeg','png','pdf','doc','docx'])) {
        $formData['medicalcert_path'] = $p;
    }

    if ($p = handleUpload('proofdisability','proof_disability_path',['jpg','jpeg','png'])) {
        $formData['proof_disability_path'] = $p;
    }

    // NEW only
    if ($type === 'new') {
        if ($p = handleUpload('bodypic','bodypic_path',['jpg','jpeg','png'])) {
            $formData['bodypic_path'] = $p;

            // 🔥 Use bodypic as 1x1 automatically
            $formData['pic_1x1_path'] = $p;
        }
    }

    // RENEW
    if ($type === 'renew') {
        if ($p = handleUpload('oldpwdid','old_pwd_id_path',['jpg','jpeg','png','pdf'])) {
            $formData['old_pwd_id_path'] = $p;
        }
    }

    // LOST
    if ($type === 'lost') {
        if ($p = handleUpload('affidavit','affidavit_loss_path',['jpg','jpeg','png','pdf'])) {
            $formData['affidavit_loss_path'] = $p;
        }
    }

    /* ===============================
       REUSE OLD DATA (CRITICAL FIX)
    =============================== */

    // Get previous approved record
    $prevRes = pg_query_params($conn,
        "SELECT d.*
         FROM application a
         LEFT JOIN documentrequirements d 
           ON d.application_id = a.application_id
         WHERE a.applicant_id = $1
         AND a.workflow_status = 'pdao_approved'
         ORDER BY a.created_at DESC
         LIMIT 1",
        [$applicant_id]
    );

    $prev = ($prevRes && pg_num_rows($prevRes)) 
        ? pg_fetch_assoc($prevRes)
        : [];

    // 🔥 Smart reuse logic
    $fields = [
        'pic_1x1_path',
        'bodypic_path',
        'barangaycert_path',
        'medicalcert_path',
        'proof_disability_path',
        'old_pwd_id_path',
        'affidavit_loss_path'
    ];

    foreach ($fields as $f) {

        if (empty($formData[$f])) {

            // 1. keep existing current
            if (!empty($draftData[$f])) {
                $formData[$f] = $draftData[$f];
            }

            // 2. fallback to previous approved
            elseif (!empty($prev[$f])) {
                $formData[$f] = $prev[$f];
            }
        }
    }

    /* ===============================
       SAVE DRAFT
    =============================== */
    saveDraftData(4, $formData, $application_id);

    /* ===============================
       SAVE TO DATABASE
    =============================== */

    pg_query_params($conn, "
    INSERT INTO documentrequirements (
        application_id,
        pic_1x1_path,
        bodypic_path,
        barangaycert_path,
        medicalcert_path,
        proof_disability_path,
        old_pwd_id_path,
        affidavit_loss_path
    )
    VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
    ON CONFLICT (application_id) DO UPDATE SET
        pic_1x1_path = EXCLUDED.pic_1x1_path,
        bodypic_path = EXCLUDED.bodypic_path,
        barangaycert_path = EXCLUDED.barangaycert_path,
        medicalcert_path = EXCLUDED.medicalcert_path,
        proof_disability_path = EXCLUDED.proof_disability_path,
        old_pwd_id_path = EXCLUDED.old_pwd_id_path,
        affidavit_loss_path = EXCLUDED.affidavit_loss_path
    ", [
        $application_id,
        $formData['pic_1x1_path'] ?? null,
        $formData['bodypic_path'] ?? null,
        $formData['barangaycert_path'] ?? null,
        $formData['medicalcert_path'] ?? null,
        $formData['proof_disability_path'] ?? null,
        $formData['old_pwd_id_path'] ?? null,
        $formData['affidavit_loss_path'] ?? null
    ]);

    header("Location: form5.php?type=" . urlencode($type));
    exit;
}

// ===============================
// AUTO COPY 1x1 PHOTO (RENEW/LOST)
// ===============================
if (in_array($type, ['renew','lost'])) {

    if (empty($formData['pic_1x1_path'])) {

        $prevRes = pg_query_params($conn,
            "SELECT d.pic_1x1_path
             FROM application a
             LEFT JOIN documentrequirements d 
               ON d.application_id = a.application_id
             WHERE a.applicant_id = $1
             AND a.workflow_status = 'pdao_approved'
             AND d.pic_1x1_path IS NOT NULL
             ORDER BY a.created_at DESC
             LIMIT 1",
            [$applicant_id]
        );

        if ($prevRes && pg_num_rows($prevRes)) {
            $prev = pg_fetch_assoc($prevRes);
            $formData['pic_1x1_path'] = $prev['pic_1x1_path'];
        }
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

  <!-- ================= PROOF OF DISABILITY ================= -->


  <?php $hasProof = !empty($draftData['proof_disability_path'] ?? ''); ?>

<div class="mb-4">
  <label class="form-label fw-semibold">
    Proof of Disability
    <span class="text-muted fw-normal">(Upload for visible disability only)</span>
  </label>

  <div class="upload-box text-center p-4 border rounded bg-light shadow-sm">

    <!-- Placeholder -->
    <div id="proofdisabilityPlaceholder"
         style="<?= $hasProof ? 'display:none;' : '' ?>;cursor:pointer"
         onclick="document.getElementById('proofdisability').click()">
      <p class="fw-semibold mb-1">Drag & Drop or Choose Image</p>
      <div class="small text-muted">PNG / JPG only</div>
    </div>

    <!-- Preview -->
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

  <!-- ✅ hidden path (IMPORTANT) -->
  <input type="hidden"
         id="proof_disability_path"
         name="proof_disability_path"
         value="<?= htmlspecialchars($draftData['proof_disability_path'] ?? '') ?>">

  <!-- actual file input -->
  <input type="file"
         id="proofdisability"
         name="proofdisability"
         accept=".jpg,.jpeg,.png"
         style="position:absolute; left:-9999px;">
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
    <a href="form3.php?type=<?= $type ?>" class="btn btn-outline-primary">Back</a>
    <button class="btn btn-primary">Save & Continue</button>
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

    function removeFile(baseId, ev, pathField = null){
      if (ev) ev.stopPropagation();

      document.getElementById(baseId).value = '';

      const hidden = pathField
          ? document.getElementById(pathField)
          : document.getElementById(baseId + '_path');

      if (hidden) hidden.value = '__REMOVE__';

      document.getElementById(baseId + 'PreviewWrap').style.display = 'none';
      document.getElementById(baseId + 'Placeholder').style.display = '';
    }

    document.addEventListener('DOMContentLoaded', () => {
      ['bodypic','barangaycert','medicalcert','proofdisability','oldpwdid','affidavit']
        .forEach(setupUploadWithRemove);
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
  const type = <?= json_encode($type) ?>;

  const requiredMap = {
    new: [
      { path: 'bodypic_path', file: 'bodypic' },
      { path: 'barangaycert_path', file: 'barangaycert' },
      { path: 'medicalcert_path', file: 'medicalcert' }
    ],
    renew: [
      { path: 'old_pwd_id_path', file: 'oldpwdid' } // 🔥 only strictly required
    ],
    lost: [
      { path: 'affidavit_loss_path', file: 'affidavit' } // 🔥 only strictly required
    ]
  };

  const alwaysRequired = [
    { path: 'barangaycert_path', file: 'barangaycert' },
    { path: 'medicalcert_path', file: 'medicalcert' }
  ];

  const required = [
    ...(requiredMap[type] || []),
    ...alwaysRequired
  ];

  for (const doc of required) {
    const pathInput = document.querySelector(`[name="${doc.path}"]`);
    const fileInput = document.getElementById(doc.file);

    const hasSavedPath =
      pathInput && pathInput.value && pathInput.value !== '__REMOVE__';

    const hasNewFile =
      fileInput && fileInput.files && fileInput.files.length > 0;

    if (!hasSavedPath && !hasNewFile) {
      e.preventDefault();
      alert(`⚠️ Please upload required document: ${doc.file}`);
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
