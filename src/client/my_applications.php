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
   FETCH APPLICATION HISTORY
   =============================== */
$sql = "
SELECT application_id,
       application_type,
       status,
       workflow_status,
       application_date
FROM application
WHERE applicant_id = $1
  AND application_date IS NOT NULL
ORDER BY application_date DESC
";

$result = pg_query_params($conn, $sql, [$applicantId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Applications</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

</head>

<body
  id="pageBody"
  class="bg-gray-100 text-gray-800 pl-16 transition-all duration-300">

<!-- SIDEBAR -->
<?php include 'sidebar.php'; ?>

<!-- HEADER -->
<header class="relative bg-gradient-to-r from-blue-700 to-blue-900 text-white py-4">

          <!-- Burger button (top-left, fixed position) -->
          <button
            onclick="toggleSidebar()"
            class="absolute left-6 top-1/2 -translate-y-1/2 text-2xl z-50">
            <i class="fas fa-bars"></i>
          </button>

          <!-- Centered Title -->
          <div class="text-center">
            <h1 class="text-xl font-semibold">My Applications</h1>
          </div>
        </header>

        <!-- MAIN CONTENT -->
        <main class="p-6 transition-all duration-300">

          <div class="bg-white rounded-lg shadow overflow-x-auto max-w-5xl mx-auto">

            <table class="min-w-full text-sm">
              <thead class="bg-gray-600 text-white">
                <tr>
                  <th class="px-4 py-3 text-left">Application Type</th>
                  <th class="px-4 py-3 text-left">Status</th>
                  <th class="px-4 py-3 text-left">Date Submitted</th>
                  <th class="px-4 py-3 text-center">Action</th>
                </tr>
              </thead>

              <tbody class="divide-y">
                <?php if ($result && pg_num_rows($result) > 0): ?>
                  <?php while ($row = pg_fetch_assoc($result)): ?>

        <?php
            $workflow = strtolower($row['workflow_status'] ?? '');
            $rawStatus = strtolower($row['status'] ?? '');

            // Determine display status FIRST
            if ($workflow === 'pdao_approved') {
                $displayStatus = 'PDAO Approved';
            } elseif ($workflow === 'cho_verified') {
                $displayStatus = 'CHO Verified';
            } elseif ($workflow === 'rejected') {
                $displayStatus = 'Rejected';
            } elseif ($workflow === 'draft') {
                $displayStatus = 'Draft';
            } else {
                $displayStatus = ucfirst($rawStatus ?: 'Pending');
            }

            // THEN use it for styling
            $status = strtolower($displayStatus);
            $badgeClass = 'bg-yellow-100 text-yellow-700';

            if (str_contains($status, 'approved') || str_contains($status, 'verified')) {
                $badgeClass = 'bg-green-100 text-green-700';
            } elseif (str_contains($status, 'denied') || str_contains($status, 'rejected')) {
                $badgeClass = 'bg-red-100 text-red-700';
            }
        ?>

            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 capitalize">
                <?= htmlspecialchars($row['application_type']) ?>
              </td>

              <td class="px-4 py-3">
                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badgeClass ?>">
                 <?= htmlspecialchars($displayStatus) ?>
                </span>
              </td>

        <td class="px-4 py-3">
          <?= !empty($row['application_date'])
              ? date('F d, Y', strtotime($row['application_date']))
              : '—'
          ?>
        </td>

              <td class="px-4 py-3 text-center">
                <a href="view_application.php?id=<?= (int)$row['application_id'] ?>"
                   class="text-gray-600 font-medium hover:underline inline-flex items-center gap-1">
                  <i class="fas fa-eye"></i> View
                </a>
              </td>
            </tr>

          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="4" class="py-10 text-center text-gray-500">
              You have no applications yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

  </div>

</main>

<!-- SIDEBAR TOGGLE SCRIPT -->
<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const body = document.getElementById('pageBody');

  const isExpanded = sidebar.classList.contains('w-64');

  if (isExpanded) {
    sidebar.classList.remove('w-64');
    sidebar.classList.add('w-16');
    body.classList.remove('pl-64');
    body.classList.add('pl-16');

    document.querySelectorAll('.sidebar-text').forEach(el => {
      el.classList.add('hidden');
    });
  } else {
    sidebar.classList.remove('w-16');
    sidebar.classList.add('w-64');
    body.classList.remove('pl-16');
    body.classList.add('pl-64');

    document.querySelectorAll('.sidebar-text').forEach(el => {
      el.classList.remove('hidden');
    });
  }
}
</script>

</body>
</html>
