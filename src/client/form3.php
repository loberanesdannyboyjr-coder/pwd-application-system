<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';

// ✅ Check session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['applicant_id']) || !isset($_SESSION['application_id'])) {
    header("Location: ../../public/login_form.php");
    exit;
}

$applicant_id   = (int)$_SESSION['applicant_id'];
$application_id = (int)$_SESSION['application_id'];

// ✅ Resolve application type (url -> post -> session)
$type = strtolower($_GET['type'] ?? $_POST['type'] ?? ($_SESSION['application_type'] ?? 'new'));
if ($type === 'renewal') $type = 'renew';
if (!in_array($type, ['new','renew','lost'], true)) $type = 'new';
$_SESSION['application_type'] = $type;

// ✅ Load draft data using application_id
$step = 3;
$draftData = loadDraftData($step, $application_id);

$step = 3;
$draftData = loadDraftData($step, $application_id);

// 🔥 ADD THIS BLOCK HERE
if ($type !== 'new') {

    $res = pg_query_params(
        $conn,
        "SELECT ad.data
         FROM application a
         JOIN application_draft ad 
           ON a.application_id = ad.application_id
         WHERE a.applicant_id = $1
           AND a.workflow_status = 'pdao_approved'
           AND ad.step = 3
         ORDER BY a.created_at DESC
         LIMIT 1",
        [$applicant_id]
    );

    if ($res && pg_num_rows($res) > 0) {
        $row = pg_fetch_assoc($res);

        $approvedData = json_decode($row['data'], true);

        // 🔥 merge approved + current draft
        $draftData = array_merge($approvedData, $draftData ?? []);
    }
}

// 🔒 LOCK FORM IF ALREADY SUBMITTED
if (($draftData['workflow_status'] ?? 'draft') !== 'draft') {
    http_response_code(403);
    exit('Application already submitted. Editing is disabled.');
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    saveDraftData($step, $_POST, $application_id);

    if (($_POST['nav'] ?? '') === 'back') {
        header("Location: form2.php?type=" . urlencode($type));
        exit;
    }
    header("Location: form4.php?type=" . urlencode($type));
    exit;
}

$currentStep = 3;

// Initialize max_step if not set
$_SESSION['max_step'] = $_SESSION['max_step'] ?? 1;

// Update max_step (never go backwards)
if ($_SESSION['max_step'] < $currentStep) {
    $_SESSION['max_step'] = $currentStep;
}

$isLocked = ($type === 'renew' || $type === 'lost');

$editableFields = [
    'contact_person_name',
    'contact_person_no'
];

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

  <div class="form-container">
    <form method="POST">
      <!-- CHO-Filled Certification Section -->
      <div class="mb-3 border-start border-4 border-secondary bg-light rounded p-2 ps-3 fw-semibold text-secondary">
        TO BE FILLED OUT BY THE CITY HEALTH OFFICE (CHO)
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Name of Certifying Physician:</label>
          <input type="text" class="form-control bg-light" name="certifying_physician"
            value="<?= htmlspecialchars($draftData['certifying_physician'] ?? '') ?>" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">License No.:</label>
          <input type="text" class="form-control bg-light" name="license_no"
            value="<?= htmlspecialchars($draftData['license_no'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Processing Officer:</label>
          <input type="text" class="form-control bg-light" name="processing_officer"
            value="<?= htmlspecialchars($draftData['processing_officer'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Approving Officer:</label>
          <input type="text" class="form-control bg-light" name="approving_officer"
            value="<?= htmlspecialchars($draftData['approving_officer'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Encoder:</label>
          <input type="text" class="form-control bg-light" name="encoder"
            value="<?= htmlspecialchars($draftData['encoder'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Reporting Unit (Office/Section):</label>
          <input type="text" class="form-control bg-light" name="reporting_unit"
            value="<?= htmlspecialchars($draftData['reporting_unit'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Control No.:</label>
          <input type="text" class="form-control bg-light" name="control_no"
            value="<?= htmlspecialchars($draftData['control_no'] ?? '') ?>" readonly>
        </div>
      </div>

    <!-- Emergency Contact Section -->
    <div class="mb-3 border-start border-4 border-primary bg-light rounded p-2 ps-3 fw-semibold text-primary">
      IN CASE OF EMERGENCY
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label fw-semibold required">
          Contact Person’s Name
        </label>
        <input
            type="text"
            class="form-control"
            name="contact_person_name"
            required
            value="<?= htmlspecialchars($draftData['contact_person_name'] ?? '') ?>"
            <?= (!in_array('contact_person_name', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
      </div>

    <div class="col-md-6">
      <label class="form-label fw-semibold required">
        Contact Person’s No.
      </label>
      <input
        type="tel"
        class="form-control"
        name="contact_person_no"
        required
        inputmode="numeric"
        pattern="[0-9]{11}"
        maxlength="11"
        placeholder="09XXXXXXXXX"
        title="Please enter an 11-digit mobile number"
        value="<?= htmlspecialchars($draftData['contact_person_no'] ?? '') ?>"
        <?= (!in_array('contact_person_no', $editableFields) && $isLocked) ? 'readonly' : '' ?>>
          </div>
    </div>


      <!-- Navigation Buttons -->
      <div class="d-flex justify-content-between">
  <button type="submit" name="nav" value="back" class="btn btn-outline-primary">Back</button>
  <button type="submit" name="nav" value="next" class="btn btn-primary px-4">Next</button>
</div>

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

    // ✅ Navigate
    window.location.href = `form${targetStep}.php?type=<?= htmlspecialchars($_SESSION['application_type']) ?>`;
  });
});
</script>


<style>

.required::after {
  content: " *";
  color: #dc3545;
  font-weight: bold;
}
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





    </form>
  </div>
</body>
</html>
