<?php
// src/admin_side/partials/form5_readonly.php
// Read-only applicant summary partial. Expects $draftData (array) provided by parent view.
// IMPORTANT: Do NOT declare h() here; view_a.php should provide it.

if (!isset($draftData) || !is_array($draftData)) {
    echo '<div class="alert alert-warning">Missing $draftData for partial.</div>';
    return;
}

/* --- small helpers local to the partial (no collisions) --- */
function _e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

$prettyName = function(string $p){
    $path = parse_url($p, PHP_URL_PATH) ?: $p;
    return basename($path);
};

$viewTarget = function(string $p){
    $ext = strtolower(pathinfo(parse_url($p, PHP_URL_PATH) ?: $p, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp','pdf']) ? '_blank' : '_self';
};

// gp - grab first present key from $draftData
function gp($keys, $default = '') {
    global $draftData;
    foreach ((array)$keys as $k) {
        // check normalized forms too
        $k = (string)$k;
        if (array_key_exists($k, $draftData) && $draftData[$k] !== null && $draftData[$k] !== '') return $draftData[$k];
        $k_low = strtolower($k);
        if (array_key_exists($k_low, $draftData) && $draftData[$k_low] !== null && $draftData[$k_low] !== '') return $draftData[$k_low];
        // also try snake-cased
        $k_snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $k);
        $k_snake = preg_replace('/[^a-z0-9_]+/', '_', strtolower($k_snake));
        if (array_key_exists($k_snake, $draftData) && $draftData[$k_snake] !== null && $draftData[$k_snake] !== '') return $draftData[$k_snake];
    }
    return $default;
}

/* ---------- constants / UI ---------- */
$occupationChoices = [
  'Managers','Professionals','Technicians and Associate Professionals',
  'Clerical Support Workers','Service and Sales Workers',
  'Skilled Agricultural, Forestry and Fishery Workers',
  'Craft and Related Trade Workers','Plant and Machinery Operators and Assemblers',
  'Elementary Occupations','Armed Forces Occupations','Others'
];

$applicationType = gp(['application_type','type'],'N/A');
$pwdNumber = gp(['pwd_number','pwd_no'],'To be filled by PDAO');
$applicationDate = gp(['application_date'], '');

if (!empty($applicationDate)) {
    $applicationDate = date('Y-m-d', strtotime($applicationDate));
}

/* ---------- helper to produce a web URL for a stored path if needed ---------- */
function make_web_url($stored) {
    if (empty($stored)) return '';
    $scheme = parse_url($stored, PHP_URL_SCHEME);
    if ($scheme === 'http' || $scheme === 'https') return $stored;
    if (substr($stored, 0, 1) === '/') {
        return (defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . $stored;
    }
    return (defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . '/' . ltrim($stored, '/');
}

/* Figure out the view script to call the proxy on.
   Use the current script name (should be view_a.php when included from the parent).
   This is robust because the page URL in the browser will be the parent script.
*/
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? 'view_a.php'); // usually 'view_a.php'
$appId = isset($app_id) ? $app_id : (isset($_GET['id']) ? $_GET['id'] : ''); // prefer parent $app_id

?>
<div class="form-summary">
  <h4 class="mb-3">Application Summary</h4>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label fw-semibold">Applicant Type</label>
      <div class="form-control bg-light"><?= _e($applicationType) ?></div>
    </div>

    <div class="col-md-4">
      <label class="form-label fw-semibold">PWD Number</label>
      <div class="form-control bg-light"><?= _e($pwdNumber) ?></div>
    </div>

    <div class="col-md-3">
      <label class="form-label fw-semibold">Date Applied</label>
      <div class="form-control bg-light"><?= _e($applicationDate) ?></div>
    </div>

    <div class="col-md-2 text-center">
      <label class="form-label fw-semibold">Photo</label><br>
<?php if (!empty($draftData['pic_url'])): 
    $pic_href = make_web_url($draftData['pic_url']);
    $pic_basename = basename(parse_url($pic_href, PHP_URL_PATH) ?: $draftData['pic_url']);
?>
  <a href="<?= _e($pic_href) ?>" target="_blank" title="<?= _e($pic_basename) ?>">
    <img src="<?= _e($pic_href) ?>" class="img-thumbnail" style="max-width:120px;max-height:120px;object-fit:cover;">
  </a>
<?php else: ?>
  <div class="text-muted small">Not uploaded</div>
<?php endif; ?>

    </div>
  </div>

  <!-- Name row -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label fw-semibold">Last Name</label>
      <div class="form-control bg-light"><?= _e(gp(['last_name','lname','family_name'],'N/A')) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">First Name</label>
      <div class="form-control bg-light"><?= _e(gp(['first_name','fname','given_name'],'N/A')) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Middle Name</label>
      <div class="form-control bg-light"><?= _e(gp(['middle_name','mname','middlename'],'')) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Suffix</label>
      <div class="form-control bg-light"><?= _e(gp(['suffix'],'')) ?></div>
    </div>
  </div>

  <!-- Personal -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label">Date of Birth</label>
      <div class="form-control bg-light"><?= _e(gp(['birthdate','dob','date_of_birth'],'') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Sex</label>
      <div class="form-control bg-light"><?= _e(gp(['sex','gender'],'') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Civil Status</label>
      <div class="form-control bg-light"><?= _e(gp(['civil_status','marital_status'],'') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Type of Disability</label>
      <div class="form-control bg-light"><?= _e(gp(['disability_label','disability_type','disability'],'') ) ?></div>
    </div>
  </div>

  <!-- Cause + Address -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label text-primary fw-semibold">Cause of Disability</label>
      <div class="mb-2">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" disabled <?= (strtolower(gp(['cause'],'') ) === 'congenital/inborn' || strtolower(gp(['cause'],'') ) === 'congenital') ? 'checked' : ''?>>
          <label class="form-check-label">Congenital/Inborn</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" disabled <?= (strtolower(gp(['cause'],'') ) === 'acquired') ? 'checked' : ''?>>
          <label class="form-check-label">Acquired</label>
        </div>
      </div>
      <div class="form-control bg-light"><?= _e(gp(['cause_description','cause_detail','cause_of_disability'],'') ) ?></div>
    </div>

    <div class="col-md-3">
      <label class="form-label">House No. and Street</label>
      <div class="form-control bg-light"><?= _e(gp(['house_no_street','house_no','address'],'') ) ?></div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Barangay</label>
      <div class="form-control bg-light"><?= _e(gp(['barangay'],'') ) ?></div>
    </div>

    <div class="col-md-3">
      <label class="form-label">Municipality</label>
      <div class="form-control bg-light"><?= _e(gp(['municipality','city'],'') ) ?></div>
    </div>
  </div>

  <!-- Contact & Region -->
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <label class="form-label">Province</label>
      <div class="form-control bg-light"><?= _e(gp(['province'],'N/A') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Region</label>
      <div class="form-control bg-light"><?= _e(gp(['region'],'N/A') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Landline No.</label>
      <div class="form-control bg-light"><?= _e(gp(['landline_no','phone','telephone'],'N/A') ) ?></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Mobile No.</label>
      <div class="form-control bg-light"><?= _e(gp(['mobile_no','mobile','contact_no','contact'],'N/A') ) ?></div>
    </div>
  </div>

  <div class="mb-3">
    <label class="form-label">E-mail Address:</label>
    <div class="form-control bg-light"><?= _e(gp(['email_address','email'],'N/A') ) ?></div>
  </div>

  <!-- occupation & education -->
  <div class="row g-2 align-items-start mb-3">
    <div class="col-md-4">
      <div class="mb-2">
        <label class="form-label fw-semibold">Educational Attainment</label>
        <div class="form-control bg-light"><?= _e(gp(['educational_attainment','education'],'N/A')) ?></div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-semibold">Status of Employment</label>
        <div class="form-control bg-light"><?= _e(gp(['employment_status'],'N/A')) ?></div>
      </div>
      <div class="mb-2">
        <label class="form-label fw-semibold">Category of Employment</label>
        <div class="form-control bg-light"><?= _e(gp(['employment_category'],'N/A')) ?></div>
      </div>
      <div class="mb-0">
        <label class="form-label fw-semibold">Type of Employment</label>
        <div class="form-control bg-light"><?= _e(gp(['type_of_employment'],'N/A')) ?></div>
      </div>
    </div>

    <div class="col-md-8">
      <label class="form-label fw-semibold mb-2">Occupation</label>
      <div class="row">
        <div class="col-md-6">
          <?php foreach(array_slice($occupationChoices,0,6) as $occ): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" disabled <?= (gp(['occupation'],'') === $occ ? 'checked' : '') ?>>
              <label class="form-check-label ms-1"><?= _e($occ) ?></label>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="col-md-6">
          <?php foreach(array_slice($occupationChoices,6,5) as $occ): ?>
            <div class="form-check">
              <input class="form-check-input" type="radio" disabled <?= (gp(['occupation'],'') === $occ ? 'checked' : '') ?>>
              <label class="form-check-label ms-1"><?= _e($occ) ?></label>
            </div>
          <?php endforeach; ?>

          <div class="form-check d-flex align-items-center mt-2">
            <input class="form-check-input me-2" type="radio" disabled <?= (gp(['occupation'],'') === 'Others' ? 'checked' : '') ?>>
            <label class="form-check-label me-2">Others, specify:</label>
            <input type="text" class="form-control form-control-sm bg-light" style="width:180px" readonly value="<?= _e(gp(['occupation_other'],'N/A')) ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- organization / ids -->
  <div class="row g-2 mb-3">
    <div class="col-md-3"><label class="form-label fw-semibold text-primary mb-1">Organization Information</label><div class="form-control bg-light"><?= _e(gp(['organization_affiliated'],'')) ?></div></div>
    <div class="col-md-3"><label class="form-label">Contact Person</label><div class="form-control bg-light"><?= _e(gp(['contact_person','contact_person_name','contactperson'],'') ) ?></div></div>
    <div class="col-md-3"><label class="form-label">Office Address</label><div class="form-control bg-light"><?= _e(gp(['office_address'],'') ) ?></div></div>
    <div class="col-md-3"><label class="form-label">Tel No.</label><div class="form-control bg-light"><?= _e(gp(['tel_no'],'') ) ?></div></div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label fw-semibold">SSS No.</label><div class="form-control bg-light"><?= _e(gp(['sss_no'],'')) ?></div></div>
    <div class="col-md-3"><label class="form-label fw-semibold">GSIS No.</label><div class="form-control bg-light"><?= _e(gp(['gsis_no'],'')) ?></div></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Pag-IBIG No.</label><div class="form-control bg-light"><?= _e(gp(['pagibig_no'],'')) ?></div></div>
    <div class="col-md-3"><label class="form-label fw-semibold">PhilHealth No.</label><div class="form-control bg-light"><?= _e(gp(['philhealth_no'],'')) ?></div></div>
  </div>

  <!-- family / accomplished by -->

<!-- ================= FAMILY BACKGROUND ================= -->

<h5 class="mt-4">Family Background</h5>

<div class="row g-3 mb-2">

  <div class="col-md-2">
    <label class="form-label">Father's Name:</label>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['father_last_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['father_first_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['father_middle_name'])) ?>"
           readonly>
  </div>

</div>


<div class="row g-3 mb-2">

  <div class="col-md-2">
    <label class="form-label">Mother's Name:</label>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['mother_last_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['mother_first_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['mother_middle_name'])) ?>"
           readonly>
  </div>

</div>


<div class="row g-3 mb-3">

  <div class="col-md-2">
    <label class="form-label">Guardian's Name:</label>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_last_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_first_name'])) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_middle_name'])) ?>"
           readonly>
  </div>

</div>

<!-- ================= ACCOMPLISHED BY ================= -->

<h5 class="mt-3">Accomplished By</h5>

<div class="row mb-2">

  <div class="col-md-3">

    <?php $acc = strtolower(gp(['accomplished_by','accomplishedby'],'')); ?>

    <div class="form-check">
      <input class="form-check-input" type="radio" disabled <?= ($acc === 'applicant') ? 'checked' : '' ?>>
      <label class="form-check-label">Applicant</label>
    </div>

    <div class="form-check">
      <input class="form-check-input" type="radio" disabled <?= ($acc === 'guardian') ? 'checked' : '' ?>>
      <label class="form-check-label">Guardian</label>
    </div>

    <div class="form-check">
      <input class="form-check-input" type="radio" disabled <?= ($acc === 'representative') ? 'checked' : '' ?>>
      <label class="form-check-label">Representative</label>
    </div>

  </div>

  <div class="col-md-3">
    <label class="form-label">Last Name</label>
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_last_name','representative_last_name'],'')) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <label class="form-label">First Name</label>
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_first_name','representative_first_name'],'')) ?>"
           readonly>
  </div>

  <div class="col-md-3">
    <label class="form-label">Middle Name</label>
    <input class="form-control bg-light"
           value="<?= _e(gp(['guardian_middle_name','representative_middle_name'],'')) ?>"
           readonly>
  </div>

</div>
</section>

<div class="section-title">
    IN CASE OF EMERGENCY
</div>

  <div class="row g-3 mb-4">
    <div class="col-md-6"><label class="form-label">Contact Person’s Name:</label><div class="form-control bg-light"><?= _e(gp(['contact_person_name','contact_person','contactperson'],'') ) ?></div></div>
    <div class="col-md-6"><label class="form-label">Contact Person’s No.:</label><div class="form-control bg-light"><?= _e(gp(['contact_person_no','contactperson_no','emergency_contact_no'],'') ) ?></div></div>
  </div>

  <!-- FILES -->
<div class="section-title">
    FILES
</div>
  <?php if (!empty($draftData['files']) && is_array($draftData['files'])): ?>
    <ul class="files-list list-unstyled mb-4">
      <?php foreach ($draftData['files'] as $f):
          $path = $f['path'] ?? '';
          if (empty($path)) continue;
          $label = $f['label'] ?? ($f['basename'] ?? $prettyName($path));
          // basename to send to proxy
          $basename = $f['basename'] ?? basename(parse_url($path, PHP_URL_PATH) ?: $path);
          // create proxy URLs pointing to the parent script (view_a.php)
          $viewUrl = $scriptName . '?id=' . urlencode($appId) . '&file_action=view&file=' . urlencode($basename);
          $dlUrl   = $scriptName . '?id=' . urlencode($appId) . '&file_action=download&file=' . urlencode($basename);
          // target: prefer new tab for images/pdf
          $target = $viewTarget($path);
      ?>
        <li class="d-flex align-items-start py-2">
          <div class="meta">
            <div class="fw-semibold"><?= _e($label) ?></div>
            <div class="text-muted small"><?= _e($basename) ?></div>
          </div>

          <div class="actions ms-auto">
            <a href="<?= _e($viewUrl) ?>" target="<?= _e($target) ?>" class="small">View</a>
            <a href="<?= _e($dlUrl) ?>" class="small ms-2">Download</a>

          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <div class="text-muted mb-4">No files uploaded.</div>
  <?php endif; ?>

</div>

<style>
  .form-summary .form-control.bg-light{ background:#f8f9fa; border:1px solid #e1e5ea; color:#212529; }
  .form-check-input[disabled]{ pointer-events:none; opacity:1; }
  .files-list li{ padding:.45rem .65rem; border-bottom:1px solid #eef0f3; display:flex; gap:1rem; align-items:flex-start; }
  .files-list .actions a{ color:#0d6efd; text-decoration:none; }
  .files-list .actions a:hover{ text-decoration:underline; }
  .section-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: #2563eb;
    text-transform: uppercase;

    background: #f1f5f9;
    border-left: 4px solid #2563eb;

    padding: 10px 14px;
    border-radius: 6px;

    margin-top: 24px;
    margin-bottom: 14px;
}
</style>
