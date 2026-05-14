<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

/* ===============================
   AUTH CHECK
   =============================== */
if (!isset($_SESSION['applicant_id'])) {
    header('Location: /public/login_form.php');
    exit;
}

$applicantId = $_SESSION['applicant_id'];

/* ===============================
   APPLICATION CHECKS
================================ */

/* Check if applicant already has approved PWD */
$approvedCheck = pg_query_params(
    $conn,
    "SELECT application_id
     FROM application
     WHERE applicant_id = $1
     AND workflow_status = 'pdao_approved'
     LIMIT 1",
    [$applicantId]
);

$hasApprovedPWD = pg_num_rows($approvedCheck) > 0;


/* Check if applicant has pending application */
$pendingCheck = pg_query_params(
    $conn,
    "SELECT application_id
     FROM application
     WHERE applicant_id = $1
     AND workflow_status NOT IN ('pdao_approved','rejected')
     LIMIT 1",
    [$applicantId]
);

$hasPendingApplication = pg_num_rows($pendingCheck) > 0;

/* ===============================
   STATUS GROUPS
   =============================== */
$statusGroups = [
    'Pending' => [
        'Pending',
        'For CHO Verification',
        'More Info Requested',
        'Pending - More Info Requested'
    ],
    'Approved' => [
        'Approved',
        'CHO Verified'
    ],
    'Denied' => [
        'Denied',
        'CHO Rejected'
    ]
];

$statusCounts = [];

foreach ($statusGroups as $label => $enumValues) {
    $params = [$applicantId];
    $placeholders = [];
    $i = 2;

    foreach ($enumValues as $value) {
        $placeholders[] = '$' . $i;
        $params[] = $value;
        $i++;
    }

    $sql = "
        SELECT COUNT(*)
        FROM application
        WHERE applicant_id = $1
        AND status IN (" . implode(',', $placeholders) . ")
    ";

    $result = pg_query_params($conn, $sql, $params);
    $row = $result ? pg_fetch_row($result) : [0];
    $statusCounts[$label] = $row[0] ?? 0;
}

/* ===============================
   LATEST APPLICATION
   =============================== */
$latestResult = pg_query_params(
    $conn,
    "SELECT application_type, status, created_at
     FROM application
     WHERE applicant_id = $1
     ORDER BY created_at DESC
     LIMIT 1",
    [$applicantId]
);

$latestApp = $latestResult ? pg_fetch_assoc($latestResult) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Applicant Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>

<body id="pageBody"
      class="bg-gray-100 min-h-screen transition-all duration-300 pl-14">

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- TOP BAR -->
<header class="bg-gradient-to-r from-blue-700 to-blue-900 text-white px-6 py-4 flex items-center justify-between shadow">

  <button onclick="toggleSidebar()" class="text-2xl">
    <i class="fas fa-bars"></i>
  </button>

  <h1 class="text-lg font-semibold tracking-wide">
    Applicant Dashboard
  </h1>

</header>

<!-- MAIN CONTENT -->
<main class="p-8">

<!-- ACTION BUTTONS -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

<?php if(!$hasApprovedPWD && !$hasPendingApplication): ?>

<a href="start_application.php"
   class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg shadow text-center">

<i class="fas fa-id-card text-3xl mb-3"></i>

<p class="font-semibold">Apply for PWD ID</p>

</a>

<?php endif; ?>


<?php if($hasApprovedPWD && !$hasPendingApplication): ?>

<a href="renew.php"
   class="bg-green-600 hover:bg-green-700 text-white p-6 rounded-lg shadow text-center">

<i class="fas fa-sync-alt text-3xl mb-3"></i>

<p class="font-semibold">Renew PWD ID</p>

</a>

<?php endif; ?>


<?php if($hasPendingApplication): ?>

<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 p-4 rounded mb-6">

You already have a pending application being processed.

</div>

<?php endif; ?>


<?php if($hasApprovedPWD && !$hasPendingApplication): ?>

<a href="lost.php"
   class="bg-red-600 hover:bg-red-700 text-white p-6 rounded-lg shadow text-center">

<i class="fas fa-exclamation-triangle text-3xl mb-3"></i>

<p class="font-semibold">Report Lost ID</p>

</a>

<?php endif; ?>

</div>

  <!-- STATS -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

    <div class="bg-white p-6 rounded-lg shadow">
      <p class="text-sm text-gray-500">Pending</p>
      <p class="text-3xl font-bold text-yellow-500">
        <?= $statusCounts['Pending'] ?>
      </p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
      <p class="text-sm text-gray-500">Approved</p>
      <p class="text-3xl font-bold text-green-600">
        <?= $statusCounts['Approved'] ?>
      </p>
    </div>

    <div class="bg-white p-6 rounded-lg shadow">
      <p class="text-sm text-gray-500">Denied</p>
      <p class="text-3xl font-bold text-red-600">
        <?= $statusCounts['Denied'] ?>
      </p>
    </div>

  </div>

  <!-- LATEST APPLICATION -->
  <div class="bg-white p-6 rounded-lg shadow">
    <h2 class="font-semibold mb-4 text-lg">
      Latest Application
    </h2>

    <?php if ($latestApp): ?>
      <p><strong>Type:</strong> <?= htmlspecialchars($latestApp['application_type']) ?></p>
      <p><strong>Status:</strong> <?= htmlspecialchars($latestApp['status']) ?></p>
      <p><strong>Date:</strong> <?= date('F d, Y', strtotime($latestApp['created_at'])) ?></p>
    <?php else: ?>
      <p class="text-gray-500">No applications yet.</p>
    <?php endif; ?>
  </div>

</main>

<!-- SIDEBAR TOGGLE SCRIPT -->
<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const body = document.getElementById('pageBody');

  const isCollapsed = sidebar.classList.contains('w-14');

  if (isCollapsed) {
    sidebar.classList.remove('w-14');
    sidebar.classList.add('w-64');
    body.classList.remove('pl-14');
    body.classList.add('pl-64');
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
  } else {
    sidebar.classList.remove('w-64');
    sidebar.classList.add('w-14');
    body.classList.remove('pl-64');
    body.classList.add('pl-14');
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.add('hidden'));
  }
}
</script>

</body>
</html>
