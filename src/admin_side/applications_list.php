<?php
// src/admin_side/applications_list.php
// PDAO Applications — For Review (submitted + pdao_review)

session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'])) {
        header('Location: ' . ADMIN_BASE . '/signin.php');
    exit;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ---------------- STATUS BADGE (PDAO) ---------------- */
function pdaoBadge(string $status): array {
    return match ($status) {
        'submitted'   => ['text' => 'SUBMITTED',    'class' => 'bg-secondary'],
        'pdao_review' => ['text' => 'PDAO REVIEW',  'class' => 'bg-warning'],
        default       => ['text' => strtoupper(str_replace('_',' ', $status)), 'class' => 'bg-dark'],
    };
}

/* ---------------- FETCH PDAO QUEUE ---------------- */
$sql = "
    SELECT
        a.application_id,
        a.application_number,
        a.application_date,
        a.workflow_status,
        ap.first_name,
        ap.middle_name,
        ap.last_name
    FROM application a
    JOIN applicant ap ON ap.applicant_id = a.applicant_id
    WHERE a.workflow_status IN ('submitted','pdao_review')
    ORDER BY a.application_date DESC
";

$res = pg_query($conn, $sql);
$applications = [];
if ($res) {
    while ($r = pg_fetch_assoc($res)) {
        $applications[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PDAO — Applications for Review</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/global/base.css">
    <link rel="stylesheet" href="../../assets/css/global/layout.css">
    <link rel="stylesheet" href="../../assets/css/global/component.css">
</head>
<body>

<!-- Sidebar -->
<?php include __DIR__ . '/partials/pdao_sidebar.php'; ?>

<div class="main">

    <div class="topbar d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Applications — For PDAO Review</h5>
        <strong><?= h($_SESSION['username']) ?></strong>
    </div>

    <div class="card mt-3">
        <div class="card-body">

            <?php if (empty($applications)): ?>
                <div class="alert alert-info mb-0">
                    No applications awaiting PDAO review.
                </div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Application #</th>
                                <th>Applicant</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th width="140">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($applications as $a): 
                            $fullname = trim(
                                ($a['first_name'] ?? '') . ' ' .
                                ($a['middle_name'] ?? '') . ' ' .
                                ($a['last_name'] ?? '')
                            );
                            $badge = pdaoBadge($a['workflow_status']);
                        ?>
                            <tr>
                                <td><?= h($a['application_number']) ?></td>
                                <td><?= h($fullname ?: 'Unknown') ?></td>
                                <td><?= h(date('M d, Y', strtotime($a['application_date']))) ?></td>
                                <td>
                                    <span class="badge <?= $badge['class'] ?>">
                                        <?= $badge['text'] ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_a.php?id=<?= (int)$a['application_id'] ?>"
                                       class="btn btn-sm btn-primary">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
