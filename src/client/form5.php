<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';



$application_id = (int) $_SESSION['application_id'];
$form1 = loadDraftData(1, $application_id);
$form2 = loadDraftData(2, $application_id);
$form3 = loadDraftData(3, $application_id);
$form4 = loadDraftData(4, $application_id);

$draftData = array_merge($form1, $form2, $form3, $form4);

/* ===============================
   LOAD documentrequirements
================================ */
$docRow = [];

$res = pg_query_params(
    $conn,
    "SELECT *
     FROM public.documentrequirements
     WHERE application_id = $1
     LIMIT 1",
    [$application_id]
);

if ($res && pg_num_rows($res) > 0) {
    $docRow = pg_fetch_assoc($res);
}

/* ✅ merge ONCE */
$draftData = array_merge($draftData, $docRow);

/* ✅ explicit mapping (optional but safe) */
$draftData['pic_1x1_path'] = $docRow['pic_1x1_path'] ?? null;


$currentStep = 5;
$maxStep = $_SESSION['max_step'] ?? 5;
 
?>
 
 <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>PWD Online Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />

    <link rel="stylesheet" href="../../assets/css/global/forms.css">

<style>
  .readonly-radio {
    pointer-events: none; /* not clickable */
    opacity: 1;           /* keep normal blue highlight */
  }
</style>

  </head>

  <body>
    <?php include __DIR__ . '/../../hero/navbar.php'; ?>

  <h1 class="form-title">Application Summary</h1>
<div class="step-indicator-wrapper mb-4">
  <div class="step-indicator">

    <?php
    $steps = [
      1 => 'Personal Information',
      2 => 'Affiliation Section',
      3 => 'Approval Section',
      4 => 'Upload Documents',
      5 => 'Application Summary'
    ];

    foreach ($steps as $num => $label):
      $state =
        $num < $currentStep ? 'completed' :
        ($num === $currentStep ? 'active' : '');
    ?>
      <a href="#"
         class="step <?= $state ?>"
         data-step="<?= $num ?>">
        <div class="circle"><?= $num ?></div>
        <div class="label"><?= $label ?></div>
      </a>
    <?php endforeach; ?>

  </div>
</div>

  <main class="form-container">
    <form novalidate>
 <!-- Row 1 -->
 <div class="row g-3 mb-4 align-items-start">

  <div class="col-md-3">
    <label class="form-label fw-semibold">Applicant Type</label>
    <div class="readonly-field">
      <?= htmlspecialchars($draftData['application_type'] ?? 'N/A') ?>
    </div>
  </div>

  <div class="col-md-4">
    <label class="form-label fw-semibold">PWD Number</label>
    <div class="readonly-field">
      <?= htmlspecialchars($draftData['pwd_number'] ?? 'To be filled by PDAO') ?>
    </div>
  </div>

<div class="col-md-3">
  <label class="form-label fw-semibold">Date Applied</label>
  <div class="readonly-field">
    <?php
      if (!empty($draftData['application_date'])) {
        echo date('F j, Y', strtotime($draftData['application_date']));
      } else {
        echo date('F j, Y'); // preview only
      }
    ?>
  </div>
</div>


    <!-- PHOTO -->
    <div class="col-md-2 text-center">
      <label class="form-label fw-semibold d-block">Photo</label>

      <?php
        $photo = $draftData['pic_1x1_path'] ?? '';
      ?>

      <?php if (!empty($photo)): ?>
        <img src="<?= htmlspecialchars($photo) ?>"
            class="img-thumbnail"
            style="width:120px;height:120px;object-fit:cover;">
      <?php else: ?>
        <div class="text-muted small">Not uploaded</div>
      <?php endif; ?>
    </div>

    </div>

<!-- ROW 2 -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <label class="form-label fw-semibold">Last Name</label>
    <div class="readonly-field"><?= htmlspecialchars($draftData['last_name'] ?? 'N/A') ?></div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">First Name</label>
    <div class="readonly-field"><?= htmlspecialchars($draftData['first_name'] ?? 'N/A') ?></div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Middle Name</label>
    <div class="readonly-field"><?= htmlspecialchars($draftData['middle_name'] ?? 'N/A') ?></div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Suffix</label>
    <div class="readonly-field"><?= htmlspecialchars($draftData['suffix'] ?? 'N/A') ?></div>
  </div>
</div>

      <!-- Row 3 -->
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Date of Birth</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['birthdate'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Sex</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['sex'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Civil Status</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['civil_status'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Type of Disability</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['disability_type'] ?? 'N/A') ?></div>
        </div>
      </div>

<!-- Row 4 -->
<div class="row g-3 mb-3 align-items-end">
  <div class="col-md-3">
    <label class="form-label fw-semibold text-primary">Cause of Disability</label>
    <div class="d-flex gap-3 mb-2" style="font-size: 0.85rem;">
      <div class="form-check mb-0">
        <input 
          class="form-check-input readonly-radio" 
          type="radio" 
          id="causeCongenital" 
          value="Congenital/Inborn"
          <?= (($draftData['cause_detail'] ?? $draftData['cause'] ?? '') === 'Congenital/Inborn') ? 'checked' : '' ?>>
        <label class="form-check-label" for="causeCongenital">Congenital/Inborn</label>
      </div>
      <div class="form-check mb-0">
        <input 
          class="form-check-input readonly-radio" 
          type="radio" 
          id="causeAcquired" 
          value="Acquired"
          <?= (($draftData['cause_detail'] ?? $draftData['cause'] ?? '') === 'Acquired') ? 'checked' : '' ?>>
        <label class="form-check-label" for="causeAcquired">Acquired</label>
      </div>
    </div>

    <!-- Cause description -->
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['cause_description'] ?? 'N/A') ?>
    </div>
  </div>

  <div class="col-md-3">
    <label class="form-label fw-semibold">House No. and Street</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['house_no_street'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Barangay</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['barangay'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Municipality</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['municipality'] ?? 'N/A') ?>
    </div>
  </div>
</div>




      <!-- Row 5 -->
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Province</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['province'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Region</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['region'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Landline No.</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['landline_no'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-semibold">Mobile No.</label>
          <div class="form-control bg-light"><?= htmlspecialchars($draftData['mobile_no'] ?? 'N/A') ?></div>
        </div>
      </div>

      <!-- Row 6 -->
<div class="mb-3">
  <label class="form-label fw-semibold">E-mail Address:</label>
  <div class="form-control bg-light">
    <?= htmlspecialchars($draftData['email_address'] ?? 'N/A') ?>
  </div>
</div>

<div class="row g-2 align-items-start">
  <div class="col-md-4 pe-md-2">
    <div class="mb-2">
      <label class="form-label fw-semibold">Educational Attainment</label>
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['educational_attainment'] ?? 'N/A') ?>
      </div>
    </div>

    <div class="mb-2">
      <label class="form-label fw-semibold">Status of Employment</label>
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['employment_status'] ?? 'N/A') ?>
      </div>
    </div>

    <div class="mb-2">
      <label class="form-label fw-semibold">Category of Employment</label>
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['employment_category'] ?? 'N/A') ?>
      </div>
    </div>

    <div class="mb-0">
      <label class="form-label fw-semibold">Type of Employment</label>
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['type_of_employment'] ?? 'N/A') ?>
      </div>
    </div>
  </div>
<!-- Right Column: Occupation (read-only with radios) -->
<div class="col-md-8">
  <label class="form-label fw-semibold mb-2" style="font-size: 1.25rem;">Occupation</label>
  <div class="row g-0">

    <?php
      // All occupation choices from Form 2
      $occupations = [
        'Managers',
        'Professionals',
        'Technicians and Associate Professionals',
        'Clerical Support Workers',
        'Service and Sales Workers',
        'Skilled Agricultural, Forestry and Fishery Workers',
        'Craft and Related Trade Workers',
        'Plant and Machinery Operators and Assemblers',
        'Elementary Occupations',
        'Armed Forces Occupations',
        'Others'
      ];
      $selectedOcc = $draftData['occupation'] ?? '';
      $otherOcc = $draftData['occupation_others'] ?? '';
    ?>

    <div class="col-md-6">
      <?php foreach (array_slice($occupations, 0, 6) as $occ): ?>
        <div class="form-check">
          <input class="form-check-input readonly-radio" type="radio" name="occ"
                 id="<?= strtolower(str_replace(' ', '_', $occ)) ?>"
                 value="<?= htmlspecialchars($occ) ?>"
                 <?= ($selectedOcc === $occ) ? 'checked' : '' ?>>
          <label class="form-check-label ms-1 text-dark"
                 for="<?= strtolower(str_replace(' ', '_', $occ)) ?>">
            <?= htmlspecialchars($occ) ?>
          </label>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="col-md-6">
      <?php foreach (array_slice($occupations, 6, 4) as $occ): ?>
        <div class="form-check">
          <input class="form-check-input readonly-radio" type="radio" name="occ"
                 id="<?= strtolower(str_replace(' ', '_', $occ)) ?>"
                 value="<?= htmlspecialchars($occ) ?>"
                 <?= ($selectedOcc === $occ) ? 'checked' : '' ?>>
          <label class="form-check-label ms-1 text-dark"
                 for="<?= strtolower(str_replace(' ', '_', $occ)) ?>">
            <?= htmlspecialchars($occ) ?>
          </label>
        </div>
      <?php endforeach; ?>

      <!-- Others -->
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input me-2 readonly-radio" type="radio" name="occ" id="others"
               value="Others" <?= ($selectedOcc === 'Others') ? 'checked' : '' ?>>
        <label class="form-check-label me-2 text-dark" for="others">Others, specify:</label>
        <input type="text" class="form-control form-control-sm bg-light"
               style="width: 150px;" readonly
               value="<?= htmlspecialchars($otherOcc ?: 'N/A') ?>">
      </div>
    </div>
  </div>
</div>

<!-- Organization Info -->
<div class="row g-2 mt-3">
  <label class="form-label fw-semibold text-primary mb-1" style="font-size: 1.2rem;">
    Organization Information
  </label>

  <div class="col-md-3">
    <label class="form-label fw-semibold">Organization Affiliated</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['organization_affiliated'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Contact Person</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['contact_person'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Office Address</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['office_address'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Tel No.</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['tel_no'] ?? 'N/A') ?>
    </div>
  </div>
</div>

<!-- ID Reference -->
<div class="row g-3 mt-1">
  <label class="form-label fw-semibold text-primary mb-0" style="font-size: 1.2rem;">
    ID Reference No.
  </label>

  <div class="col-md-3">
    <label class="form-label fw-semibold">SSS No.</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['sss_no'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">GSIS No.</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['gsis_no'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">Pag-IBIG No.</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['pagibig_no'] ?? 'N/A') ?>
    </div>
  </div>
  <div class="col-md-3">
    <label class="form-label fw-semibold">PhilHealth No.</label>
    <div class="form-control bg-light">
      <?= htmlspecialchars($draftData['philhealth_no'] ?? 'N/A') ?>
    </div>
  </div>
</div>

<!-- CSS for read-only radios -->
<style>
  .readonly-radio {
    pointer-events: none;
    opacity: 1; /* keeps checked radios blue */
  }
</style>

<!-- Family Background (Read-Only) -->
<div class="mt-4">
  <div class="row mb-1 align-items-end">
    <div class="col-md-3">
      <div class="fw-semibold text-primary" style="font-size: 1.20rem;">Family Background:</div>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-0">Last Name</label>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-0">First Name</label>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-0">Middle Name</label>
    </div>
  </div>

  <!-- Father's Name -->
  <div class="row g-2 align-items-center text-center">
    <div class="col-md-3">
      <label class="form-label" style="font-size: 0.95rem;">Father's Name:</label>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['father_last_name'] ?? 'N/A') ?>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['father_first_name'] ?? 'N/A') ?>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['father_middle_name'] ?? 'N/A') ?>
      </div>
    </div>
  </div>

  <!-- Mother's Name -->
  <div class="row g-2 align-items-center text-center mt-2">
    <div class="col-md-3">
      <label class="form-label" style="font-size: 0.95rem;">Mother's Name:</label>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['mother_last_name'] ?? 'N/A') ?>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['mother_first_name'] ?? 'N/A') ?>
      </div>
    </div>
    <div class="col-md-3">
      <div class="form-control bg-light">
        <?= htmlspecialchars($draftData['mother_middle_name'] ?? 'N/A') ?>
      </div>
    </div>
  </div>

<!-- Accomplished By (Read-Only) -->
<div class="row g-3 mt-3 align-items-center">
  <div class="col-md-3" style="margin-top: -10px;">
    <label class="form-label fw-semibold text-primary mb-2" style="font-size: 1.2rem;">Accomplished By:</label>

    <?php $accomplishedBy = $draftData['accomplished_by'] ?? ''; ?>
    <div class="d-grid gap-3">
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input readonly-radio me-2" type="radio" id="by_applicant"
               value="Applicant" <?= ($accomplishedBy === 'Applicant') ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="by_applicant">Applicant</label>
      </div>
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input readonly-radio me-2" type="radio" id="by_guardian"
               value="Guardian" <?= ($accomplishedBy === 'Guardian') ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="by_guardian">Guardian</label>
      </div>
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input readonly-radio me-2" type="radio" id="by_rep"
               value="Representative" <?= ($accomplishedBy === 'Representative') ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="by_rep">Representative</label>
      </div>
    </div>
  </div>

  <?php
    // Resolve name fields based on who accomplished it (with fallbacks)
    switch ($accomplishedBy) {
      case 'Guardian':
        $lastKeys   = ['acc_last_name_guardian','guardian_last_name','acc_last_name','last_name'];
        $firstKeys  = ['acc_first_name_guardian','guardian_first_name','acc_first_name','first_name'];
        $middleKeys = ['acc_middle_name_guardian','guardian_middle_name','acc_middle_name','middle_name'];
        break;
      case 'Representative':
        $lastKeys   = ['acc_last_name_rep','representative_last_name','acc_last_name','last_name'];
        $firstKeys  = ['acc_first_name_rep','representative_first_name','acc_first_name','first_name'];
        $middleKeys = ['acc_middle_name_rep','representative_middle_name','acc_middle_name','middle_name'];
        break;
      case 'Applicant':
      default:
        $lastKeys   = ['acc_last_name_applicant','applicant_last_name','acc_last_name','last_name'];
        $firstKeys  = ['acc_first_name_applicant','applicant_first_name','acc_first_name','first_name'];
        $middleKeys = ['acc_middle_name_applicant','applicant_middle_name','acc_middle_name','middle_name'];
        break;
    }
    $pick = function(array $keys) use ($draftData) {
      foreach ($keys as $k) if (!empty($draftData[$k])) return $draftData[$k];
      return 'N/A';
    };
    $accLast   = $pick($lastKeys);
    $accFirst  = $pick($firstKeys);
    $accMiddle = $pick($middleKeys);
  ?>

  <!-- Name Fields (Read-Only) -->
  <div class="col-md-9">
    <div class="row fw-semibold mb-1">
      <label class="col-md-4">Last Name</label>
      <label class="col-md-4">First Name</label>
      <label class="col-md-4">Middle Name</label>
    </div>

    <div class="row g-2 mb-2">
      <div class="col-md-4"><div class="readonly-field"><?= htmlspecialchars($accLast) ?></div></div>
      <div class="col-md-4"><div class="readonly-field"><?= htmlspecialchars($accFirst) ?></div></div>
      <div class="col-md-4"><div class="readonly-field"><?= htmlspecialchars($accMiddle) ?></div></div>
    </div>
  </div>
</div>


<!-- Keep radios visually active but non-clickable -->
<style>
  .readonly-radio {
    pointer-events: none; /* disable click */
    opacity: 1;           /* keep visible */
    accent-color: #0d6efd; /* force Bootstrap blue */
  }
</style>
<div class="mt-4">
  <div class="row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label">Name of Certifying Physician:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['certifying_physician'] ?? 'N/A') ?></div>
    </div>
    <div class="col-md-6">
      <label class="form-label">License No.:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['license_no'] ?? 'N/A') ?></div>
    </div>

    <div class="col-md-6">
      <label class="form-label">Processing Officer:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['processing_officer'] ?? 'N/A') ?></div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Approving Officer:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['approving_officer'] ?? 'N/A') ?></div>
    </div>

    <div class="col-md-6">
      <label class="form-label">Encoder:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['encoder'] ?? 'N/A') ?></div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Name of Reporting Unit (Office/Section):</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['reporting_unit'] ?? 'N/A') ?></div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Control No.:</label>
      <div class="readonly-field"><?= htmlspecialchars($draftData['control_no'] ?? 'N/A') ?></div>
    </div>
  </div>
</div>

<!-- Keep radios visually active -->
<style>
  .readonly-radio {
    pointer-events: none;
    opacity: 1; /* Keep blue shading for checked items */
  }
</style>


        <div class="mb-3 border-start border-4 border-primary bg-light rounded p-2 ps-3 fw-semibold text-primary">
        IN CASE OF EMERGENCY
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <label class="form-label">Contact Person’s Name:</label>
          <div class="readonly-field"><?= htmlspecialchars($draftData['contact_person_name'] ?? 'N/A') ?></div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Contact Person’s No.:</label>
          <div class="readonly-field"><?= htmlspecialchars($draftData['contact_person_no'] ?? 'N/A') ?></div>
        </div>
      </div>
<?php
// Normalize type
$type = strtolower($_SESSION['application_type'] ?? 'new');
if ($type === 'renewal') $type = 'renew';

// Build the list of files to show (only add if present)
$files = [];


// New application: whole body picture
if ($type === 'new' && !empty($draftData['bodypic_path'])) {
  $files[] = ['label' => 'Whole Body Picture', 'path' => $draftData['bodypic_path']];
}

// Common
if (!empty($draftData['barangaycert_path'])) {
  $files[] = ['label' => 'Barangay Certificate', 'path' => $draftData['barangaycert_path']];
}
if (!empty($draftData['medicalcert_path'])) {
  $files[] = ['label' => 'Medical Certificate', 'path' => $draftData['medicalcert_path']];
}

if (!empty($draftData['proof_disability_path'])) {
  $files[] = [
    'label' => 'Proof of Disability',
    'path'  => $draftData['proof_disability_path']
  ];
}


// Renewal only
if ($type === 'renew' && !empty($draftData['old_pwd_id_path'])) {
  $files[] = ['label' => 'Old PWD ID', 'path' => $draftData['old_pwd_id_path']];
}

// Lost only
if ($type === 'lost' && !empty($draftData['affidavit_loss_path'])) {
  $files[] = ['label' => 'Affidavit of Loss', 'path' => $draftData['affidavit_loss_path']];
}

// CHO uploads (if any)
if (!empty($draftData['cho_cert_path'])) {
  $files[] = ['label' => 'CHO Certificate', 'path' => $draftData['cho_cert_path']];
}



// Helpers
$prettyName = function(string $p) {
  $path = parse_url($p, PHP_URL_PATH) ?: $p;
  return basename($path);
};
$viewTarget = function(string $p) {
  $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','gif','webp','pdf']) ? '_blank' : '_self';
};
?>

<div class="mb-3 border-start border-4 border-primary bg-light rounded p-2 ps-3 fw-semibold text-primary">
  FILES
</div>

<?php if (!empty($files)): ?>
  <ul class="list-unstyled mb-4">
    <?php foreach ($files as $f): $name = $prettyName($f['path']); ?>
      <li class="d-flex align-items-center py-2 border-bottom">
        <span class="me-3" aria-hidden="true" style="font-size:1.1rem;"></span>
        <div class="flex-grow-1">
          <div class="fw-semibold text-dark"><?= htmlspecialchars($f['label']) ?></div>
          <div class="small text-muted"><?= htmlspecialchars($name) ?></div>
        </div>
        <div class="ms-3">
          <a class="link-primary fw-semibold me-3"
             href="<?= htmlspecialchars($f['path']) ?>"
             target="<?= $viewTarget($f['path']) ?>">View</a>
          <a class="link-primary fw-semibold"
             href="<?= htmlspecialchars($f['path']) ?>" download>Download</a>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else: ?>
  <div class="text-muted mb-4">No files uploaded.</div>
<?php endif; ?>

      <!-- Buttons -->
      <div class="d-flex justify-content-between mt-4">
        <a href="form4.php" class="btn btn-outline-primary">Back</a>

        <?php if (($draftData['workflow_status'] ?? '') === 'submitted'): ?>
          <button class="btn btn-success" disabled>
            ✔ Already Submitted
          </button>
        <?php else: ?>
          <button id="submitBtn" type="button" class="btn btn-success">
            Confirm & Submit
          </button>
        <?php endif; ?>
      </div>

          <script>
    document.querySelectorAll('.step').forEach(step => {
      step.addEventListener('click', function (e) {
        e.preventDefault();

        const targetStep = parseInt(this.dataset.step, 10);
        const maxAllowed = <?= (int)($_SESSION['max_step'] ?? 5) ?>;

        if (targetStep > maxAllowed) {
          alert('Please complete the previous step first.');
          return;
        }

        window.location.href = `form${targetStep}.php?type=<?= urlencode($type) ?>`;
      });
    });
    </script>


      <script>
document.getElementById('submitBtn').addEventListener('click', async function () {

  const btn = this;
  btn.disabled = true;
  btn.innerText = 'Submitting...';

  try {
   const response = await fetch('/api/submit_application.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    application_id: <?= (int) $application_id ?>
  })
});

    const data = await response.json();

    if (!response.ok || !data.success) {
      throw new Error(data.error || 'Submission failed');
    }
    alert('✅ Your application has been submitted successfully.');

    // ✅ redirect to client home
    window.location.href = 'http://localhost:8080/public/index.php';

  } catch (err) {
    console.error(err); // ✅ debugging
    alert('❌ ' + err.message);

    btn.disabled = false;
    btn.innerText = 'Confirm & Submit';
  }
});
</script>



        </form>
      </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        


        <style>
      /* Uniform read-only box that matches Bootstrap form-control look */
      .readonly-field{
        background-color:#f8f9fa;
        border:1px solid #ced4da;
        border-radius:.375rem;       /* same as .form-control */
        padding:.47rem .75rem;       /* visual match to inputs */
        min-height:38px;             /* default bs form-control height */
        display:flex;
        align-items:center;          /* vertically center text */
        color:#212529;
        line-height:1.5;
      }

      /* keep radios non-interactive and blue */

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
    </style>

  </body>
  </html>
