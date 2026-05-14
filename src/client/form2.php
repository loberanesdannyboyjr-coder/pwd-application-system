<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';

//Check session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['applicant_id']) || !isset($_SESSION['application_id'])) {
    header("Location: ../../public/login_form.php");
    exit;
}

$applicant_id   = (int)$_SESSION['applicant_id'];
$application_id = (int)$_SESSION['application_id'];

// Resolve application type (url -> post -> session)
$type = strtolower($_GET['type'] ?? $_POST['type'] ?? ($_SESSION['application_type'] ?? 'new'));
if ($type === 'renewal') $type = 'renew';
if (!in_array($type, ['new','renew','lost'], true)) $type = 'new';
$_SESSION['application_type'] = $type;

//  Load draft data for Step 2
$step = 2;
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
           AND ad.step = 2
         ORDER BY a.created_at DESC
         LIMIT 1",
        [$applicant_id]
    );

    if ($res && pg_num_rows($res) > 0) {
        $row = pg_fetch_assoc($res);

        $approvedData = json_decode($row['data'], true);

        // IMPORTANT
        $draftData = array_merge($approvedData, $draftData ?? []);
    }
}

// 🔒 LOCK FORM IF ALREADY SUBMITTED
if (($draftData['workflow_status'] ?? 'draft') !== 'draft') {
    http_response_code(403);
    exit('Application already submitted. Editing is disabled.');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    saveDraftData($step, $_POST, $application_id);

    // Optional: structured session caching for final submit
    $_SESSION['affiliation'] = [
        'educational_attainment' => $_POST['educational_attainment'] ?? null,
        'employment_status'      => $_POST['employment_status'] ?? null,
        'employment_category'    => $_POST['employment_category'] ?? null,
        'occupation'             => $_POST['occupation'] ?? null,
        'type_of_employment'     => $_POST['type_of_employment'] ?? null,
        'organization_affiliated'=> $_POST['organization_affiliated'] ?? null,
        'contact_person'         => $_POST['contact_person'] ?? null,
        'office_address'         => $_POST['office_address'] ?? null,
        'tel_no'                 => $_POST['tel_no'] ?? null,
        'sss_no'                 => $_POST['sss_no'] ?? null,
        'gsis_no'                => $_POST['gsis_no'] ?? null,
        'pagibig_no'             => $_POST['pagibig_no'] ?? null,
        'psn_no'                 => $_POST['psn_no'] ?? null,
        'philhealth_no'          => $_POST['philhealth_no'] ?? null,
    ];

    $_SESSION['accomplishedby'] = [
        'accomplished_by' => $_POST['accomplished_by'] ?? null,
        'last_name'       => $_POST['acc_last_name'] ?? null,
        'first_name'      => $_POST['acc_first_name'] ?? null,
        'middle_name'     => $_POST['acc_middle_name'] ?? null,
    ];

    $_SESSION['familybackground'] = [
        'father_name'   => $_POST['father_name'] ?? null,
        'mother_name'   => $_POST['mother_name'] ?? null,
        'guardian_name' => $_POST['guardian_name'] ?? null,
    ];

    // Redirect to step 3, keep ?type
    header("Location: form3.php?type=" . urlencode($type));
    exit;
}
$currentStep = 2;

// Initialize max_step if not set
$_SESSION['max_step'] = $_SESSION['max_step'] ?? 1;

// Never allow going backwards
if ($_SESSION['max_step'] < $currentStep) {
    $_SESSION['max_step'] = $currentStep;
}

$isLocked = ($type === 'renew' || $type === 'lost');

// editable fields for renew/lost
$editableFields = [
    'employment_status',
    'employment_category',
    'type_of_employment',
    'occupation',

    'organization_affiliated',
    'contact_person',
    'office_address',
    'tel_no',

    'accomplished_by'
];

// helper
function isEditable($field, $editableFields, $isLocked) {
    if (!$isLocked) return true;
    return in_array($field, $editableFields);
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
  <form method="POST">
    <div class="row g-2 align-items-start">
      <div class="col-md-4 pe-md-2">

<!-- Educational Attainment -->
<div class="mb-2">
  <label class="form-label fw-semibold required">Educational Attainment</label>

  <select name="educational_attainment" class="form-select" required
    <?= $isLocked ? 'disabled' : '' ?>>

    <option value="">Please Select</option>

    <option value="None" <?= ($draftData['educational_attainment'] ?? '') === 'None' ? 'selected' : '' ?>>None</option>
    <option value="Kindergarten" <?= ($draftData['educational_attainment'] ?? '') === 'Kindergarten' ? 'selected' : '' ?>>Kindergarten</option>
    <option value="Elementary" <?= ($draftData['educational_attainment'] ?? '') === 'Elementary' ? 'selected' : '' ?>>Elementary</option>
    <option value="Junior High School" <?= ($draftData['educational_attainment'] ?? '') === 'Junior High School' ? 'selected' : '' ?>>Junior High School</option>
    <option value="Senior High School" <?= ($draftData['educational_attainment'] ?? '') === 'Senior High School' ? 'selected' : '' ?>>Senior High School</option>
    <option value="College" <?= ($draftData['educational_attainment'] ?? '') === 'College' ? 'selected' : '' ?>>College</option>
    <option value="Vocational" <?= ($draftData['educational_attainment'] ?? '') === 'Vocational' ? 'selected' : '' ?>>Vocational</option>
    <option value="Post Graduate" <?= ($draftData['educational_attainment'] ?? '') === 'Post Graduate' ? 'selected' : '' ?>>Post Graduate</option>

  </select>

  <!-- Hidden fallback -->
  <?php if ($isLocked): ?>
  <input type="hidden" name="educational_attainment"
    value="<?= htmlspecialchars($draftData['educational_attainment'] ?? '') ?>">
  <?php endif; ?>
</div>


        <!-- Status of Employment -->
          <div class="mb-2">
            <label class="form-label fw-semibold required">Status of Employment</label>
            <select name="employment_status" class="form-select" required>
              <option value="">Please Select</option>
              <option value="Employed" <?= ($draftData['employment_status'] ?? '') === 'Employed' ? 'selected' : '' ?>>Employed</option>
              <option value="Unemployed" <?= ($draftData['employment_status'] ?? '') === 'Unemployed' ? 'selected' : '' ?>>Unemployed</option>
              <option value="Self-employed" <?= ($draftData['employment_status'] ?? '') === 'Self-employed' ? 'selected' : '' ?>>Self-employed</option>
            </select>
          </div>


        <!-- Category of Employment -->
        <div class="mb-2">
        <label id="employmentCategoryLabel"
            class="form-label fw-semibold conditional-required">
        Category of Employment
      </label>
        <select name="employment_category" id="employment_category" class="form-select">
            <option value="">Please Select</option>
            <option value="Government" <?= ($draftData['employment_category'] ?? '') === 'Government' ? 'selected' : '' ?>>Government</option>
            <option value="Private" <?= ($draftData['employment_category'] ?? '') === 'Private' ? 'selected' : '' ?>>Private</option>
            <option value="Others" <?= ($draftData['employment_category'] ?? '') === 'Others' ? 'selected' : '' ?>>Others</option>
          </select>
        </div>


        <!-- Types of Employment -->
          <div class="mb-0">
            <label id="employmentTypeLabel"
              class="form-label fw-semibold conditional-required">
          Types of Employment
        </label>
            <select name="type_of_employment" id="type_of_employment" class="form-select">
              <option value="">Please Select</option>
              <option value="Permanent/Regular" <?= ($draftData['type_of_employment'] ?? '') === 'Permanent/Regular' ? 'selected' : '' ?>>Permanent / Regular</option>
              <option value="Seasonal" <?= ($draftData['type_of_employment'] ?? '') === 'Seasonal' ? 'selected' : '' ?>>Seasonal</option>
              <option value="Casual" <?= ($draftData['type_of_employment'] ?? '') === 'Casual' ? 'selected' : '' ?>>Casual</option>
              <option value="Emergency" <?= ($draftData['type_of_employment'] ?? '') === 'Emergency' ? 'selected' : '' ?>>Emergency</option>
            </select>
          </div>
        </div>


      <!-- Right Column: Occupations -->
      <div class="col-md-8">
        <label class="form-label fw-semibold conditional-required" id="occupationLabel"
              style="font-size: 1.25rem;">
          Occupation
        </label>
        <div class="row g-0">
          <div class="col-md-6">

            <div class="form-check">
        <input class="form-check-input" type="radio" name="occupation" id="occ_managers"
              value="Managers"
              <?= ($draftData['occupation'] ?? '') === 'Managers' ? 'checked' : '' ?>
              <?= $isLocked ? 'disabled' : '' ?>>
              <label class="form-check-label ms-1" for="occ_managers">Managers</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_professionals"
                    value="Professionals"
                    <?= ($draftData['occupation'] ?? '') === 'Professionals' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_professionals">Professionals</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_tech"
                    value="Technicians and Associate Professionals"
                    <?= ($draftData['occupation'] ?? '') === 'Technicians and Associate Professionals' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_tech">
                Technicians and Associate Professionals
              </label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_clerical"
                    value="Clerical Support Workers"
                    <?= ($draftData['occupation'] ?? '') === 'Clerical Support Workers' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_clerical">Clerical Support Workers</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_service"
                    value="Service and Sales Workers"
                    <?= ($draftData['occupation'] ?? '') === 'Service and Sales Workers' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_service">Service and Sales Workers</label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_skilled"
                    value="Skilled Agricultural, Forestry and Fishery Workers"
                    <?= ($draftData['occupation'] ?? '') === 'Skilled Agricultural, Forestry and Fishery Workers' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_skilled">
                Skilled Agricultural, Forestry and Fishery Workers
              </label>
            </div>

          </div>

          <div class="col-md-6">

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_craft"
                    value="Craft and Related Trade Workers"
                    <?= ($draftData['occupation'] ?? '') === 'Craft and Related Trade Workers' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_craft">
                Craft and Related Trade Workers
              </label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_plant"
                    value="Plant and Machinery Operators and Assemblers"
                    <?= ($draftData['occupation'] ?? '') === 'Plant and Machinery Operators and Assemblers' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_plant">
                Plant and Machinery Operators and Assemblers
              </label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_elementary"
                    value="Elementary Occupations"
                    <?= ($draftData['occupation'] ?? '') === 'Elementary Occupations' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_elementary">
                Elementary Occupations
              </label>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="radio" name="occupation" id="occ_armed"
                    value="Armed Forces Occupations"
                    <?= ($draftData['occupation'] ?? '') === 'Armed Forces Occupations' ? 'checked' : '' ?>>
              <label class="form-check-label ms-1" for="occ_armed">
                Armed Forces Occupations
              </label>
            </div>

            <!-- Others -->
            <div class="form-check d-flex align-items-center mt-1">
              <input class="form-check-input me-2" type="radio" name="occupation" id="occ_others"
                  value="Others"
                  <?= ($draftData['occupation'] ?? '') === 'Others' ? 'checked' : '' ?>
                  onchange="toggleOtherOccupation()">
              <label class="form-check-label me-2" for="occ_others">Others, specify:</label>
              <input type="text"
                    name="occupation_others"
                    class="form-control form-control-sm"
                    style="width: 160px;"
                    value="<?= htmlspecialchars($draftData['occupation_others'] ?? '') ?>">
            </div>

          </div>
        </div>
      </div>


          <!-- Organization Info -->
      <div class="mt-3">
        <label class="form-label fw-semibold text-primary" style="font-size: 1.2rem;">
          Organization Information:
        </label>
      </div>

      <div class="row g-2">

        <div class="col-md-3">
          <label class="form-label fw-semibold">Organization Affiliated</label>
          <input type="text" class="form-control" name="organization_affiliated" value="<?= htmlspecialchars($draftData['organization_affiliated'] ?? '') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">Contact Person</label>
          <input type="text" class="form-control" name="contact_person"
            value="<?= htmlspecialchars($draftData['contact_person'] ?? '') ?>"
            <?= (!in_array('contact_person', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">Office Address</label>
          <input class="form-control" name="office_address"
          value="<?= htmlspecialchars($draftData['office_address'] ?? '') ?>"
          <?= (!in_array('office_address', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>

        <div class="col-md-3">
          <label class="form-label fw-semibold">Tel No.</label>
          <input class="form-control" name="tel_no"
          value="<?= htmlspecialchars($draftData['tel_no'] ?? '') ?>"
          <?= (!in_array('tel_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
      </div>

          <div class="row g-3 mb-3">
      <label class="form-label fw-semibold text-primary mb-0" style="font-size: 1.2rem;">ID Reference No.:</label>

      <div class="col-md-3">
        <label for="sss_no" class="form-label fw-semibold">SSS No.</label>
        <input type="text" name="sss_no" id="sss_no" class="form-control"
          value="<?= htmlspecialchars($draftData['sss_no'] ?? '') ?>"
          <?= (!in_array('sss_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
      </div>

      <div class="col-md-3">
        <label for="gsis_no" class="form-label fw-semibold">GSIS No.</label>
        <input type="text" name="gsis_no" id="gsis_no" class="form-control"
          value="<?= htmlspecialchars($draftData['gsis_no'] ?? '') ?>"
          <?= (!in_array('gsis_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
      </div>

      <div class="col-md-3">
        <label for="pagibig_no" class="form-label fw-semibold">Pag-ibig No.</label>
        <input type="text" name="pagibig_no" id="pagibig_no" class="form-control"
          value="<?= htmlspecialchars($draftData['pagibig_no'] ?? '') ?>"
          <?= (!in_array('pagibig_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
      </div>

      <div class="col-md-3">
        <label for="philhealth_no" class="form-label fw-semibold">PhilHealth No.</label>
        <input type="text" name="philhealth_no" id="philhealth_no" class="form-control"
          value="<?= htmlspecialchars($draftData['philhealth_no'] ?? '') ?>"
          <?= (!in_array('philhealth_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
      </div>
    </div>


    <!-- Family Background -->
    <div class="mt-4">
      <div class="row mb-1 align-items-end">
        <div class="col-md-3">
          <div class="fw-semibold text-primary" style="font-size: 1.20rem;">Family Background:</div>
        </div>
        <div class="col-md-3"><label class="form-label mb-0">Last Name</label></div>
        <div class="col-md-3"><label class="form-label mb-0">First Name</label></div>
        <div class="col-md-3"><label class="form-label mb-0">Middle Name</label></div>
      </div>

      <!-- Father's Name -->
      <div class="row g-2 align-items-center text-center">
        <div class="col-md-3"><label class="form-label" style="font-size: 0.95rem;">Father's Name:</label></div>
        <div class="col-md-3">
          <input type="text" name="father_last_name" id="father_last_name" class="form-control"
            value="<?= htmlspecialchars($draftData['father_last_name'] ?? '') ?>"
            <?= (!in_array('father_last_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="father_first_name" id="father_first_name" class="form-control"
            value="<?= htmlspecialchars($draftData['father_first_name'] ?? '') ?>"
            <?= (!in_array('father_first_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="father_middle_name" id="father_middle_name" class="form-control"
            value="<?= htmlspecialchars($draftData['father_middle_name'] ?? '') ?>"
            <?= (!in_array('father_middle_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
      </div>

      <!-- Mother's Name -->
      <div class="row g-2 align-items-end text-center mt-2">
        <div class="col-md-3"><label class="form-label" style="font-size: 0.95rem;">Mother's Name:</label></div>
        <div class="col-md-3">
          <input type="text" name="mother_last_name" id="mother_last_name" class="form-control"
            value="<?= htmlspecialchars($draftData['mother_last_name'] ?? '') ?>"
            <?= (!in_array('mother_last_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="mother_first_name" id="mother_first_name" class="form-control"
            value="<?= htmlspecialchars($draftData['mother_first_name'] ?? '') ?>"
            <?= (!in_array('mother_first_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="mother_middle_name" id="mother_middle_name" class="form-control"
            value="<?= htmlspecialchars($draftData['mother_middle_name'] ?? '') ?>"
            <?= (!in_array('mother_middle_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
      </div>

      <!-- Guardian's Name -->
      <div class="row g-2 align-items-end text-center mt-2">
        <div class="col-md-3"><label class="form-label" style="font-size: 0.95rem;">Guardian's Name:</label></div>
        <div class="col-md-3">
          <input type="text" name="guardian_last_name" id="guardian_last_name" class="form-control"
            value="<?= htmlspecialchars($draftData['guardian_last_name'] ?? '') ?>"
            <?= (!in_array('guardian_last_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="guardian_first_name" id="guardian_first_name" class="form-control"
            value="<?= htmlspecialchars($draftData['guardian_first_name'] ?? '') ?>"
            <?= (!in_array('guardian_first_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
        <div class="col-md-3">
          <input type="text" name="guardian_middle_name" id="guardian_middle_name" class="form-control"
            value="<?= htmlspecialchars($draftData['guardian_middle_name'] ?? '') ?>"
            <?= (!in_array('guardian_middle_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
        </div>
      </div>
    </div>


      <div class="row g-3 mt-3 align-items-center">

<!-- Accomplished By -->
<div class="row g-3 mb-3">
  <div class="col-md-3" style="margin-top: -10px;">
    <label class="form-label fw-semibold text-primary mb-2" style="font-size: 1.2rem;">Accomplished By:</label>

    <div class="d-grid gap-3">
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input me-2" type="radio" name="accomplished_by" id="applicant" value="Applicant"
          <?= ($draftData['accomplished_by'] ?? '') === 'Applicant' ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="applicant">Applicant</label>
      </div>
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input me-2" type="radio" name="accomplished_by" id="guardian" value="Guardian"
          <?= ($draftData['accomplished_by'] ?? '') === 'Guardian' ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="guardian">Guardian</label>
      </div>
      <div class="form-check d-flex align-items-center">
        <input class="form-check-input me-2" type="radio" name="accomplished_by" id="rep" value="Representative"
          <?= ($draftData['accomplished_by'] ?? '') === 'Representative' ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="rep">Representative</label>
      </div>
    </div>
  </div>

  <!-- Name Fields -->
  <div class="col-md-9">
    <div class="row fw-semibold mb-1">
      <label class="col-md-4">Last Name</label>
      <label class="col-md-4">First Name</label>
      <label class="col-md-4">Middle Name</label>
    </div>

    <!-- Applicant Row -->
    <div class="row g-2 mb-2 text-center" data-group="Applicant">
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_last_name_applicant"
          value="<?= htmlspecialchars($draftData['acc_last_name_applicant'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_first_name_applicant"
          value="<?= htmlspecialchars($draftData['acc_first_name_applicant'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_middle_name_applicant"
          value="<?= htmlspecialchars($draftData['acc_middle_name_applicant'] ?? '') ?>">
      </div>
    </div>

    <!-- Guardian Row -->
    <div class="row g-2 mb-2 text-center" data-group="Guardian">
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_last_name_guardian"
          value="<?= htmlspecialchars($draftData['acc_last_name_guardian'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_first_name_guardian"
          value="<?= htmlspecialchars($draftData['acc_first_name_guardian'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_middle_name_guardian"
          value="<?= htmlspecialchars($draftData['acc_middle_name_guardian'] ?? '') ?>">
      </div>
    </div>

    <!-- Representative Row -->
    <div class="row g-2 text-center" data-group="Representative">
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_last_name_rep"
          value="<?= htmlspecialchars($draftData['acc_last_name_rep'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_first_name_rep"
          value="<?= htmlspecialchars($draftData['acc_first_name_rep'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <input type="text" class="form-control" name="acc_middle_name_rep"
          value="<?= htmlspecialchars($draftData['acc_middle_name_rep'] ?? '') ?>">
      </div>
    </div>
  </div>
</div>

      <!-- Buttons -->
      <div class="d-flex justify-content-between mt-4">
        <button type="button" class="btn btn-secondary" onclick="window.location.href='form1.php'">Back</button>
        <button type="submit" class="btn btn-primary">Next</button>
      </div>
    </form>

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
  window.currentStep = <?= (int)$currentStep ?>;
  window.maxAllowedStep = <?= (int)($_SESSION['max_step'] ?? 1) ?>;
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {
  const radios = document.querySelectorAll('input[name="accomplished_by"]');
  const rows = document.querySelectorAll('[data-group]');

  function updateRows() {
    const selected = document.querySelector('input[name="accomplished_by"]:checked')?.value;

    rows.forEach(row => {
      const group = row.getAttribute('data-group');
      const isActive = (group === selected);

      row.querySelectorAll('input').forEach(input => {
        input.disabled = !isActive;

        // ✅ clear values of non-selected groups
        if (!isActive) {
          input.value = '';
        }
      });
    });
  }

  // listen for change
  radios.forEach(radio => {
    radio.addEventListener('change', updateRows);
  });

  // run on page load
  updateRows();
});
</script>

<script>
document.querySelectorAll('.step').forEach(step => {
  step.addEventListener('click', function (e) {
    e.preventDefault();

    const targetStep = parseInt(this.dataset.step, 10);

    // Prevent skipping ahead
    if (targetStep > window.maxAllowedStep) {
      alert('Please complete the previous step first.');
      return;
    }

    window.location.href = `form${targetStep}.php`;
  });
});
</script>

  </body>
  <script>
    const form = document.querySelector('form');
    form.addEventListener('input', () => {
      const formData = Object.fromEntries(new FormData(form));
      fetch('autosave.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'formData=' + encodeURIComponent(JSON.stringify(formData)) + '&step=2'
      });
    });
  </script>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const status = document.querySelector('[name="employment_status"]');

  const category = document.getElementById('employment_category');
  const type = document.getElementById('type_of_employment');

  const categoryLabel = document.getElementById('employmentCategoryLabel');
  const typeLabel = document.getElementById('employmentTypeLabel');

  const occupationRadios = document.querySelectorAll('input[name="occupation"]');
  const occupationOther = document.querySelector('[name="occupation_others"]');
  const occupationLabel = document.getElementById('occupationLabel');

  function toggleEmploymentFields() {
    const employed = status.value !== 'Unemployed' && status.value !== '';

    /* CATEGORY & TYPE */
    category.required = employed;
    type.required = employed;

    category.disabled = !employed;
    type.disabled = !employed;

    categoryLabel.classList.toggle('required', employed);
    typeLabel.classList.toggle('required', employed);

    if (!employed) {
      category.value = '';
      type.value = '';
    }

    /* OCCUPATION */
    occupationRadios.forEach(radio => {
      radio.required = employed;
      radio.disabled = !employed;
      if (!employed) radio.checked = false;
    });

    occupationLabel.classList.toggle('required', employed);

    /* OTHERS */
    if (!employed) {
      occupationOther.value = '';
      occupationOther.disabled = true;
      occupationOther.required = false;
    }
  }

  status.addEventListener('change', toggleEmploymentFields);
  toggleEmploymentFields();

  /* OTHERS TOGGLE */
  occupationRadios.forEach(radio => {
    radio.addEventListener('change', () => {
      const isOthers = radio.value === 'Others' && radio.checked;
      occupationOther.disabled = !isOthers;
      occupationOther.required = isOthers;
      if (!isOthers) occupationOther.value = '';
    });
  });
});
</script>


<style>
  label.required::after {
    content: " *";
    color: #dc3545; /* Bootstrap danger red */
    font-weight: bold;
  }
</style>



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

</style>
  </html>