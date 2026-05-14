<?php
// src/doctor/view_a.php
// CHO/Doctor view for a single application (review + verify/reject).
// Requires: config/db.php

// --- bootstrap & auth ---
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Simple auth: adjust keys to your app's session variables if different.
$session_user_id = $_SESSION['user_id'] ?? null;
$session_role = strtoupper($_SESSION['role'] ?? ($_SESSION['user_role'] ?? ''));

// Allow roles CHO or DOCTOR (adjust if you use different labels)
if (empty($session_user_id) || !in_array($session_role, ['CHO','DOCTOR','ADMIN'], true)) {
    // not authorised - redirect to a sign-in page (adjust path)
    header('Location: ' . (defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '/PWD-Application-System') . '/src/auth/signin.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
if (!defined('APP_BASE_URL')) {
    // attempt include paths if you have a paths file
    if (file_exists(__DIR__ . '/../../config/paths.php')) {
        require_once __DIR__ . '/../../config/paths.php';
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE); }

$app_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($app_id <= 0) {
    echo '<div class="container mt-4"><div class="alert alert-warning">Missing application id.</div></div>';
    exit;
}

/* ---- helper functions for files (reuse pattern from admin view) ---- */
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

/* ---------------- Fetch application + applicant + docs ------------------ */
$sql = "SELECT a.*, ap.* FROM application a LEFT JOIN applicant ap ON a.applicant_id = ap.applicant_id WHERE a.application_id = $1 LIMIT 1";
$res = @pg_query_params($conn, $sql, [$app_id]);
if ($res === false) {
    error_log('doctor view_a query error: '.pg_last_error($conn));
    echo '<div class="container mt-4"><div class="alert alert-danger">An internal error occurred while loading the application.</div></div>';
    exit;
}
$row = pg_fetch_assoc($res);
if (!$row) {
    echo '<div class="container mt-4"><div class="alert alert-warning">Application not found.</div></div>';
    exit;
}
$application = $row;

$currentWorkflow = $application['workflow_status'] ?? 'draft';


/* ---------------- documentrequirements ------------------ */
$docs = null;
$docs_sql = "SELECT * FROM documentrequirements WHERE application_id = $1 LIMIT 1";
$docs_res = @pg_query_params($conn, $docs_sql, [$app_id]);
if ($docs_res && pg_num_rows($docs_res) > 0) $docs = pg_fetch_assoc($docs_res);

/* ---------------- certification (CHO certificate) ------------------ */
$cert = null;
$cert_sql = "SELECT pwd_cert_path FROM certification WHERE application_id = $1 LIMIT 1";
$cert_res = @pg_query_params($conn, $cert_sql, [$app_id]);

if ($cert_res && pg_num_rows($cert_res) > 0) {
    $cert = pg_fetch_assoc($cert_res);
}

/* ---------------- application_draft rows (merge JSON data) ------------------ */
$draft_json_merged = [];
$draft_q = "SELECT data, step FROM application_draft WHERE application_id = $1 ORDER BY step ASC, updated_at ASC";
$draft_res = @pg_query_params($conn, $draft_q, [$app_id]);
if ($draft_res && pg_num_rows($draft_res) > 0) {
    while ($r = pg_fetch_assoc($draft_res)) {
        if (!empty($r['data'])) {
            $decoded = json_decode($r['data'], true);
            if (is_array($decoded)) $draft_json_merged = array_merge($draft_json_merged, $decoded);
        }
    }
}

/* ---------- normalize and merge (draft -> applicant+application -> docs) ---------- */
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

$normalizedDraft = [];
foreach ($draft_json_merged as $k => $v) {
    $k2 = preg_replace('/[^a-z0-9_]+/i', '_', strtolower((string)$k));
    $normalizedDraft[$k2] = $v;
}
$normalizedApp = normalize_row($application ?: []);
$normalizedDocs = normalize_row($docs ?: []);

$draftData = array_merge($normalizedApp, $normalizedDocs, $normalizedDraft);

// compatibility aliases
if (empty($draftData['application_type']) && !empty($application['application_type'])) $draftData['application_type'] = $application['application_type'];
if (empty($draftData['pwd_number']) && !empty($application['pwd_number'])) $draftData['pwd_number'] = $application['pwd_number'] ?? $application['pwdno'] ?? '';
if (empty($draftData['application_date'])) $draftData['application_date'] = $application['application_date'] ?? $application['created_at'] ?? '';

/* ---------- build files list (exclude redundant 1x1 in files) ---------- */
$draftData['files'] = [];
$map_docs = [
    'bodypic_path' => 'Whole Body Picture',
    'barangaycert_path' => 'Barangay Certificate',
    'proof_disability_path'=> 'Proof of Disability',
    'medicalcert_path' => 'Medical Certificate',
    'old_pwd_id_path' => 'Old PWD ID',
    'affidavit_loss_path' => 'Affidavit of Loss'
];

/* ---------- add CHO issued certificate ---------- */

if (!empty($cert['pwd_cert_path'])) {

    $stored = $cert['pwd_cert_path'];

    $draftData['files'][] = [
        'label' => 'PWD Certificate',
        'path'  => $stored,
        'url'   => build_url_from_stored($stored),
        'server_candidates' => server_path_candidates($stored),
        'server_found' => find_first_existing(server_path_candidates($stored))
    ];
}
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

/* ---------- pick header 1x1 picture ---------- */
$pic_candidate = '';
if (!empty($normalizedDocs['pic_1x1_path'])) $pic_candidate = $normalizedDocs['pic_1x1_path'];
elseif (!empty($normalizedApp['pic_1x1_path'])) $pic_candidate = $normalizedApp['pic_1x1_path'];
elseif (!empty($normalizedDraft['pic_1x1_path'])) $pic_candidate = $normalizedDraft['pic_1x1_path'];
$draftData['pic_url'] = $pic_candidate ? build_url_from_stored($pic_candidate) : '';
$draftData['pic_server_found'] = $pic_candidate ? find_first_existing(server_path_candidates($pic_candidate)) : null;

// ---------- file serving handler (view / download) ----------
if (!empty($_GET['file_action']) && in_array($_GET['file_action'], ['view','download'], true) && !empty($_GET['file'])) {
    $action = $_GET['file_action'];
    $requested = basename($_GET['file']);

    $match = null;
    if (!empty($draftData['files']) && is_array($draftData['files'])) {
        foreach ($draftData['files'] as $f) {
            $fbasename = basename(parse_url($f['path'] ?? $f['url'] ?? '', PHP_URL_PATH) ?: ($f['path'] ?? ''));
            if ($fbasename === $requested) { $match = $f; break; }
        }
    }

    if (!$match) { http_response_code(404); echo 'File not found.'; exit; }

    if (!empty($match['server_found']) && @file_exists($match['server_found'])) {
        $filePath = $match['server_found'];
        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) $mime = mime_content_type($filePath);

        header('Content-Type: ' . $mime);
        $disposition = ($action === 'view') ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disposition . '; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));

        @ob_end_clean();
        readfile($filePath);
        exit;
    }

    if (!empty($match['url']) && parse_url($match['url'], PHP_URL_SCHEME)) {
        header('Location: ' . $match['url']);
        exit;
    }

    http_response_code(404);
    echo 'File not available.';
    exit;
}


$hist_sql = "SELECT hist_id, from_status, to_status, changed_by, role, remarks, created_at
             FROM application_status_history
             WHERE application_id = $1
             ORDER BY created_at ASC";
$hist_res = @pg_query_params($conn, $hist_sql, [$app_id]);
$history_rows = $hist_res && pg_num_rows($hist_res) ? pg_fetch_all($hist_res) : [];

$latestRemark = '';

if (!empty($history_rows)) {
    foreach (array_reverse($history_rows) as $h) {
        if (!empty($h['remarks'])) {
            $latestRemark = $h['remarks'];
            break;
        }
    }
}



/* ---------------- Render HTML ------------------ */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Applicant Profile — <?= h(($draftData['last_name'] ?? '') . ', ' . ($draftData['first_name'] ?? '')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f6f7f9}
    .card-header-blue { background: linear-gradient(90deg,#2d6be6,#5b9df7); color:#fff }
    .files-list li { display:flex; align-items:center; justify-content:space-between; padding:.45rem .5rem; border-bottom:1px solid #eef0f3; }
  </style>
</head>
<body>
 <div class="container mt-4">
    <div class="card shadow-sm">
      <div class="card-header card-header-blue p-3 d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0 text-white">Applicant Profile</h5>
          <small class="text-white-50">Review of application # <?= h($application['application_number'] ?? ('PWD-'.date('Y').'-'.str_pad($app_id,5,'0',STR_PAD_LEFT))) ?></small>
        </div>
        <div>
                  <?php
          $badgeMap = [
              'cho_review'     => 'warning',
              'cho_accepted'   => 'success',
              'approved_final' => 'primary',
              'cho_rejected'   => 'danger'
          ];
          $badgeClass = $badgeMap[$currentWorkflow] ?? 'secondary';
          ?>
          <span class="badge bg-<?= $badgeClass ?>">

          <?= h(strtoupper(str_replace('_',' ', $currentWorkflow))) ?>
        </span>
        </div>
      </div>

      <div class="card-body">
        <?php
        // include the admin partial if available (re-uses your existing summary markup)
        $partial = __DIR__ . '/../admin_side/partials/form5_readonly.php';
        if (file_exists($partial)) {
            include $partial;
        } else {
            echo '<div class="alert alert-warning">No summary partial found.</div>';
        }
        ?>

            <?php if (!empty($latestRemark)): ?>
        <div class="alert alert-danger mt-3">
        <strong>Doctor's Remarks:</strong><br>
        <?= h($latestRemark) ?>
        </div>
        <?php endif; ?>

        <!-- CHO action form -->
        <?php
                $canTakeAction = (
            $currentWorkflow === 'cho_review'
            && in_array($session_role, ['CHO','ADMIN'], true)
        );
        ?>
        <?php if ($canTakeAction): ?>
        <form id="cho-action-form" method="post" action="<?= h((defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . '/api/admin_action.php') ?>" class="mt-4">
          <input type="hidden" name="application_id" value="<?= h($app_id) ?>">
          <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
          <div class="mb-3">
            <label>Remarks (optional — required when rejecting)</label>
            <textarea id="cho-remarks" name="remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
          </div>

          <div class="d-flex align-items-center">
          <button type="button" class="btn btn-success me-2" data-action="cho_verify">
            Accept (Medical Assessment Passed)
          </button>
            <button type="button" class="btn btn-danger me-2" data-action="cho_reject">Reject</button>
            <a href="<?= h((defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . '/src/doctor/applications.php') ?>" class="btn btn-outline-secondary">Back to list</a>
          </div>
        </form>
        <?php else: ?>
        <div class="mt-4">
          <a href="<?= h((defined('APP_BASE_URL') ? rtrim(APP_BASE_URL, '/') : '') . '/src/doctor/applications.php') ?>" class="btn btn-outline-secondary">Back to list</a>
        </div>
        <?php endif; ?>


      </div>
    </div>
 </div>

<script>
document.querySelectorAll('#cho-action-form button[data-action]').forEach(btn=>{
  btn.addEventListener('click', function(e){
    const action = this.getAttribute('data-action');
    const remarks = document.getElementById('cho-remarks').value.trim();
    // require remarks for reject
    if (action === 'cho_reject' && remarks === '') {
      if (!confirm('Reject this application without remarks? Remarks are strongly recommended. Continue?')) return;
    }
    // set action param and submit via fetch to show JSON result (keeps page UX nicer)
    const form = document.getElementById('cho-action-form');
    const fd = new FormData(form);
    fd.set('action', action);

    fetch(form.action, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(r => r.json()).then(j => {
      if (j.success) {
        if (j.redirect) {
          window.location.href = j.redirect;
        } else {
          // reload to reflect new status/history
          window.location.reload();
        }
      } else {
        alert('Action failed: ' + (j.error || 'Unknown error'));
        console.error(j);
      }
    }).catch(err=>{
      console.error(err);
      alert('Server error. See console for details.');
    });
  });
});
</script>

</body>
</html>
