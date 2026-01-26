<?php
/** Displays list of PWD members with avatar, age, search, and download functionality. */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE); }

// Auth check
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['doctor', 'CHO', 'ADMIN'])) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';

// Search params
$search = trim($_GET['q'] ?? $_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query for approved PWD members - get data from draft where available
$baseSql = "
    SELECT
        ap.applicant_id,
        ap.pwd_number,
        COALESCE(d.data->>'first_name', ap.first_name, '') AS first_name,
        COALESCE(d.data->>'middle_name', ap.middle_name, '') AS middle_name,
        COALESCE(d.data->>'last_name', ap.last_name, '') AS last_name,
        COALESCE(d.data->>'barangay', ap.barangay, '') AS barangay,
        COALESCE((d.data->>'birthdate')::date, ap.birthdate) AS birthdate,
        COALESCE(d.data->>'sex', ap.sex::text, '') AS sex,
        a.application_id,
        a.status
    FROM applicant ap
    JOIN application a ON a.applicant_id = ap.applicant_id
    LEFT JOIN application_draft d ON d.application_id = a.application_id AND d.step = 1
    WHERE a.workflow_status = 'approved_final'
    AND ap.pwd_number IS NOT NULL

";

$params = [];
$conds = [];

if ($search !== '') {
    $s = '%' . str_replace('%', '\\%', $search) . '%';
    $conds[] = "(COALESCE(d.data->>'last_name', ap.last_name) ILIKE $1 OR COALESCE(d.data->>'first_name', ap.first_name) ILIKE $1 OR ap.pwd_number ILIKE $1 OR COALESCE(d.data->>'barangay', ap.barangay) ILIKE $1)";
    $params[] = $s;
}

if (!empty($conds)) {
    $baseSql .= ' AND ' . implode(' AND ', $conds);
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as total FROM applicant ap JOIN application a ON a.applicant_id = ap.applicant_id LEFT JOIN application_draft d ON d.application_id = a.application_id AND d.step = 1 WHERE a.workflow_status = 'approved_final'
  AND ap.pwd_number IS NOT NULL";
if (!empty($conds)) {
    $countSql .= ' AND ' . implode(' AND ', $conds);
}

$countRes = !empty($params) ? @pg_query_params($conn, $countSql, $params) : @pg_query($conn, $countSql);
$total = $countRes ? (int)pg_fetch_result($countRes, 0, 0) : 0;
$totalPages = max(1, (int)ceil($total / $limit));

// Add pagination
$baseSql .= " ORDER BY ap.last_name ASC, ap.first_name ASC LIMIT $limit OFFSET $offset";

$res = !empty($params) ? @pg_query_params($conn, $baseSql, $params) : @pg_query($conn, $baseSql);

$rows = [];
$dbErr = null;
if ($res === false) {
    $dbErr = pg_last_error($conn);
} else {
    while ($r = pg_fetch_assoc($res)) $rows[] = $r;
}

// Calculate age from date of birth
function calculateAge($dob) {
    if (empty($dob)) return null;
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    return $birthDate->diff($today)->y;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHO PWD Members</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/global/base.css">
    <link rel="stylesheet" href="../../assets/css/global/layout.css">
    <link rel="stylesheet" href="../../assets/css/global/component.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../../assets/pictures/white.png" alt="logo" width="45">
            <img src="../../assets/pictures/CHO logo.png" alt="logo 2" width="45">
            <h4>CHO</h4>
        </div>
        <hr>

        <a href="CHO_dashboard.php"><i class="fas fa-chart-line me-2"></i><span>Dashboard</span></a>
        <a href="members.php" class="active"><i class="fas fa-wheelchair me-2"></i><span>Members</span></a>
        <a href="applications.php"><i class="fas fa-users me-2"></i><span>Application</span></a>

        <div class="sidebar-item">
            <div class="toggle-btn d-flex justify-content-between align-items-center">
                <span class="no-wrap d-flex align-items-center">
                    <i class="fas fa-folder me-2"></i><span>Manage Applications</span>
                </span>
                <i class="fas fa-chevron-down chevron-icon"></i>
            </div>
            <div class="submenu">
                <a href="accepted.php" class="submenu-link d-flex align-items-center ps-4" style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-user-check"></i></span>
                    <span class="ms-2">Accepted</span>
                </a>
                <a href="pending.php" class="submenu-link d-flex align-items-center ps-4" style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-hourglass-half"></i></span>
                    <span class="ms-2">Pending</span>
                </a>
                <a href="denied.php" class="submenu-link d-flex align-items-center ps-4" style="padding-top: 3px; padding-bottom: 3px; margin: 5px 0;">
                    <span class="icon" style="width: 18px;"><i class="fas fa-user-times"></i></span>
                    <span class="ms-2">Denied</span>
                </a>
            </div>
        </div>

        <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i><span>Logout</span></a>
    </div>

    <div class="main">
        <div class="topbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="toggle-btn" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </div>
            </div>

            <div class="d-flex flex-column align-items-end">
                <div class="d-flex align-items-center ms-3 mt-2 mb-2" style="font-size: 1.4rem;">
                    <strong><?= h($username) ?></strong>
                    <i class="fas fa-user-circle ms-3 me-2 mb-2 mt-2" style="font-size: 2.5rem;"></i>
                </div>
            </div>
        </div>

        <!-- Search row with Download button -->
        <form method="get" class="search-container d-flex align-items-center justify-content-between mt-3">
            <div class="search-bar d-flex align-items-center">
                <label class="me-2">Search:</label>
                <input type="text" name="q" placeholder="Search PWDs..." value="<?= h($search) ?>" class="form-control" style="width: 300px;">
                <button type="submit" class="btn btn-primary ms-2"><i class="fas fa-search"></i></button>
                <?php if ($search !== ''): ?>
                    <a href="members.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i> Clear</a>
                <?php endif; ?>
            </div>

            <a href="download_members.php" class="btn btn-outline-primary">
                <i class="fas fa-download me-1"></i> Download
            </a>
        </form>

        <?php if ($dbErr): ?>
            <div class="alert alert-danger mt-3">Database error: <?= h($dbErr) ?></div>
        <?php endif; ?>

        <div class="section-header">
            <div>List of PWD's</div>
            <div class="d-flex justify-content-between">
                <div class="fw-bold me-4"></div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="p-4 bg-white rounded">
                <p class="text-muted mb-0">No PWD members found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($rows as $r):
                $fullname = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                $age = calculateAge($r['birthdate']);
                $ageText = $age !== null ? $age . ' yrs. old' : 'Age unknown';
                $viewUrl = 'view_a.php?id=' . urlencode($r['application_id']);
                $initials = strtoupper(substr($r['first_name'] ?? 'U', 0, 1) . substr($r['last_name'] ?? '', 0, 1));
            ?>
            <div class="member-list">
                <div class="member-info d-flex align-items-center">
                    <!-- Avatar -->
                    <div class="avatar-circle me-3" style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1rem;">
                        <?= h($initials) ?>
                    </div>
                    <div>
                        <div><b><?= h($fullname ?: 'Unknown') ?></b></div>
                        <div style="font-size: 12px; color: #6b7280;"><?= h($ageText) ?></div>
                    </div>
                </div>

                <a href="<?= h($viewUrl) ?>" class="view-link text-primary text-decoration-none">
                    <i class="fas fa-eye me-1"></i> View Applicant
                </a>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">&lt; Previous</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled>&lt; Previous</button>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-outline-secondary">Next &gt;</a>
            <?php else: ?>
                <button class="btn btn-sm btn-outline-secondary" disabled>Next &gt;</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.sidebar-item .toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const submenu = btn.nextElementSibling;
                const icon = btn.querySelector('.chevron-icon');
                if (submenu) {
                    submenu.style.maxHeight = submenu.style.maxHeight ? null : submenu.scrollHeight + "px";
                }
                if (icon) icon.classList.toggle('rotate');
            });
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const main = document.querySelector('.main');
            sidebar.classList.toggle('closed');
            main.classList.toggle('shifted');
        }
    </script>

    <style>
        .rotate { transform: rotate(180deg); transition: transform 0.3s ease; }
        .pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
        .member-list {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: white;
            border-bottom: 1px solid #eee;
        }
        .member-list:hover {
            background: #f9fafb;
        }
    </style>
</body>
</html>
