<?php
session_start();
require_once '../../config/db.php';

$applicant_id = $_SESSION['applicant_id'];
$application_id = $_SESSION['application_id'];

function upload($file, $folder){
    if(empty($file['name'])) return null;

    $name = time().'_'.bin2hex(random_bytes(3)).'_'.basename($file['name']);
    $path = "uploads/$folder/".$name;

    move_uploaded_file($file['tmp_name'], "../../".$path);

    return "/".$path;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old_id = upload($_FILES['old_pwd_id'], 'docs');
    $barangay = upload($_FILES['barangaycert'], 'docs');
    $medical = upload($_FILES['medicalcert'], 'docs');
    $photo = upload($_FILES['photo'], 'photos');

    /* UPDATE PHOTO */
    if($photo){
        pg_query_params($conn,
            "UPDATE applicant SET pic_1x1_path=$1 WHERE applicant_id=$2",
            [$photo,$applicant_id]
        );
    }

    /* SAVE DOCUMENTS */
    pg_query_params(
        $conn,
        "INSERT INTO documentrequirements
        (application_id, old_pwd_id_path, barangaycert_path, medicalcert_path)
        VALUES ($1,$2,$3,$4)
        ON CONFLICT (application_id)
        DO UPDATE SET
            old_pwd_id_path = EXCLUDED.old_pwd_id_path,
            barangaycert_path = EXCLUDED.barangaycert_path,
            medicalcert_path = EXCLUDED.medicalcert_path",
        [$application_id,$old_id,$barangay,$medical]
    );

    /* SUBMIT */
    pg_query_params($conn,
        "UPDATE application
         SET workflow_status='pdao_review',
             application_date=NOW()
         WHERE application_id=$1",
        [$application_id]
    );

    header("Location: submission_success.php");
    exit;
}
?>

<h3>Renewal Uploads</h3>

<form method="POST" enctype="multipart/form-data">

Old PWD ID: <input type="file" name="old_pwd_id" required><br>
Barangay Cert: <input type="file" name="barangaycert"><br>
Medical Cert: <input type="file" name="medicalcert"><br>
New Photo: <input type="file" name="photo"><br>

<button>Submit Renewal</button>

</form>