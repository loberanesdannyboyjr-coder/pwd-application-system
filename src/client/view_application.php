<?php
session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

/* ===============================
   AUTH
   =============================== */
if (empty($_SESSION['applicant_id'])) {
    header('Location: /public/login_form.php');
    exit;
}

if (empty($_GET['id']) || !ctype_digit($_GET['id'])) {
    http_response_code(400);
    exit('Invalid application.');
}

$applicantId = (int) $_SESSION['applicant_id'];
$app_id      = (int) $_GET['id']; // IMPORTANT for partial

/* ===============================
   HELPERS
   =============================== */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE);
}

function build_url_from_stored(string $stored): string {
    if ($stored === '') return '';
    if (parse_url($stored, PHP_URL_SCHEME)) return $stored;
    return rtrim(APP_BASE_URL, '/') . '/' . ltrim($stored, '/');
}

function normalize_row(array $row): array {
    $out = [];
    foreach ($row as $k => $v) {
        $k = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $k));
        $out[$k] = $v;
    }
    return $out;
}

/* ===============================
   FETCH APPLICATION (OWNERSHIP SAFE)
   =============================== */
$appRes = pg_query_params(
    $conn,
    "SELECT a.*, ap.*
     FROM application a
     JOIN applicant ap ON ap.applicant_id = a.applicant_id
     WHERE a.application_id = $1
       AND a.applicant_id   = $2
     LIMIT 1",
    [$app_id, $applicantId]
);

$application = pg_fetch_assoc($appRes);
if (!$application) {
    http_response_code(404);
    exit('Application not found.');
}

/* ===============================
   DOCUMENT REQUIREMENTS
   =============================== */
$docs = [];
$docsRes = pg_query_params(
    $conn,
    "SELECT * FROM documentrequirements WHERE application_id = $1 LIMIT 1",
    [$app_id]
);
if ($docsRes && pg_num_rows($docsRes) > 0) {
    $docs = pg_fetch_assoc($docsRes);
}

/* ===============================
   APPLICATION DRAFT (JSON MERGE)
   =============================== */
$draft_json = [];
$draftRes = pg_query_params(
    $conn,
    "SELECT data FROM application_draft
     WHERE application_id = $1
     ORDER BY step ASC, updated_at ASC",
    [$app_id]
);

while ($r = pg_fetch_assoc($draftRes)) {
    if (!empty($r['data'])) {
        $decoded = json_decode($r['data'], true);
        if (is_array($decoded)) {
            $draft_json = array_merge($draft_json, $decoded);
        }
    }
}

/* ===============================
   MERGE DATA (ADMIN-COMPATIBLE)
   =============================== */
$draftData = array_merge(
    normalize_row($application),
    normalize_row($docs),
    normalize_row($draft_json)
);

/* ===============================
   DISABILITY LABEL
   =============================== */
$labels = [];
$disRes = pg_query_params(
    $conn,
    "SELECT disability_type FROM disability WHERE application_id = $1",
    [$app_id]
);
if ($disRes) {
    while ($d = pg_fetch_assoc($disRes)) {
        if (!empty($d['disability_type'])) $labels[] = $d['disability_type'];
    }
}
$draftData['disability_label'] = implode(', ', $labels);

/* ===============================
   PHOTO (ADMIN-COMPATIBLE LOGIC)
   =============================== */
$pic_candidate = '';

if (!empty($docs['pic_1x1_path'])) {
    $pic_candidate = $docs['pic_1x1_path'];
} elseif (!empty($application['pic_1x1_path'])) {
    $pic_candidate = $application['pic_1x1_path'];
} elseif (!empty($draftData['pic_1x1_path'])) {
    $pic_candidate = $draftData['pic_1x1_path'];
}

$draftData['pic_url'] = $pic_candidate
    ? build_url_from_stored($pic_candidate)
    : '';

/* ===============================
   FILES (ADMIN-COMPATIBLE)
   =============================== */
$fileMap = [
    'bodypic_path'           => 'Whole Body Picture',
    'barangaycert_path'     => 'Barangay Certificate',
    'medicalcert_path'      => 'Medical Certificate',
    'proof_disability_path' => 'Proof of Disability',
    'old_pwd_id_path'       => 'Old PWD ID',
    'affidavit_loss_path'   => 'Affidavit of Loss',
    'cho_cert_path'         => 'CHO Certificate'
];


foreach ($fileMap as $col => $label) {
    if (!empty($docs[$col])) {
        $stored = $docs[$col];
        $draftData['files'][] = [
            'label' => $label,
            'path'  => $stored,
            'url'   => build_url_from_stored($stored)
        ];
    }
}


/* ===============================
   FILE VIEW / DOWNLOAD PROXY
   =============================== */
if (!empty($_GET['file_action']) && in_array($_GET['file_action'], ['view','download'], true)) {
    $requested = basename($_GET['file'] ?? '');
    foreach ($draftData['files'] as $f) {
        if (basename($f['path']) === $requested) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($f['path'], '/');
            if (is_file($filePath)) {
                header('Content-Type: ' . mime_content_type($filePath));
                header(
                    'Content-Disposition: ' .
                    ($_GET['file_action'] === 'view' ? 'inline' : 'attachment') .
                    '; filename="' . basename($filePath) . '"'
                );
                readfile($filePath);
                exit;
            }
        }
    }
    http_response_code(404);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Application</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body style="background:#f6f7f9">

<main
  class="p-4"
  style="margin-left: 4rem; padding-top: 1.5rem;"
>

<style>
  @media (min-width: 768px) {
    main {
      margin-left: 16rem; /* matches expanded sidebar */
    }
  }
</style>

<div class="container-lg">

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Application Details</h4>
    <small class="text-muted">
      Review of your submitted PWD application
    </small>
  </div>
</div>

<a href="my_applications.php"
   class="text-decoration-none text-primary fw-semibold d-inline-flex align-items-center mb-3">
  <i class="bi bi-arrow-left me-2"></i>
  Back to My Applications
</a>


<div class="card shadow-sm">
  <div class="card-header text-white"
       style="background:linear-gradient(90deg,#2d6be6,#5b9df7)">
    Application Details
  </div>

  <div class="card-body">
    <?php
      $partial = __DIR__ . '/../admin_side/partials/form5_readonly.php';
      include $partial;
    ?>
  </div>
</div>

</div>
</main>

</body>
</html>
