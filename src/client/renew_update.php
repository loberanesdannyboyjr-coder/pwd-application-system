<?php
session_start();
require_once '../../config/db.php';

$applicant_id = $_SESSION['applicant_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    pg_query_params(
        $conn,
        "UPDATE applicant
         SET barangay=$1,
             province=$2,
             mobile_no=$3,
             email_address=$4,
             updated_at=NOW()
         WHERE applicant_id=$5",
        [
            $_POST['barangay'],
            $_POST['province'],
            $_POST['mobile_no'],
            $_POST['email_address'],
            $applicant_id
        ]
    );

    header("Location: renew.php");
    exit;
}

$res = pg_query_params($conn,
    "SELECT * FROM applicant WHERE applicant_id=$1",
    [$applicant_id]
);

$data = pg_fetch_assoc($res);
?>

<h3>Update Info</h3>

<form method="POST">
Barangay: <input name="barangay" value="<?= $data['barangay'] ?>"><br>
Province: <input name="province" value="<?= $data['province'] ?>"><br>
Mobile: <input name="mobile_no" value="<?= $data['mobile_no'] ?>"><br>
Email: <input name="email_address" value="<?= $data['email_address'] ?>"><br>

<button>Save</button>
</form>