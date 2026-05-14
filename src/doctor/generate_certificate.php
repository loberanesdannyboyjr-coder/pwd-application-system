<?php
declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../lib/certificate_generator.php';

function generateCertificate(int $application_id, $conn): ?string
{
    try {

        /* ===============================
           1️⃣ FETCH APPLICATION DRAFT DATA
        ================================ */
        $draftQuery = pg_query_params(
            $conn,
            "SELECT data
             FROM application_draft
             WHERE application_id = $1
             ORDER BY updated_at ASC",
            [$application_id]
        );

        if (!$draftQuery) {
            throw new Exception("Failed to fetch draft data.");
        }

        $draftData = [];

        while ($row = pg_fetch_assoc($draftQuery)) {
            $stepData = json_decode($row['data'], true);
            if (is_array($stepData)) {
                $draftData = array_merge($draftData, $stepData);
            }
        }

        if (empty($draftData)) {
            throw new Exception("Draft data not found.");
        }

        /* ===============================
           2️⃣ FETCH CERTIFICATION DATA
        ================================ */
        $result = pg_query_params(
            $conn,
            "SELECT c.certifying_physician,
                    c.license_no,
                    c.diagnosis,
                    c.pwd_cert_path,
                    d.disability_type
            FROM certification c
            LEFT JOIN disability d
                ON d.application_id = c.application_id
            WHERE c.application_id = $1",
            [$application_id]
        );

        if (!$result) {
            throw new Exception("Failed to fetch certification data.");
        }

        $certRow = pg_fetch_assoc($result);

        if (!$certRow) {
            throw new Exception("Certification not found.");
        }

                // allow regeneration
        if (!empty($certRow['pwd_cert_path'])) {
            // optional: delete old file
            $oldFile = __DIR__ . '/../../' . $certRow['pwd_cert_path'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
        /* ===============================
           3️⃣ PREPARE DATA
        ================================ */

        $full_name = trim(
            ($draftData['first_name'] ?? '') . ' ' .
            (!empty($draftData['middle_name']) ? $draftData['middle_name'] . ' ' : '') .
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

        $diagnosis = $certRow['diagnosis'] ?? 'N/A';
        $disability = $certRow['disability_type'] ?? 'N/A';

        $certifyingPhysician = $certRow['certifying_physician'] ?? 'N/A';
        $certifyingLicense   = $certRow['license_no'] ?? 'N/A';

        /* ===============================
           4️⃣ LOGO PATHS
        ================================ */

        $basePath = realpath(__DIR__ . '/../../assets/pictures');

            $leftLogo  = 'file://' . str_replace('\\','/', $basePath . '/iligan_logo.png');
            $rightLogo = 'file://' . str_replace('\\','/', $basePath . '/cho_logo.png');


        /* ===============================
           5️⃣ BUILD CERTIFICATE HTML
        ================================ */
          $signature = null;

            $doctorSignatures = [
                "Dr. Alejandro M. Reyes" => "assets/signatures/reyes_signature.png",
                "Dr. Maria Lourdes P. Santos" => "assets/signatures/santos_signature.png",
                "Dr. Ramon C. Villanueva" => "assets/signatures/villanueva_signature.png"
            ];

            if (isset($doctorSignatures[$certifyingPhysician])) {

                $sigPath = realpath(__DIR__ . '/../../' . $doctorSignatures[$certifyingPhysician]);

                if ($sigPath) {
                    $signature = 'file://' . str_replace('\\','/',$sigPath);
                }
            }

        $html = buildCertificateHTML([
            'full_name'            => $full_name,
            'age'                  => $age,
            'sex'                  => $draftData['sex'] ?? '',
            'civil_status'         => $draftData['civil_status'] ?? '',
            'barangay'             => $draftData['barangay'] ?? '',
            'address'              => $address,
            'diagnosis'            => $diagnosis,
            'disability'           => $disability,
            'certifying_physician' => $certifyingPhysician,
            'license_no'           => $certifyingLicense,
            'signature'            => $signature,   // 👈 ADD THIS
            'issued_date'          => date('F d, Y'),
            'left_logo'            => $leftLogo,
            'right_logo'           => $rightLogo,
        ]);

        /* ===============================
           6️⃣ GENERATE PDF
        ================================ */

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', realpath(__DIR__ . '/../../'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        /* ===============================
           7️⃣ SAVE FILE
        ================================ */

        $dir = __DIR__ . '/../../uploads/certificates/';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fileName = "PWD_CERT_" . $application_id . "_" . time() . ".pdf";
        $filePath = $dir . $fileName;

        $pdfOutput = $dompdf->output();

        if (file_put_contents($filePath, $pdfOutput) === false) {
            throw new Exception("Failed to write certificate file.");
        }

        $relativePath = "uploads/certificates/" . $fileName;

        /* ===============================
           8️⃣ UPDATE DATABASE
        ================================ */

        $update = pg_query_params(
            $conn,
            "UPDATE certification
             SET pwd_cert_path = $1,
                 updated_at = NOW()
             WHERE application_id = $2",
            [$relativePath, $application_id]
        );

        if (!$update) {
            throw new Exception("Failed to update certification record.");
        }

        /* ===============================
           9️⃣ RETURN FILE PATH
        ================================ */

        return $relativePath;

    } catch (Exception $e) {

        throw $e;

    }
}