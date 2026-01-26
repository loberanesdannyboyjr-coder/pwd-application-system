<?php
// src/admin_side/application_review.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

// --- Admin auth: prefer boolean flag set during login ---
$role = strtoupper($_SESSION['role'] ?? $_SESSION['user_role'] ?? '');

if (!isset($_SESSION['username']) || !in_array($role, ['PDAO','ADMIN'], true)) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}



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

if ($status !== '' && strtolower($status) !== 'all') {
    $where[] = "a.status = $" . $paramIndex++;
    $params[] = $status;
}

if ($search !== '') {
    $where[] = "(
        ap.first_name ILIKE $" . $paramIndex++ . " OR
        ap.last_name ILIKE $" . $paramIndex++ . " OR
        (ap.first_name || ' ' || ap.last_name) ILIKE $" . $paramIndex++ . "
    )";
    $wild = '%' . $search . '%';
    $params[] = $wild;
    $params[] = $wild;
    $params[] = $wild;
}

if ($barangay !== '') {
    $where[] = "ap.barangay ILIKE $" . $paramIndex++;
    $params[] = '%' . $barangay . '%';
}

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
    a.status,
    ap.first_name,
    ap.last_name,
    ap.middle_name
  FROM application a
  JOIN applicant ap ON a.applicant_id = ap.applicant_id
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
    body { background:#f6f7f9; font-family: system-ui, -apple-system, "Segoe UI", Roboto; }
    .app-wrapper { display:flex; min-height:100vh; }
    .sidebar { width:250px; background: linear-gradient(180deg,#1f2b6f,#2c54a6); color:#fff; padding:20px 16px; }
    .sidebar .logo { display:flex; align-items:center; gap:10px; }
    .sidebar .logo h4 { margin:0; }
    .sidebar .nav-link, .submenu-link { color:#eef3ff; text-decoration:none; padding:10px; display:block; border-radius:6px; }
    .sidebar .nav-link:hover, .submenu-link:hover, .sidebar .nav-link.active, .submenu-link.active { background: rgba(255,255,255,0.10); color:#fff; }
    .submenu { max-height:0; overflow:hidden; transition:max-height 260ms cubic-bezier(.2,.8,.2,1); }
    .sidebar-item.open .submenu { padding-top:6px; padding-bottom:6px; }
    .chevron-icon { transition:transform .24s ease; }
    .chevron-icon.rotate { transform:rotate(180deg); }
    .main { flex:1; padding:28px; }
    .card.table-card { border-radius:8px; box-shadow:0 8px 18px rgba(0,0,0,0.06); }
    .table thead th { background:#2d6be6; color:#fff; border:0; }
    .view-link { color:#2f5ec8; text-decoration:none; white-space:nowrap; display:inline-flex; align-items:center; gap:6px; font-weight:500; }
    .view-link:hover { color:#1e47a0; text-decoration:none; }
  </style>
</head>
<body>
  <div class="app-wrapper">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="logo">
      <img src="<?= rtrim(APP_BASE_URL, '/') ?>/assets/pictures/white.png" width="40" alt="logo">
      <h4>PDAO</h4>
    </div>
    <hr>

    <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/dashboard.php' ?>"
      class="nav-link <?= $curPage==='dashboard.php'?'active':'' ?>">
      <i class="fas fa-chart-line me-2"></i> Dashboard
    </a>

    <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/members.php' ?>"
      class="nav-link <?= $curPage==='members.php'?'active':'' ?>">
      <i class="fas fa-users me-2"></i> Members
    </a>

    <div class="sidebar-item">
      <div class="submenu-toggle d-flex justify-content-between align-items-center" tabindex="0">
        <span class="d-flex align-items-center">
          <i class="fas fa-folder me-2"></i> Manage Applications
        </span>
        <i class="fas fa-chevron-down chevron-icon"></i>
      </div>

      <div class="submenu">
        <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/application_review.php' ?>"
          class="submenu-link ps-4 <?= $curPage==='application_review.php'?'active':'' ?>">
          <i class="fas fa-file-alt me-1"></i> Application Review
        </a>

        <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/accepted.php' ?>"
          class="submenu-link ps-4 <?= $curPage==='accepted.php'?'active':'' ?>">
          <i class="fas fa-user-check me-1"></i> Accepted
        </a>

        <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/pending.php' ?>"
          class="submenu-link ps-4 <?= $curPage==='pending.php'?'active':'' ?>">
          <i class="fas fa-hourglass-half me-1"></i> Pending
        </a>

        <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/denied.php' ?>"
          class="submenu-link ps-4 <?= $curPage==='denied.php'?'active':'' ?>">
          <i class="fas fa-user-times me-1"></i> Denied
        </a>
      </div>
    </div>

    <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/logout.php' ?>" class="nav-link">
      <i class="fas fa-sign-out-alt me-2"></i> Logout
    </a>
  </div>

  <!-- MAIN AREA -->
  <main class="main">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><i class="fas fa-file-alt me-2"></i>Application Review</h2>
      <div class="text-muted">
        Hello, <?= e($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Admin') ?>
      </div>
    </div>

    <div class="card table-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <form method="get" class="d-flex gap-2 align-items-center search-row" style="margin:0;">
            <input type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>"
                  class="form-control" placeholder="Search applicant name..." style="width:320px;">

            <select name="status" class="form-select" style="width:160px;">
              <?php foreach (['All','Pending','Accepted','Rejected','Denied'] as $s): ?>
                <option value="<?= e($s) ?>" <?= ($s===$status)?'selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>

            <button class="btn btn-primary">Filter</button>
          </form>

          <a href="<?= rtrim(APP_BASE_URL, '/') . '/src/admin_side/export_applications.php?' . buildQuery() ?>"
            class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-file-csv me-1"></i> Export CSV
          </a>
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
                    <th style="width:140px;">Action</th>
                  </tr>
                </thead>

                <tbody>
                <?php foreach ($applications as $row):
                  $fullName = e(trim("{$row['last_name']}, {$row['first_name']} {$row['middle_name']}"));
                  $applicationType = e(ucfirst((string)($row['application_type'] ?? '')));
                  $date = $row['application_date'] ?? null;
                  $dateFmt = $date ? date('M d, Y \a\t H:i', strtotime($date)) : '';
                  $viewUrl = rtrim(APP_BASE_URL, '/') . '/src/admin_side/view_a.php?id=' . urlencode($row['application_id']);
                ?>
                  <tr>
                    <td><?= $fullName ?></td>
                    <td><?= $applicationType ?></td>
                    <td><?= $dateFmt ?></td>
                    <td>
                      <a href="<?= $viewUrl ?>" class="view-link" style="font-weight:500;">
                        <i class="fas fa-eye"></i>&nbsp;View Application
                      </a>
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

  </div> <!-- wrapper -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('.submenu-toggle');

    function openSub(parent) {
      const submenu = parent.querySelector('.submenu'); if (!submenu) return;
      submenu.style.maxHeight = submenu.scrollHeight + 'px';
      parent.classList.add('open');
      parent.querySelector('.chevron-icon').classList.add('rotate');
    }

    function closeSub(parent) {
      const submenu = parent.querySelector('.submenu'); if (!submenu) return;
      submenu.style.maxHeight = '0px';
      parent.classList.remove('open');
      parent.querySelector('.chevron-icon').classList.remove('rotate');
    }

    toggles.forEach(toggle => {
      toggle.addEventListener('click', () => {
        const parent = toggle.closest('.sidebar-item');
        if (parent.classList.contains('open')) closeSub(parent);
        else {
          document.querySelectorAll('.sidebar-item.open').forEach(p => closeSub(p));
          openSub(parent);
        }
      });
    });

    // auto-open when inside manage apps
    const path = location.pathname.toLowerCase();
    if (path.includes('application_review') || path.includes('/accepted') || path.includes('/pending') || path.includes('/denied')) {
      const ma = document.querySelector('.submenu-toggle');
      if (ma) openSub(ma.closest('.sidebar-item'));
    }
  });
  </script>

</body>
</html>
