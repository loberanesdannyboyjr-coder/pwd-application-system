<?php
// src/admin_side/view_a.php
// Fixed version: merges application_draft JSON data + applicant + application + documentrequirements
// to produce a single $draftData consumed by partials/form5_readonly.php
// Also includes a small file serving proxy so "View" / "Download" buttons work.

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';
session_start();

// ensure session already started
if (session_status() === PHP_SESSION_NONE) session_start();

// create a CSRF token once per session (safe to call repeatedly)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// AUTH
if (empty($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: ' . rtrim(APP_BASE_URL, '/') . '/src/admin_side/signin.php');
    exit;
}

if (empty($_GET['id'])) { http_response_code(400); echo 'Missing application id.'; exit; }
$app_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if ($app_id === false) { http_response_code(400); echo 'Invalid id.'; exit; }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE); }

function build_url_from_stored(string $stored): string {
    $stored = trim($stored);
    if ($stored === '') return '';
    if (parse_url($stored, PHP_URL_SCHEME)) return $stored;
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '';
    return $base . '/' . ltrim($stored, '/');
}

function server_path_candidates(string $storedPath): array {
    $out = [];
    if ($storedPath === '') return $out;
    if (DIRECTORY_SEPARATOR === '\\') {
        if (preg_match('#^[A-Z]:\\\\#i', $storedPath)) $out[] = $storedPath;
    } else {
        if (strpos($storedPath, '/') === 0) $out[] = $storedPath;
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $out[] = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR);
    }
    $projectRoot = realpath(__DIR__ . '/../../..');
    if ($projectRoot) {
        $out[] = $projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR);
        $out[] = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($storedPath);
    }
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $out[] = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($storedPath);
    }
    $cwd = getcwd();
    if ($cwd) $out[] = $cwd . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($storedPath);
    $seen = []; $uniq = [];
    foreach ($out as $p) {
        if (!$p) continue;
        $normalized = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $p);
        if (isset($seen[$normalized])) continue;
        $seen[$normalized] = true;
        $uniq[] = $normalized;
    }
    return $uniq;
}

function find_first_existing(array $candidates) {
    foreach ($candidates as $p) {
        if (@file_exists($p)) return $p;
    }
    return null;
}

/* ---------------- Fetch application + applicant (LEFT JOIN) ------------------ */
$sql = "SELECT a.*, ap.* FROM application a LEFT JOIN applicant ap ON a.applicant_id = ap.applicant_id WHERE a.application_id = $1 LIMIT 1";
$res = @pg_query_params($conn, $sql, [$app_id]);
if ($res === false) {
    error_log('view_a query error: '.pg_last_error($conn));
    http_response_code(500); echo 'An internal error occurred.'; exit;
}
$row = pg_fetch_assoc($res);
if (!$row) { http_response_code(404); echo 'Application not found.'; exit; }
$application = $row;

/* ---------------- documentrequirements ------------------ */
$docs = null;
$docs_sql = "SELECT * FROM documentrequirements WHERE application_id = $1 LIMIT 1";
$docs_res = @pg_query_params($conn, $docs_sql, [$app_id]);
if ($docs_res && pg_num_rows($docs_res) > 0) $docs = pg_fetch_assoc($docs_res);

/* ---------------- application_draft rows (merge JSON data) ------------------ */
$draft_json_merged = [];
$draft_q = "SELECT data, step FROM application_draft WHERE application_id = $1 ORDER BY step ASC, updated_at ASC";
$draft_res = @pg_query_params($conn, $draft_q, [$app_id]);
if ($draft_res && pg_num_rows($draft_res) > 0) {
    while ($r = pg_fetch_assoc($draft_res)) {
        if (!empty($r['data'])) {
            $decoded = json_decode($r['data'], true);
            if (is_array($decoded)) {
                $draft_json_merged = array_merge($draft_json_merged, $decoded);
            }
        }
    }
}

/* ---------------- normalize helper ------------------ */
function normalize_row(array $row): array {
    $out = [];
    foreach ($row as $k => $v) {
        $low = strtolower((string)$k);
        $snake = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $low);
        $snake = preg_replace('/[^a-z0-9_]+/', '_', $snake);
        $snake = trim($snake, '_');
        $out[$snake] = $v;
        $out[$low] = $v;
    }
    return $out;
}

function workflow_label(string $status): string {
    switch (strtolower($status)) {
        case 'draft':        return 'DRAFT';
        case 'submitted':    return 'SUBMITTED';
        case 'pdao_review':  return 'PDAO REVIEW';
        case 'cho_review':   return 'CHO REVIEW';
        case 'approved':     return 'ACCEPTED';
        case 'verified':     return 'ACCEPTED';
        case 'rejected':
        case 'pdao_rejected':return 'FEEDBACK';
        default:             return strtoupper($status);
    }
}


/* ---------------- build $draftData (priority: drafts -> applicant -> application -> docs) ------------------ */
$normalizedDraft = [];
foreach ($draft_json_merged as $k => $v) {
    $k2 = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$k));
    $normalizedDraft[$k2] = $v;
}

$normalizedApp = normalize_row($application ?: []);
$normalizedDocs = normalize_row($docs ?: []);

$draftData = array_merge($normalizedApp, $normalizedDocs, $normalizedDraft);

/* ===============================
   PDAO VIEW — REMOVE MEDICAL DATA
   =============================== */

$medicalKeys = [
    // diagnosis / medical info
    'diagnosis',
    'medical_condition',
    'medical_findings',
    'cause_of_disability',
    'impairment_type',

    // CHO assessment fields
    'cho_remarks',
    'cho_findings',
    'cho_assessment',
    'cho_recommendation',
    'cho_physician',
    'cho_license_no',

    // medical certificate metadata
    'medical_certificate_no',
    'medical_certificate_date',
    'hospital_name',
    'physician_name'
];

foreach ($medicalKeys as $key) {
    unset($draftData[$key]);
}


// compatibility aliases
if (empty($draftData['application_type']) && !empty($application['application_type'])) $draftData['application_type'] = $application['application_type'];
if (empty($draftData['pwd_number']) && !empty($application['pwd_number'])) $draftData['pwd_number'] = $application['pwd_number'];
if (empty($draftData['application_date'])) $draftData['application_date'] = $application['application_date'] ?? $application['created_at'] ?? '';

$autoMap = [
    'first_name' => ['first_name','first','fname','given_name','givenname'],
    'last_name'  => ['last_name','last','lname','family_name','familyname'],
    'middle_name'=> ['middle_name','middle','mname','middlename'],
    'birthdate'  => ['birthdate','dob','date_of_birth','dateofbirth'],
    'sex'        => ['sex','gender'],
    'civil_status'=> ['civil_status','marital_status','marital'],
    'house_no_street' => ['house_no_street','house_no','address','street'],
    'barangay'   => ['barangay','brgy'],
    'municipality'=> ['municipality','city','town'],
    'region'     => ['region'],
    'province'   => ['province'],
    'landline_no'=> ['landline_no','telephone','tel','phone'],
    'mobile_no'  => ['mobile_no','mobile','contact_no','contact'],
    'email_address'=> ['email_address','email','e_mail'],
    'educational_attainment'=> ['educational_attainment','education'],
    'occupation' => ['occupation','job','work'],
    'employment_status' => ['employment_status','employment_status'],
    'sss_no'     => ['sss_no','sss'],
    'gsis_no'    => ['gsis_no','gsis'],
    'pagibig_no' => ['pagibig_no','pagibig'],
    'philhealth_no'=> ['philhealth_no','philhealth'],
    'contact_person_name' => ['contact_person_name','contact_person','contactperson'],
    'contact_person_no' => ['contact_person_no','contactperson_no','emergency_contact_no','emergency_contact']
];

foreach ($autoMap as $target => $variants) {
    if (!isset($draftData[$target]) || $draftData[$target] === '' || $draftData[$target] === null) {
        foreach ($variants as $v) {
            if (isset($normalizedDraft[$v]) && $normalizedDraft[$v] !== '') { $draftData[$target] = $normalizedDraft[$v]; break; }
            if (isset($normalizedApp[$v]) && $normalizedApp[$v] !== '') { $draftData[$target] = $normalizedApp[$v]; break; }
            if (isset($normalizedDocs[$v]) && $normalizedDocs[$v] !== '') { $draftData[$target] = $normalizedDocs[$v]; break; }
        }
    }
}

/* ---------- build draftData['files'] but EXCLUDE the 1x1 pic from the files list ---------- */
$draftData['files'] = [];
$map_docs = [
    'bodypic_path'       => 'Whole Body Picture',
    'barangaycert_path' => 'Barangay Certificate',
    'old_pwd_id_path'   => 'Old PWD ID',
    'affidavit_loss_path' => 'Affidavit of Loss'
    // medicalcert_path REMOVED for PDAO
    // cho_cert_path REMOVED for PDAO
];

if ($docs) {
    foreach ($map_docs as $col => $label) {
        if (!empty($docs[$col])) {
            $stored = $docs[$col];
            $draftData['files'][] = [
                'label' => $label,
                'path'  => $stored,
                'url'   => build_url_from_stored($stored),
                'server_candidates' => server_path_candidates($stored),
                'server_found' => find_first_existing(server_path_candidates($stored))
            ];
        }
    }
}
$fileCandidates = [
    'bodypic_path',
    'bodypic',
    'file_path',
    'file',
    'attachment_path',
    'attachment',
    'document_path',
    'barangaycert_path',
    'old_pwd_id_path',
    'affidavit_loss_path'
    // medicalcert_path REMOVED for PDAO
];
foreach ($fileCandidates as $c) {
    if (!empty($draftData[$c])) {
        $stored = $draftData[$c];
        $basename = basename(parse_url($stored, PHP_URL_PATH) ?: $stored);

        $exists = false;
        foreach ($draftData['files'] as $f) {
            if (basename($f['path']) === $basename) {
                $exists = true;
                break;
            }
        }
        if ($exists) continue;

        $draftData['files'][] = [
            'label' => ucfirst(str_replace('_',' ', $c)),
            'path'  => $stored,
            'url'   => build_url_from_stored($stored),
            'server_candidates' => server_path_candidates($stored),
            'server_found' => find_first_existing(server_path_candidates($stored))
        ];
    }
}

$draftData['files'] = array_filter(
    $draftData['files'],
    fn($f) => stripos($f['label'], 'medical') === false
);


/* ---------- determine header pic (prefers docs.pic_1x1_path then application.pic_1x1_path or draft json) ---------- */
$pic_candidate = '';
if (!empty($normalizedDocs['pic_1x1_path'])) {
    $pic_candidate = $normalizedDocs['pic_1x1_path'];
} elseif (!empty($normalizedApp['pic_1x1_path'])) {
    $pic_candidate = $normalizedApp['pic_1x1_path'];
} elseif (!empty($normalizedDraft['pic_1x1_path'])) {
    $pic_candidate = $normalizedDraft['pic_1x1_path'];
} elseif (!empty($draftData['pic_1x1_path'])) {
    $pic_candidate = $draftData['pic_1x1_path'];
}
$draftData['pic_url'] = $pic_candidate ? build_url_from_stored($pic_candidate) : '';
$draftData['pic_server_candidates'] = $pic_candidate ? server_path_candidates($pic_candidate) : [];
$draftData['pic_server_found'] = $pic_candidate ? find_first_existing(server_path_candidates($pic_candidate)) : null;

/* ---------- disability label ---------- */
$labelParts = [];
$dis_q = "SELECT * FROM disability WHERE application_id = $1";
$dis_res = @pg_query_params($conn, $dis_q, [$app_id]);
if ($dis_res && pg_num_rows($dis_res) > 0) {
    while ($d = pg_fetch_assoc($dis_res)) {
        $lab = $d['disability_type'] ?? $d['disability'] ?? '';
        if ($lab) $labelParts[] = $lab;
    }
}
$draftData['disability_label'] = !empty($labelParts) ? implode(', ', $labelParts) : ($draftData['disability_label'] ?? '');

/* ---------- Built-in file serving handler (view/download) ---------- */
/* Usage:
   view:   view_a.php?id=...&file_action=view&file=basename.ext
   download: view_a.php?id=...&file_action=download&file=basename.ext
*/
if (!empty($_GET['file_action']) && in_array($_GET['file_action'], ['view','download'], true) && !empty($_GET['file'])) {
    $action = $_GET['file_action'];
    // sanitize: only basename allowed
    $requested = basename($_GET['file']);
    $match = null;
    foreach ($draftData['files'] as $f) {
        $fbasename = basename(parse_url($f['path'], PHP_URL_PATH) ?: $f['path']);
        if ($fbasename === $requested) { $match = $f; break; }
    }
    if (!$match) {
        http_response_code(404); echo 'File not found.'; exit;
    }

    // If there's a server file found, serve that; otherwise if it's an external URL redirect
    if (!empty($match['server_found']) && @file_exists($match['server_found'])) {
        $filePath = $match['server_found'];
        // Determine mime type
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($filePath);
            if ($m) $mime = $m;
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $m = finfo_file($finfo, $filePath);
                if ($m) $mime = $m;
                finfo_close($finfo);
            }
        }
        // Headers
        header('Content-Type: ' . $mime);
        $disposition = ($action === 'view') ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        // Prevent buffering issues
        @ob_end_clean();
        // Stream file
        $fp = fopen($filePath, 'rb');
        if ($fp) {
            while (!feof($fp)) {
                echo fread($fp, 8192);
                flush();
            }
            fclose($fp);
        }
        exit;
    } else {
        // No server file; if url is remote, redirect (browser will handle view/download)
        if (!empty($match['url']) && parse_url($match['url'], PHP_URL_SCHEME)) {
            // For "view" just redirect; for download add header hint (can't force remote)
            header('Location: ' . $match['url']);
            exit;
        } else {
            http_response_code(404); echo 'File not available.'; exit;
        }
    }
}

/* ---------- debug dump (optional) ---------- */
if (!empty($_GET['debug']) && !empty($_SESSION['is_admin'])) {
    echo '<pre style="background:#fff;padding:8px;border:1px solid #ddd">RAW APPLICATION: ' . htmlspecialchars(print_r($application, true)) . '</pre>';
    echo '<pre style="background:#fff;padding:8px;border:1px solid #ddd">MERGED DRAFT DATA: ' . htmlspecialchars(print_r($draftData, true)) . '</pre>';
}

/* ---------- Render page and include partial ---------- */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Applicant Profile — <?= h(($draftData['last_name'] ?? '') . ', ' . ($draftData['first_name'] ?? '')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body{background:#f6f7f9}
    .form-summary .form-control.bg-light{ background:#f8f9fa; border:1px solid #e1e5ea; color:#212529; }
    .form-check-input[disabled]{ pointer-events:none; opacity:1; }
    .files-list { margin-top: .6rem; }
    .files-list li { display:flex; align-items:center; justify-content:space-between; padding:.45rem .5rem; border-bottom:1px solid #eef0f3; }
    .card-body .mt-4.border-top { margin-top:1.8rem; }

 <?php include __DIR__ . '/../../hero/navbar_admin.php'; ?>

  </style>
</head>
<body>
 <div class="container mt-4">
    <div class="card shadow-sm">

      <!-- HEADER BLUE BAR -->
      <div class="card-header p-3 d-flex justify-content-between align-items-center" 
           style="background: linear-gradient(90deg, #2d6be6, #5b9df7); color: #fff;">
        
        <div>
          <small class="text-white-100">
            Review of application #
            <?= h($application['application_number'] ?? ('PWD-'.date('Y').'-'.str_pad($app_id,5,'0',STR_PAD_LEFT))) ?>
          </small>
        </div>

        <div class="text-end d-flex align-items-center">
          <?php
            // workflow_status badge (friendly color mapping)
            $wf = $application['workflow_status'] ?? ($application['status'] ?? 'Pending');
            $badgeClass = 'bg-secondary';
            switch (strtolower((string)$wf)) {
              case 'submitted':       $badgeClass = 'bg-warning text-dark'; break;
              case 'pdao_review':     $badgeClass = 'bg-info text-dark'; break;
              case 'cho_review':      $badgeClass = 'bg-primary'; break;
              case 'verified':        $badgeClass = 'bg-success'; break;
              case 'approved':        $badgeClass = 'bg-success'; break;
              case 'pdao_rejected':
              case 'rejected':        $badgeClass = 'bg-danger'; break;
              default:                $badgeClass = 'bg-secondary'; break;
            }
          ?>
<span class="badge <?= h($badgeClass) ?> me-3">
  <?= h(workflow_label((string)$wf)) ?>
</span>

          <?php if (!empty($draftData['pic_url'])):
              // clickable header photo -> opens via this page's file serving if possible (use basename)
              $picBasename = basename(parse_url($draftData['pic_url'], PHP_URL_PATH) ?: $draftData['pic_url']);
              $viewHref = h(rtrim($_SERVER['PHP_SELF'], '/')) . '?id=' . urlencode($app_id) . '&file_action=view&file=' . urlencode($picBasename);
          ?>

          <?php else: ?>
            <div style="display:inline-block;text-align:center;color:#fff;font-weight:600;padding:.35rem .5rem;background:rgba(255,255,255,0.12);border-radius:6px">No photo</div>
          <?php endif; ?>

        </div>

      </div>
      <!-- END HEADER -->

      <div class="card-body">

        <?php
            $partial = __DIR__ . '/partials/form5_readonly.php';
            $fallback = __DIR__ . '/partials/application_summary_partial.php';
            if (file_exists($partial)) {
                include $partial;
            } elseif (file_exists($fallback)) {
                include $fallback;
            } else {
                echo '<div class="alert alert-warning">No summary partial found.</div>';
            }
        ?>

<!-- PDAO action form -> use api/admin_action.php -->
<div class="mt-4 border-top pt-3">
<form id="pdao-action-form" method="post" action="<?= h(rtrim(APP_BASE_URL, '/') . '/api/admin_action.php') ?>">
    <input type="hidden" name="application_id" value="<?= h($app_id) ?>">
    <?php if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24)); ?>
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <div class="mb-3">
        <label class="form-label">
            Remarks (optional — required when rejecting / requesting info)
        </label>
        <textarea id="pdao-remarks"
                  name="remarks"
                  class="form-control"
                  rows="3"
                  placeholder="Enter remarks..."></textarea>
    </div>

<!-- BUTTON ROW (RIGHT-ALIGNED WITH ICONS) -->
<div class="d-flex justify-content-end gap-2">

 <!-- Reject -->
    <button type="button"
            class="btn btn-danger"
            data-action="reject">
        <i class="bi bi-x-circle-fill me-1"></i>
        Reject
    </button>
    

    <!-- Request More Info -->
    <button type="button"
            class="btn btn-warning"
            data-action="request_more_info">
        <i class="bi bi-info-circle-fill me-1"></i>
        Request More Info
    </button>

      <!-- Forward to CHO (Approve) -->
    <button type="button"
            class="btn btn-success"
            data-action="forward_to_cho">
        <i class="bi bi-check-circle-fill me-1"></i>
        Forward to CHO
    </button>
   
</div>

</form>
</div>


<script>
document.querySelectorAll('#pdao-action-form button[data-action]').forEach(btn=>{
  btn.addEventListener('click', async function(e){
    const action = this.dataset.action;
    const remarks = document.getElementById('pdao-remarks').value.trim();
    // require remarks for certain actions client-side
    if ((action === 'reject' || action === 'request_more_info') && remarks === '') {
      alert('Remarks are required for this action.');
      return;
    }
    // send via fetch
    const form = document.getElementById('pdao-action-form');
    const data = new FormData(form);
    data.set('action', action);
    try {
      const resp = await fetch(form.action, { method: 'POST', body: data });
      const json = await resp.json();
      if (!resp.ok || !json.success) {
        alert(json.error || 'Server error');
        return;
      }
      // redirect if server asks
      if (json.redirect) {
        window.location.href = json.redirect;
      } else {
        // otherwise reload to show updated status and history
        window.location.reload();
      }
    } catch(err){
      console.error(err);
      alert('Network error');
    }
  });
});
</script>



</body>
</html>
