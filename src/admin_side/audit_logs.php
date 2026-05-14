<?php
session_start();
require_once '../../config/db.php';

$res = pg_query($conn,"
SELECT *
FROM audit_logs
ORDER BY created_at DESC
LIMIT 100
");

$logs = pg_fetch_all($res) ?: [];
?>

<!DOCTYPE html>
<html>
<head>
<title>Audit Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">

<h4>Audit Logs</h4>

<table class="table table-bordered">
<thead>
<tr>
<th>User ID</th>
<th>Role</th>
<th>Action</th>
<th>Application</th>
<th>Date</th>
</tr>
</thead>

<tbody>
<?php foreach($logs as $log): ?>
<tr>
<td><?= $log['user_id'] ?></td>
<td><?= $log['role'] ?></td>
<td><?= $log['action'] ?></td>
<td><?= $log['application_id'] ?></td>
<td><?= $log['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>

</table>

</body>
</html>