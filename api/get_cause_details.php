<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_GET['cause_id'])) {
    echo json_encode([]);
    exit;
}

$cause_id = (int) $_GET['cause_id'];

$res = pg_query_params(
    $conn,
    "SELECT cause_detail 
     FROM causedetail 
     WHERE cause_disability_id = $1
     ORDER BY cause_detail ASC",
    [$cause_id]
);

$data = [];

while ($row = pg_fetch_assoc($res)) {
    $data[] = $row['cause_detail'];
}

echo json_encode($data);