<?php
// upload_draft_file.php
session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../includes/DraftHelper.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['application_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$application_id = (int)$_SESSION['application_id'];
$step = 4;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Invalid method']);
  exit;
}

// Which input fired this upload?
$input = $_POST['input'] ?? '';

$map = [
  'bodypic'        => ['col' => 'bodypic_path',           'subdir' => 'photos'],
  'barangaycert'   => ['col' => 'barangaycert_path',      'subdir' => 'docs'],
  'medicalcert'    => ['col' => 'medicalcert_path',       'subdir' => 'docs'],
  'oldpwdid'       => ['col' => 'old_pwd_id_path',        'subdir' => 'docs'],
  'affidavit'      => ['col' => 'affidavit_loss_path',    'subdir' => 'docs'],
  'proofdisability'=> ['col' => 'proof_disability_path',  'subdir' => 'photos'],
];

if (!isset($map[$input])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Unknown input']);
  exit;
}

$col    = $map[$input]['col'];
$subdir = $map[$input]['subdir'];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
  exit;
}

/* =========================================================
   ENSURE ROW EXISTS (FIXED: schema-qualified)
========================================================= */
pg_query_params(
  $conn,
  "INSERT INTO public.documentrequirements (application_id)
   SELECT $1
   WHERE NOT EXISTS (
     SELECT 1 FROM public.documentrequirements WHERE application_id = $1
   )",
  [$application_id]
);

/* =========================================================
   VALIDATE FILE
========================================================= */
$allowed = ['jpg','jpeg','png','gif','webp','pdf'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed, true)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid file type']);
  exit;
}

$maxBytes = 10 * 1024 * 1024; // 10MB
if (!empty($_FILES['file']['size']) && $_FILES['file']['size'] > $maxBytes) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'File too large']);
  exit;
}

/* =========================================================
   MOVE FILE
========================================================= */
$root   = realpath(__DIR__ . '/../../');
$fsDir  = $root . '/uploads/' . $subdir;
$webDir = '/uploads/' . $subdir;

if (!is_dir($fsDir)) {
  @mkdir($fsDir, 0777, true);
}

$name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$fs   = $fsDir . '/' . $name;
$web  = $webDir . '/' . $name;

if (!move_uploaded_file($_FILES['file']['tmp_name'], $fs)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
  exit;
}

/* =========================================================
   UPDATE documentrequirements (FIXED)
========================================================= */
pg_query_params(
  $conn,
  "UPDATE public.documentrequirements
     SET {$col} = $1,
         updated_at = NOW()
   WHERE application_id = $2",
  [$web, $application_id]
);

/* =========================================================
   MERGE INTO STEP-4 DRAFT
========================================================= */
saveDraftData($step, [$col => $web], $application_id);

/* =========================================================
   RESPONSE
========================================================= */
echo json_encode([
  'ok'       => true,
  'path'     => $web,
  'column'   => $col,
  'input'    => $input,
  'filename' => basename($web),
]);
