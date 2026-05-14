<?php
/** CHO Applications — Inbox (Forwarded by PDAO) */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ===============================
   AUTH CHECK
   =============================== */
$role = $_SESSION['role'] ?? '';

if (
    empty($_SESSION['username']) ||
    !in_array($role, ['doctor', 'CHO', 'ADMIN'], true)
) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';

/* ===============================
   FILTERS & PAGINATION
   =============================== */
$search = trim($_GET['q'] ?? '');
$barangayFilter = trim($_GET['barangay'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$dbErr = null;
$params = [];
$conds = [];

/* ===============================
   BASE QUERY (CHO INBOX)
   =============================== */
$sql = "
SELECT
    a.application_id,
    a.applicant_id,
    a.application_type,
    a.application_date,
    a.workflow_status AS status,
    a.updated_at AS forwarded_at,
    COALESCE(d.data->>'barangay', ap.barangay) AS barangay,
    COALESCE(d.data->>'first_name', ap.first_name) AS first_name,
    COALESCE(d.data->>'middle_name', ap.middle_name) AS middle_name,
    COALESCE(d.data->>'last_name', ap.last_name) AS last_name
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id AND d.step = 1
WHERE a.workflow_status = 'cho_review'
";


/* ===============================
   SEARCH FILTER
   =============================== */
if ($search !== '') {
    $params[] = '%' . str_replace('%', '\\%', $search) . '%';
    $idx = '$' . count($params);
    $conds[] = "(
        COALESCE(d.data->>'last_name', ap.last_name) ILIKE {$idx}
        OR COALESCE(d.data->>'first_name', ap.first_name) ILIKE {$idx}
        OR COALESCE(d.data->>'barangay', ap.barangay) ILIKE {$idx}
        OR ap.pwd_number ILIKE {$idx}
    )";
}

/* ===============================
   BARANGAY FILTER
   =============================== */
if ($barangayFilter !== '') {
    $params[] = $barangayFilter;
    $idx = '$' . count($params);
    $conds[] = "COALESCE(d.data->>'barangay', ap.barangay) = {$idx}";
}

if ($conds) {
    $sql .= ' AND ' . implode(' AND ', $conds);
}

/* ===============================
   COUNT QUERY (for pagination)
   =============================== */
$countSql = "
SELECT COUNT(*)
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id AND d.step = 1
WHERE a.workflow_status = 'cho_review'
";



if ($conds) {
    $countSql .= ' AND ' . implode(' AND ', $conds);
}

$countRes = $params
    ? pg_query_params($conn, $countSql, $params)
    : pg_query($conn, $countSql);

$total = $countRes ? (int) pg_fetch_result($countRes, 0, 0) : 0;
$totalPages = max(1, ceil($total / $limit));

/* ===============================
   FINAL ORDERING (FIXED)
   =============================== */
$sql .= "
ORDER BY a.updated_at DESC, a.application_id DESC
LIMIT {$limit} OFFSET {$offset}
";

$res = $params
    ? pg_query_params($conn, $sql, $params)
    : pg_query($conn, $sql);

if (!$res) {
    $dbErr = pg_last_error($conn);
}

$rows = [];
while ($res && $r = pg_fetch_assoc($res)) {
    $rows[] = $r;
}

/* ===============================
   BARANGAY DROPDOWN
   =============================== */
$bq = "
SELECT DISTINCT COALESCE(d.data->>'barangay', ap.barangay) AS barangay
FROM application a
JOIN applicant ap ON ap.applicant_id = a.applicant_id
LEFT JOIN application_draft d
    ON d.application_id = a.application_id AND d.step = 1
WHERE a.workflow_status = 'cho_review'
ORDER BY barangay
";

$barangays = [];

$br = pg_query($conn, $bq);
while ($br && $b = pg_fetch_assoc($br)) {
    $barangays[] = $b['barangay'];
}

/* ===============================
   STATUS BADGE (CHO)
   =============================== */
function getStatusBadge($status) {

    switch (strtolower($status)) {

        case 'cho_review':
            return [
                'class' => 'bg-primary',
                'text'  => 'FOR CHO REVIEW'
            ];

        case 'cho_approved':
            return [
                'class' => 'bg-info text-dark',
                'text'  => 'MEDICALLY ACCEPTED'
            ];

        case 'pdao_approved':
            return [
                'class' => 'bg-success',
                'text'  => 'OFFICIAL PWD'
            ];

        case 'rejected':
            return [
                'class' => 'bg-danger',
                'text'  => 'DISAPPROVED'
            ];

        default:
            return [
                'class' => 'bg-secondary',
                'text'  => strtoupper($status)
            ];
    }
}
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>CHO Applications</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="../../assets/css/global/base.css">
        <link rel="stylesheet" href="../../assets/css/global/layout.css">
        <link rel="stylesheet" href="../../assets/css/global/component.css">
    </head>
    <body>

<div class="layout">

<?php include __DIR__ . '/../../includes/cho_sidebar.php'; ?>

<div class="main-content">

<div class="container-fluid">
    <!-- CARD -->
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-body p-4">

        <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            Medical assessment saved successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

            <!-- SEARCH ROW -->
            <form method="get" class="d-flex gap-2 align-items-center mb-3">
                <input type="text"
                       name="q"
                       value="<?= h($search) ?>"
                       class="form-control"
                       placeholder="Search applicant name..."
                       style="max-width:320px;">

                <select name="barangay"
                        class="form-select"
                        style="max-width:150px;">
                    <option value="">All</option>
                    <?php foreach ($barangays as $b): ?>
                        <option value="<?= h($b) ?>"
                            <?= $b === $barangayFilter ? 'selected' : '' ?>>
                            <?= h($b) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-primary px-4">Filter</button>
            </form>

            <!-- TOTAL -->
            <div class="mb-3">
                <strong>Total results:</strong> <?= number_format($total) ?>
            </div>

            <!-- TABLE -->
            <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                    <thead style="background:#4b5563; color:#fff;">
                        <tr>
                            <th>Applicant Name</th>
                            <th>Application Type</th>
                            <th>Date Submitted</th>
                            <th style="width:180px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $fullname = trim(
                            ($r['last_name'] ?? '') . ', ' .
                            ($r['first_name'] ?? '') . ' ' .
                            ($r['middle_name'] ?? '')
                        );

                        $dateFmt = $r['application_date']
                            ? date('M d, Y', strtotime($r['application_date']))
                            : '';

                        $viewUrl = 'view_applicant.php?id=' . urlencode($r['application_id']);
                    ?>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td><?= h($fullname ?: 'Unknown') ?></td>
                            <td><?= h(ucfirst($r['application_type'] ?? '')) ?></td>
                            <td><?= h($dateFmt) ?></td>
                            <td>
                                <a href="<?= h($viewUrl) ?>"
                                   style="color:#374151; font-weight:500; text-decoration:none;">
                                    <i class="fas fa-eye me-1"></i> View Application
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>
</div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>" class="btn btn-sm btn-outline-secondary">&lt; Previous</a>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>&lt; Previous</button>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&barangay=<?= urlencode($barangayFilter) ?>" class="btn btn-sm btn-outline-secondary">Next &gt;</a>
                <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>Next &gt;</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        </div> 
</div> 

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

        <script src="../../assets/js/sidebar.js">
            const sidebar = document.getElementById("sidebar");

            sidebar.addEventListener("mouseenter", () => {

                sidebar.classList.remove("w-16");
                sidebar.classList.add("w-64");

                document.body.classList.add("sidebar-expanded");

                document.querySelectorAll(".sidebar-text").forEach(el=>{
                    el.classList.remove("hidden");
                });

            });

            sidebar.addEventListener("mouseleave", () => {

                sidebar.classList.remove("w-64");
                sidebar.classList.add("w-16");

                document.body.classList.remove("sidebar-expanded");

                document.querySelectorAll(".sidebar-text").forEach(el=>{
                    el.classList.add("hidden");
                });

            });

        </script>
        <style>

           /* Default (expanded sidebar) */
.main-content{
    flex:1;
    min-height:100vh;
    background:#f5f7fb;
    padding:20px;

    margin-left:64px; /* collapsed sidebar */
    transition: margin-left .3s ease;
}

body.sidebar-expanded .main-content{
    margin-left:256px; /* expanded sidebar */
}
            .rotate { transform: rotate(180deg); transition: transform 0.3s ease; }
            .pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
            
            .section-header {
            background-color: #4b5563;
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
                    }

                .table {
                    border-radius: 8px;
                    overflow: hidden;
                }
                .table thead tr.table-header {
                    background-color: #4b5563 !important;
                }

                .table thead tr.table-header th {
                    background-color: #4b5563 !important;
                    color: #ffffff !important;
                    font-weight: 600;
                    padding: 14px;
                    border: none;
                }

            .table-header th {
                font-weight: 600;
                padding: 16px;
                border: none;
            }

                        .card.table-card {
                border-radius: 10px;
                box-shadow: 0 8px 18px rgba(0,0,0,0.06);
                border: none;
            }

            .table thead th {
                background: #4b5563;
                color: #fff;
                border: 0;
                padding: 14px;
                font-weight: 400;
            }

            .table tbody td {
                padding: 10px;
                border-top: 1px solid #e5e7eb;
            }

            .table-hover tbody tr:hover {
                background-color: #f9fafb;
            }

            .view-link {
                color: #374151;
                font-weight: 500;
                text-decoration: none;
                display: inline-flex;       /* keeps icon + text aligned */
                align-items: center;
                gap: 6px;
                white-space: nowrap;        /* 🚀 PREVENTS WRAPPING */
            }

            .view-link:hover {
                color: #111827;
                text-decoration: none;
            }

            .badge {
                min-width: 110px;
            }
                    </style>

                    
                </body>
                </html>
