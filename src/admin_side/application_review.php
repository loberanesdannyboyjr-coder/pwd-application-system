<?php
// src/admin_side/application_review.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';


/* ------------------ AUTH ------------------ */
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
      header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

/* ------------------ DELETE APPLICATION ------------------ */
if (isset($_GET['delete_app'])) {

    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        die("Unauthorized access.");
    }

    $app_id = (int) $_GET['delete_app'];

    pg_query_params($conn,
        "DELETE FROM documentrequirements WHERE application_id = $1",
        [$app_id]
    );

    pg_query_params($conn,
        "DELETE FROM application WHERE application_id = $1",
        [$app_id]
    );

    header("Location: application_review.php");
    exit;
}

// --- Admin auth: prefer boolean flag set during login ---
$role = $_SESSION['role'] ?? '';

/* ---------- Pagination & filter inputs ---------- */
$limit = 20;
$page  = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$search = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? 'All'));
$barangay = trim((string)($_GET['barangay'] ?? ''));

/* ---------- WHERE builder (using pg_query_params) ---------- */
$where = [];
$params = [];
$paramIndex = 1;

if ($status !== '' && $status !== 'All') {

    $workflowMap = [
        'FOR PDAO REVIEW' => 'pdao_review',
        'FOR CHO REVIEW'  => 'cho_review',
        'CHO APPROVED'    => 'cho_approved',
        'PDAO APPROVED'   => 'pdao_approved',
        'DISAPPROVED'     => 'rejected',
    ];

    if (isset($workflowMap[$status])) {
        $where[] = "a.workflow_status = $" . $paramIndex++;
        $params[] = $workflowMap[$status];
    }
}


if ($search !== '') {

    $where[] = "(
        COALESCE(d.data->>'first_name', ap.first_name) ILIKE $" . $paramIndex . " OR
        COALESCE(d.data->>'last_name', ap.last_name) ILIKE $" . $paramIndex . " OR
        (
          COALESCE(d.data->>'first_name', ap.first_name) || ' ' ||
          COALESCE(d.data->>'last_name', ap.last_name)
        ) ILIKE $" . $paramIndex . "
    )";

    $params[] = '%' . $search . '%';
    $paramIndex++;
}

if ($barangay !== '') {
    $where[] = "ap.barangay ILIKE $" . $paramIndex++;
    $params[] = '%' . $barangay . '%';
}

$where[] = "(a.workflow_status = 'pdao_review' OR a.workflow_status = 'cho_review')";

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/* ---------- Count (safe) ---------- */
$countSql = "
    SELECT COUNT(*) AS total
    FROM application a
    JOIN applicant ap ON a.applicant_id = ap.applicant_id
    $whereSql
";
$countRes = @pg_query_params($conn, $countSql, $params);
$totalRows = 0;
if ($countRes !== false) {
    $row = pg_fetch_assoc($countRes);
    $totalRows = $row ? intval($row['total']) : 0;
}

/* ---------- Fetch rows (add limit/offset params) ---------- */
$fetchSql = "
SELECT
  a.application_id,
  a.application_type,
  a.application_date,
  a.workflow_status,

  COALESCE(d.data->>'first_name', ap.first_name)   AS first_name,
  COALESCE(d.data->>'middle_name', ap.middle_name) AS middle_name,
  COALESCE(d.data->>'last_name', ap.last_name)     AS last_name

FROM application a
JOIN applicant ap ON a.applicant_id = ap.applicant_id
LEFT JOIN application_draft d
  ON d.application_id = a.application_id
 AND d.step = 1
  $whereSql
  ORDER BY a.application_date DESC NULLS LAST
  LIMIT $" . $paramIndex++ . " OFFSET $" . $paramIndex++ . "
";
$params_fetch = $params;
$params_fetch[] = $limit;
$params_fetch[] = $offset;

$result = @pg_query_params($conn, $fetchSql, $params_fetch);
$applications = [];
$fetch_error = false;
if ($result === false) {
    $fetch_error = true;
} else {
    $applications = pg_fetch_all($result) ?: [];
}

$totalPages = max(1, (int)ceil(max(0, $totalRows) / $limit));

/* ---------- Helpers ---------- */
function buildQuery(array $overrides = []) {
    $base = [
        'q' => $_GET['q'] ?? '',
        'status' => $_GET['status'] ?? 'All',
        'barangay' => $_GET['barangay'] ?? '',
        'page' => $_GET['page'] ?? 1
    ];
    return http_build_query(array_merge($base, $overrides));
}

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
$curPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Application Review | PDAO</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

      <style>
      body {
    background:#f6f7f9;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto;
}

/* MAIN CONTENT SHIFT */
#mainContent {
    margin-left: 64px;
    transition: 0.3s;
}

body.sidebar-expanded #mainContent {
    margin-left: 256px;
}

/* CARD */
.card.table-card { 
    border-radius:12px; 
    box-shadow:0 10px 25px rgba(0,0,0,0.06); 
    border: none;
}

/* TABLE */
.table {
    border-collapse: separate;
    border-spacing: 0;
    
}

/* HEADER */
.table thead th {  
    background: #4b5563; 
    color: #fff; 
    border: 0; 
    padding: 14px; 
    font-weight: 600;
}



/* Rounded header */
.table thead th:first-child {
    border-top-left-radius: 12px;
}

.table thead th:last-child {
    border-top-right-radius: 12px;
}

/* ROWS */
.table tbody td {
    padding: 12px;
    border-top: 1px solid #e5e7eb;
    vertical-align: middle; /* 🔥 important fix */
}

/* ACTION BUTTON WRAPPER */
.action-buttons{
    display:flex;
    align-items:center;
    justify-content:center; /* 🔥 CENTER FIX */
    gap:8px;
}

/* BASE SOFT BUTTON */
.btn-soft{
    display:inline-flex;
    align-items:center;
    gap:6px;

    padding:6px 12px;
    font-size:12.5px;
    font-weight:500;

    border-radius:10px;
    text-decoration:none;

    border:1px solid transparent;
    transition: all 0.2s ease;
}

/* VIEW (SOFT BLUE) */
.btn-view{
    background:#e7f0ff;
    color:#3b82f6;
}

.btn-view:hover{
    background:#dbeafe;
    color:#2563eb;
    box-shadow:0 2px 6px rgba(59,130,246,0.2);
}

/* DELETE (SOFT RED) */
.btn-delete{
    background:#fde8e8;
    color:#ef4444;
}

.btn-delete:hover{
    background:#fbd5d5;
    color:#dc2626;
    box-shadow:0 2px 6px rgba(239,68,68,0.2);
}
            </style>

</head>
<body>

<?php include __DIR__ . '/../../includes/pdao_sidebar.php'; ?>  

  <!-- MAIN AREA -->
  <div id="mainContent" class="ml-16 p-6 transition-all duration-300">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>Application Review</h2>
      <div>Hello, <?= e($_SESSION['username'] ?? 'Admin') ?></div>
    </div>

    <div class="card table-card">
      <div class="card-body">

              <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success mb-3">
                Application deleted successfully.
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <form method="get" class="d-flex gap-2 align-items-center search-row" style="margin:0;">
            <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>"
                  class="form-control" placeholder="Search applicant name..." style="width:320px;">

                    <?php
                $workflowMap = [
            'All'               => 'All',
            'FOR PDAO REVIEW'   => 'pdao_review',
            'FOR CHO REVIEW'    => 'cho_review',
        ];
        ?>

        <select name="status" class="form-select" style="width:200px;">
            <?php foreach ($workflowMap as $label => $value): ?>
                <option value="<?= e($label) ?>" <?= ($status === $label) ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>


            <button class="btn btn-primary">Filter</button>
          </form>

         
        </div>

        <?php if ($fetch_error): ?>
          <div class="alert alert-danger">Failed to fetch applications. Check server logs for details.</div>
        <?php else: ?>

          <div class="mb-2"><strong>Total results:</strong> <?= number_format($totalRows) ?></div>

          <?php if (empty($applications)): ?>
            <div class="alert alert-info">No applications found.</div>
          <?php else: ?>

            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Applicant Name</th>
                    <th>Application Type</th>
                    <th>Date Submitted</th>
                    <th style="width:180px;">Action</th>
                  </tr>
                </thead>

                <tbody>
                <?php foreach ($applications as $row):
                  $fullName = e(trim("{$row['last_name']}, {$row['first_name']} {$row['middle_name']}"));
                  $applicationType = e(ucfirst((string)($row['application_type'] ?? '')));
                  $date = $row['application_date'] ?? null;
                  $dateFmt = $date ? date('M d, Y', strtotime($date)) : '';
                  $viewUrl = rtrim(APP_BASE_URL, '/') . '/src/admin_side/view_a.php?id=' . urlencode($row['application_id']);
                ?>
                  <tr>
                    <td><?= $fullName ?></td>
                    <td><?= $applicationType ?></td>
                    <td><?= $dateFmt ?></td>
                    <td>
                      
                    <div class="action-buttons">

                        <!-- VIEW -->
                        <a href="<?= $viewUrl ?>" class="btn-soft btn-view">
                            <i class="fas fa-eye"></i>
                            <span>View</span>
                        </a>

                        <!-- DELETE -->
                        <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                            <a href="#"
                          class="btn-soft btn-delete delete-btn"
                          data-id="<?= $row['application_id'] ?>">
                          <i class="fas fa-trash"></i>
                          <span>Delete</span>
                        </a>
                        <?php endif; ?>

                    </div>

                </td>

                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <nav aria-label="pagination" class="mt-3">
              <ul class="pagination">
              <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?<?= buildQuery(['page'=>$page-1]) ?>">Previous</a></li>
              <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Previous</span></li>
              <?php endif; ?>

              <?php for ($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
                <li class="page-item <?= $p==$page?'active':'' ?>">
                  <a class="page-link" href="?<?= buildQuery(['page'=>$p]) ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>

              <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="?<?= buildQuery(['page'=>$page+1]) ?>">Next</a></li>
              <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Next</span></li>
              <?php endif; ?>
              </ul>
            </nav>

          <?php endif; ?>

        <?php endif; ?>

      </div>
    </div>
  </main>
   </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  

  
   <script>
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();

        const appId = this.getAttribute('data-id');

        Swal.fire({
            title: 'Delete Application?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel',
            background: '#fff',
            borderRadius: '10px'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '?delete_app=' + appId;
            }
        });
    });
});
</script>

<script>
<?php if (isset($_GET['deleted'])): ?>
Swal.fire({
    icon: 'success',
    title: 'Deleted!',
    text: 'Application has been removed.',
    timer: 2000,
    showConfirmButton: false
});
<?php endif; ?>
</script>


</body>
</html>
