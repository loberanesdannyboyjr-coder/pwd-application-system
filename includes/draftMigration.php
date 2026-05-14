<?php

function migrateDraftToOfficial($conn, $application_id, $applicant_id)
{

function clean($value) {
    return is_string($value) ? trim($value) : $value;
}

error_log("MIGRATION RUNNING FOR APPLICATION: ".$application_id);

/* ===============================
   LOAD DRAFT DATA
================================ */

$draftRes = pg_query_params(
$conn,
"SELECT DISTINCT ON (step) step, data
 FROM application_draft
 WHERE application_id = $1
 ORDER BY step",
[$application_id]
);

if(!$draftRes){
error_log("Draft fetch failed: " . pg_last_error($conn));
return;
}

$form1 = $form2 = $form3 = $form4 = [];

while ($row = pg_fetch_assoc($draftRes)) {

    $step = (int)$row['step'];

    $data = $row['data'];

    if (!is_array($data)) {
        $data = json_decode($data, true);
    }

    if (!is_array($data)) {
        $data = [];
    }

    if ($step === 1) $form1 = $data;
    if ($step === 2) $form2 = $data;
    if ($step === 3) $form3 = $data;
    if ($step === 4) $form4 = $data;
}

error_log("FORM2 DATA: " . json_encode($form2));
error_log("FORM3 DATA: " . json_encode($form3));

/* ===============================
DEBUG LOGS
================================ */

error_log("FORM1: " . print_r($form1,true));
error_log("FORM2: " . print_r($form2,true));
error_log("FORM3: " . print_r($form3,true));
error_log("FORM4: " . print_r($form4,true));


/* ===============================
UPDATE APPLICANT
================================ */

$res = pg_query_params(
$conn,
"UPDATE applicant
SET
    first_name      = $1,
    middle_name     = $2,
    last_name       = $3,
    suffix          = $4,
    birthdate       = $5,
    sex             = $6,
    civil_status    = $7,
    house_no_street = $8,
    barangay        = $9,
    municipality    = $10,
    province        = $11,
    region          = $12,
    landline_no     = $13,
    mobile_no       = $14,
    email_address   = $15,
    updated_at      = NOW()
WHERE applicant_id = $16",
[
    $form1['first_name'] ?? null,
    $form1['middle_name'] ?? null,
    $form1['last_name'] ?? null,
    $form1['suffix'] ?? null,
    $form1['birthdate'] ?? null,
    $form1['sex'] ?? null,
    $form1['civil_status'] ?? null,
    $form1['house_no_street'] ?? null,
    $form1['barangay'] ?? null,
    $form1['municipality'] ?? null,
    $form1['province'] ?? null,
    $form1['region'] ?? null,
    $form1['landline_no'] ?? null,
    $form1['mobile_no'] ?? null,
    $form1['email_address'] ?? null,
    $applicant_id
]
);

if (!$res) {
    error_log("Applicant update failed: " . pg_last_error($conn));
}

/* ===============================
GET CAUSE DETAIL ID
================================ */

$cause_detail_id = null;

$detailRes = pg_query_params(
    $conn,
    "SELECT cause_detail_id 
     FROM causedetail 
     WHERE cause_detail = $1 
     LIMIT 1",
    [$form1['cause_description'] ?? null]
);

if ($detailRes && pg_num_rows($detailRes) > 0) {
    $row = pg_fetch_assoc($detailRes);
    $cause_detail_id = $row['cause_detail_id'];
}
/* ===============================
DISABILITY
================================ */

$res = pg_query_params(
$conn,
"INSERT INTO disability
(
application_id,
disability_type,
cause_detail_id
)
VALUES ($1,$2,$3)
ON CONFLICT (application_id)
DO UPDATE SET
    disability_type = EXCLUDED.disability_type,
    cause_detail_id = EXCLUDED.cause_detail_id",
[
    $application_id,                      // $1
    $form1['disability_type'] ?? null,   // $2
    $cause_detail_id                     // $3 ← THIS IS WHAT I MEANT
]
);

if(!$res){
error_log("Disability upsert failed: " . pg_last_error($conn));
}

/* ===============================
AFFILIATION
================================ */

// 🔥 PREPARE VALUES FIRST
$employmentStatus = $form2['employment_status'] ?? null;
$occupation = $form2['occupation'] ?? null;

// if unemployed → clear occupation
if ($employmentStatus === 'Unemployed') {
    $occupation = null;
}

// handle "Others"
if ($occupation === 'Others') {
    $occupation = $form2['occupation_others'] ?? null;
}

// 🔥 ACCOMPLISHED BY LOGIC
$accomplishedBy = $form2['accomplished_by'] ?? null;

$acc_last = $acc_first = $acc_middle = null;

if ($accomplishedBy === 'Applicant') {
    $acc_last   = $form2['acc_last_name_applicant'] ?? null;
    $acc_first  = $form2['acc_first_name_applicant'] ?? null;
    $acc_middle = $form2['acc_middle_name_applicant'] ?? null;
}
elseif ($accomplishedBy === 'Guardian') {
    $acc_last   = $form2['acc_last_name_guardian'] ?? null;
    $acc_first  = $form2['acc_first_name_guardian'] ?? null;
    $acc_middle = $form2['acc_middle_name_guardian'] ?? null;
}
elseif ($accomplishedBy === 'Representative') {
    $acc_last   = $form2['acc_last_name_rep'] ?? null;
    $acc_first  = $form2['acc_first_name_rep'] ?? null;
    $acc_middle = $form2['acc_middle_name_rep'] ?? null;
}

// 🔥 INSERT / UPDATE
$res = pg_query_params(
$conn,
"INSERT INTO affiliation
(
    applicant_id,
    educational_attainment,
    employment_status,
    employment_category,
    type_of_employment,
    occupation,
    organization_affiliated,
    contact_person,
    office_address,
    tel_no,
    sss_no,
    gsis_no,
    pagibig_no,
    philhealth_no,
    accomplished_by,
    acc_last_name,
    acc_first_name,
    acc_middle_name
)
VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18)
ON CONFLICT (applicant_id)
DO UPDATE SET
    educational_attainment = EXCLUDED.educational_attainment,
    employment_status = EXCLUDED.employment_status,
    employment_category = EXCLUDED.employment_category,
    type_of_employment = EXCLUDED.type_of_employment,
    occupation = EXCLUDED.occupation,
    organization_affiliated = EXCLUDED.organization_affiliated,
    contact_person = EXCLUDED.contact_person,
    office_address = EXCLUDED.office_address,
    tel_no = EXCLUDED.tel_no,
    sss_no = EXCLUDED.sss_no,
    gsis_no = EXCLUDED.gsis_no,
    pagibig_no = EXCLUDED.pagibig_no,
    philhealth_no = EXCLUDED.philhealth_no,
    accomplished_by = EXCLUDED.accomplished_by,
    acc_last_name = EXCLUDED.acc_last_name,
    acc_first_name = EXCLUDED.acc_first_name,
    acc_middle_name = EXCLUDED.acc_middle_name",
[
    $applicant_id,
    clean($form2['educational_attainment'] ?? null),
    clean($employmentStatus),
    clean($form2['employment_category'] ?? null),
    clean($form2['type_of_employment'] ?? null),
    clean($occupation),
    clean($form2['organization_affiliated'] ?? null),
    clean($form2['contact_person'] ?? null),
    clean($form2['office_address'] ?? null),
    clean($form2['tel_no'] ?? null),
    clean($form2['sss_no'] ?? null),
    clean($form2['gsis_no'] ?? null),
    clean($form2['pagibig_no'] ?? null),
    clean($form2['philhealth_no'] ?? null),
    clean($accomplishedBy),
    clean($acc_last),
    clean($acc_first),
    clean($acc_middle)
]
);

if(!$res){
    error_log("Affiliation upsert failed: " . pg_last_error($conn));
}


/* ===============================
EMERGENCY CONTACT
================================ */

$res = pg_query_params(
$conn,
"INSERT INTO emergencycontact
(
applicant_id,
contact_person_name,
contact_person_no
)
VALUES ($1,$2,$3)
ON CONFLICT (applicant_id)
DO UPDATE SET
contact_person_name = EXCLUDED.contact_person_name,
contact_person_no = EXCLUDED.contact_person_no",
[
$applicant_id,
$form3['contact_person_name'] ?? null,
$form3['contact_person_no'] ?? null
]
);

if(!$res){
error_log("Emergencycontact upsert failed: " . pg_last_error($conn));
}


/* ===============================
CERTIFICATION
================================ */

$res = pg_query_params(
$conn,
"INSERT INTO certification
(
application_id,
certifying_physician,
license_no,
processing_officer,
approving_officer,
encoder,
reporting_unit,
control_no
)
VALUES ($1,$2,$3,$4,$5,$6,$7,$8)
ON CONFLICT (application_id)
DO UPDATE SET
certifying_physician = EXCLUDED.certifying_physician,
license_no = EXCLUDED.license_no,
processing_officer = EXCLUDED.processing_officer,
approving_officer = EXCLUDED.approving_officer,
encoder = EXCLUDED.encoder,
reporting_unit = EXCLUDED.reporting_unit,
control_no = EXCLUDED.control_no",
[
$application_id,
$form3['certifying_physician'] ?? null,
$form3['license_no'] ?? null,
$form3['processing_officer'] ?? null,
$form3['approving_officer'] ?? null,
$form3['encoder'] ?? null,
$form3['reporting_unit'] ?? null,
$form3['control_no'] ?? null
]
);

if(!$res){
error_log("Certification upsert failed: " . pg_last_error($conn));
}


/* ===============================
DOCUMENT REQUIREMENTS
================================ */

$res = pg_query_params(
$conn,
"INSERT INTO documentrequirements
(
application_id,
bodypic_path,
barangaycert_path,
medicalcert_path,
old_pwd_id_path,
affidavit_loss_path,
proof_disability_path
)
VALUES ($1,$2,$3,$4,$5,$6,$7)
ON CONFLICT (application_id)
DO UPDATE SET
bodypic_path = EXCLUDED.bodypic_path,
barangaycert_path = EXCLUDED.barangaycert_path,
medicalcert_path = EXCLUDED.medicalcert_path,
old_pwd_id_path = EXCLUDED.old_pwd_id_path,
affidavit_loss_path = EXCLUDED.affidavit_loss_path,
proof_disability_path = EXCLUDED.proof_disability_path",
[
$application_id,
$form4['bodypic_path'] ?? null,
$form4['barangaycert_path'] ?? null,
$form4['medicalcert_path'] ?? null,
$form4['old_pwd_id_path'] ?? null,
$form4['affidavit_loss_path'] ?? null,
$form4['proof_disability_path'] ?? null
]
);

if(!$res){
error_log("Documentrequirements upsert failed: " . pg_last_error($conn));
}

}