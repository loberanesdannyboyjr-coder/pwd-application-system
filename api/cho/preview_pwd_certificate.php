<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/lib/certificate_generator.php';

$application_id = (int) ($_POST['application_id'] ?? 0);

if (!$application_id) {
    exit('Invalid application ID.');
}

/* ===============================
   1️⃣ Fetch Draft Data (ALL STEPS)
================================= */
$draftQuery = pg_query_params(
    $conn,
    "SELECT data
     FROM application_draft
     WHERE application_id = $1
     ORDER BY step ASC",
    [$application_id]
);

$draftData = [];

while ($row = pg_fetch_assoc($draftQuery)) {
    $stepData = json_decode($row['data'], true);
    if (is_array($stepData)) {
        $draftData = array_merge($draftData, $stepData);
    }
}

if (empty($draftData)) {
    exit('Draft data not found.');
}

/* ===============================
   2️⃣ Prepare Personal Info
================================= */

$full_name = trim(
    ($draftData['first_name'] ?? '') . ' ' .
    (!empty($draftData['middle_name']) ? $draftData['middle_name'].' ' : '') .
    ($draftData['last_name'] ?? '')
);

$birthdate = $draftData['birthdate'] ?? null;
$age = '';

if ($birthdate) {
    $b = new DateTime($birthdate);
    $age = (new DateTime())->diff($b)->y;
}

$address = implode(', ', array_filter([
    $draftData['house_no_street'] ?? '',
    $draftData['barangay'] ?? '',
    $draftData['municipality'] ?? '',
    $draftData['province'] ?? ''
]));

/* ===============================
   3️⃣ Medical Info From POST
================================= */

$diagnosis  = $_POST['diagnosis'] ?? 'N/A';
$certifying = $_POST['certifying_physician'] ?? 'N/A';
$disability = $_POST['disability_type'] ?? 'N/A';

/* ===============================
   4️⃣ Logos
================================= */

$basePath = realpath(__DIR__ . '/../../assets/pictures');

$leftLogo  = str_replace('\\','/',$basePath . '/iligan_logo.png');
$rightLogo = str_replace('\\','/',$basePath . '/cho_logo.png');
$watermark = str_replace('\\','/',$basePath . '/pdao_logo.png');


/* ===============================
   5️⃣ Build HTML
================================= */

$html = buildCertificateHTML([
    'full_name'            => $full_name,
    'age'                  => $age,
    'sex'                  => $draftData['sex'] ?? '',
    'civil_status'         => $draftData['civil_status'] ?? '',
    'barangay'             => $draftData['barangay'] ?? '',
    'address'              => $address,
    'diagnosis'            => $diagnosis,
    'disability'           => $disability,
    'certifying_physician' => $certifying,
    'license_no'           => $_POST['certifying_prc_id'] ?? '',
    'signature' => $_POST['certifying_signature'] ?? '',
    'issued_date'          => date('F d, Y'),
    'left_logo'            => $leftLogo,
    'right_logo'           => $rightLogo,
]);

/* ===============================
   6️⃣ Generate PDF
================================= */

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', realpath(__DIR__ . '/../../'));

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("PWD_Certificate_Preview.pdf", ["Attachment" => false]);
exit;