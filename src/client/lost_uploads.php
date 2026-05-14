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

    $affidavit = upload($_FILES['affidavit'], 'docs');
    $photo = upload($_FILES['photo'], 'photos');

    if(!$affidavit){
        die("Affidavit of Loss is required.");
    }

    /* UPDATE PHOTO */
    if($photo){
        pg_query_params($conn,
            "UPDATE applicant SET pic_1x1_path=$1 WHERE applicant_id=$2",
            [$photo,$applicant_id]
        );
    }

    /* SAVE DOCUMENT */
    pg_query_params(
        $conn,
        "INSERT INTO documentrequirements
        (application_id, affidavit_loss_path)
        VALUES ($1,$2)
        ON CONFLICT (application_id)
        DO UPDATE SET
            affidavit_loss_path = EXCLUDED.affidavit_loss_path",
        [$application_id,$affidavit]
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

<h3>Lost ID Upload</h3>

<form method="POST" enctype="multipart/form-data">

Affidavit of Loss: <input type="file" name="affidavit" required><br>
New Photo: <input type="file" name="photo"><br>

<button>Submit</button>

</form>