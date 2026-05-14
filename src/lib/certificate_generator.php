<?php
declare(strict_types=1);

function buildCertificateHTML(array $data): string
{
    $full_name           = htmlspecialchars($data['full_name'] ?? 'N/A');
    $age                 = $data['age'] ?? null;
    $address             = htmlspecialchars($data['address'] ?? 'N/A');
    $sex                 = htmlspecialchars($data['sex'] ?? '');
    $status              = htmlspecialchars($data['civil_status'] ?? '');
    $barangay            = htmlspecialchars($data['barangay'] ?? '');
    $diagnosis           = htmlspecialchars($data['diagnosis'] ?? 'N/A');
    $disability          = htmlspecialchars($data['disability'] ?? 'N/A');
    $certifyingPhysician = htmlspecialchars($data['certifying_physician'] ?? 'N/A');
    $licenseNo = htmlspecialchars($data['license_no'] ?? 'N/A');
    $signaturePath = '';

        if (!empty($data['signature'])) {

            // If already file:// path (from generate_certificate)
            if (str_starts_with($data['signature'], 'file://')) {
                $signaturePath = $data['signature'];
            } else {
                $realSig = realpath(__DIR__ . '/../../' . ltrim($data['signature'], '/'));

                if ($realSig) {
                    $signaturePath = 'file://' . str_replace('\\','/',$realSig);
                }
            }
        }


    $issuedDate          = htmlspecialchars($data['issued_date'] ?? date('F d, Y'));
    $leftLogo   = $data['left_logo'] ?? '';
    $rightLogo  = $data['right_logo'] ?? '';

        if ($leftLogo && !str_starts_with($leftLogo, 'file://')) {
        $realLeft = realpath(__DIR__ . '/../../' . ltrim($leftLogo,'/'));
        if ($realLeft) {
            $leftLogo = 'file://' . str_replace('\\','/',$realLeft);
        }
    }

    if ($rightLogo && !str_starts_with($rightLogo, 'file://')) {
        $realRight = realpath(__DIR__ . '/../../' . ltrim($rightLogo,'/'));
        if ($realRight) {
            $rightLogo = 'file://' . str_replace('\\','/',$realRight);
        }
    }

    // 🧠 Build dynamic personal description cleanly
        $introParts = [];

        if ($age && $sex) {
            $introParts[] = $age . " / " . $sex;
        } elseif ($age) {
            $introParts[] = $age;
        } elseif ($sex) {
            $introParts[] = $sex;
        }

        if ($status) {
            $introParts[] = $status;
        }

        $introText = implode(', ', $introParts);
         return 
    "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                font-family: DejaVu Sans;
                margin: 60px 70px;
                font-size: 14px;
                line-height: 1.7;
            }

            .header-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }

            .header-table td {
                vertical-align: middle;
            }

            .logo {
                width: 85px;
            }

            .header-text {
                text-align: center;
                font-family: 'Times New Roman', serif;
                font-size: 14px;
            }

            .office {
                color: #b30000;
                font-weight: bold;
                font-size: 16px;
            }

            .title {
                text-align: center;
                font-weight: bold;
                font-size: 30px;
                color: green;
                margin: 35px 0 25px 0;
            }

            .content {
                text-align: justify;
                margin-bottom: 8px;
            }

            .diagnosis {
                text-align: center;
                font-weight: bold;
                font-size: 22px;
                margin: 18px 0;
            }

            .watermark {
                position: fixed;
                top: 35%;
                left: 25%;
                width: 350px;
                opacity: 0.05;
                z-index: -1;
            }

            .signature {
                margin-top: 50px;
                text-align: right;

            .signature img{
              margin-bottom:-10px;
}
            }
        </style>
    </head>

    <body>

        <table class='header-table'>
            <tr>
                <td width='20%'>
                    <img src='$leftLogo' class='logo'>
                </td>
                <td width='60%' class='header-text'>
                    Republic of the Philippines<br>
                    City of Iligan<br>
                    <div class='office'>OFFICE OF THE CITY HEALTH OFFICE</div>
                    Gen. Aguinaldo Street, Pala-o, Iligan City
                </td>
                <td width='20%' style='text-align:right;'>
                    <img src='$rightLogo' class='logo'>
                </td>
            </tr>
        </table>

        <div class='title'>CERTIFICATION OF DISABILITY</div>

        <div class='content'>
            To Whom It May Concern,<br><br>

            This is to certify that <strong>$full_name</strong>" .
            ($introText ? ", $introText," : "") . "
            a resident of Barangay $barangay, Iligan City,
            has undergone proper examination and/or assessment
            in relation to the nature of disability.

            Based on the findings and evaluation conducted,
            the patient has been diagnosed with <strong>$diagnosis</strong>
            which resulted in:
        </div>

        <div class='diagnosis'>$disability Disability.</div>

        <div class='content'>
            This certification is issued this $issuedDate in compliance with the requirement
            in issuance of PWD-IDC for the benefits and privileges of persons
            with disabilities as mandated by <strong>Republic Act No. 9442 or
            Magna Carta for Persons with Disabilities</strong>,
            and not for medico-legal purposes.
        </div>

            <div class='signature'>
            <br>

            " . ($signaturePath ? "<img src='$signaturePath' style='height:65px;'><br>"  : "") . "

            <strong>$certifyingPhysician</strong><br>
            <em>City Health Officer</em><br>
            PRC ID: $licenseNo
        </div>

    </body>
    </html>
    ";
}