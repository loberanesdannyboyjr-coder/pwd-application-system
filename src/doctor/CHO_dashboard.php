<?php
/** CHO Dashboard - displays statistics and charts based on workflow status */
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paths.php';

// Auth check
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'] ?? '', ['doctor','CHO','ADMIN'])) {
    header('Location: ' . APP_BASE_URL . '/backend/auth/login.php');
    exit;
}

$username = $_SESSION['username'] ?? 'User';

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE);
}

/* ===============================
   DASHBOARD STATISTICS
   =============================== */

$stats = [
    'pwds'    => 0,
    'new'     => 0,
    'renew'   => 0,
    'lost_id' => 0
];

// Official PWD members (FINAL APPROVAL + PWD NUMBER)
$pwdRes = pg_query($conn, "
    SELECT COUNT(*)
    FROM application a
    JOIN applicant ap ON ap.applicant_id = a.applicant_id
    WHERE a.workflow_status = 'approved_final'
      AND ap.pwd_number IS NOT NULL
");
if ($pwdRes) {
    $stats['pwds'] = (int) pg_fetch_result($pwdRes, 0, 0);
}

// Workflow filter visible to CHO
$workflowStatuses = ['cho_review','cho_accepted','approved_final'];
$workflowFilter = "('" . implode("','", $workflowStatuses) . "')";

// New applications (CHO side only)
$newRes = pg_query($conn, "
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text) = 'new'
      AND workflow_status IN $workflowFilter
");
if ($newRes) {
    $stats['new'] = (int) pg_fetch_result($newRes, 0, 0);
}

// Renewal applications
$renewRes = pg_query($conn, "
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text) = 'renewal'
      AND workflow_status IN $workflowFilter
");
if ($renewRes) {
    $stats['renew'] = (int) pg_fetch_result($renewRes, 0, 0);
}

// Lost ID applications
$lostRes = pg_query($conn, "
    SELECT COUNT(*)
    FROM application
    WHERE LOWER(application_type::text) = 'lost id'
      AND workflow_status IN $workflowFilter
");
if ($lostRes) {
    $stats['lost_id'] = (int) pg_fetch_result($lostRes, 0, 0);
}

/* ===============================
   CHART DATA (LAST 12 MONTHS)
   =============================== */

$chartData = [
    'new'     => array_fill(0, 12, 0),
    'renew'   => array_fill(0, 12, 0),
    'lost_id' => array_fill(0, 12, 0)
];
$monthLabels = [];

for ($i = 11; $i >= 0; $i--) {
    $date = new DateTime();
    $date->modify("-$i months");

    $monthLabels[] = $date->format('M Y');
    $monthStart = $date->format('Y-m-01');
    $monthEnd   = $date->format('Y-m-t');

    $idx = 11 - $i;

    // NEW
    $res = pg_query($conn, "
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text) = 'new'
          AND workflow_status IN $workflowFilter
          AND application_date BETWEEN '$monthStart' AND '$monthEnd'
    ");
    if ($res) $chartData['new'][$idx] = (int) pg_fetch_result($res, 0, 0);

    // RENEW
    $res = pg_query($conn, "
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text) = 'renewal'
          AND workflow_status IN $workflowFilter
          AND application_date BETWEEN '$monthStart' AND '$monthEnd'
    ");
    if ($res) $chartData['renew'][$idx] = (int) pg_fetch_result($res, 0, 0);

    // LOST ID
    $res = pg_query($conn, "
        SELECT COUNT(*)
        FROM application
        WHERE LOWER(application_type::text) = 'lost id'
          AND workflow_status IN $workflowFilter
          AND application_date BETWEEN '$monthStart' AND '$monthEnd'
    ");
    if ($res) $chartData['lost_id'][$idx] = (int) pg_fetch_result($res, 0, 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CHO Dashboard</title>

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
        <img src="../../assets/pictures/white.png" width="45">
        <img src="../../assets/pictures/CHO logo.png" width="45">
        <h4>CHO</h4>
    </div>
    <hr>

    <a href="CHO_dashboard.php" class="active">
        <i class="fas fa-chart-line me-2"></i>Dashboard
    </a>

    <a href="members.php">
        <i class="fas fa-wheelchair me-2"></i>Members
    </a>

    <a href="applications.php">
        <i class="fas fa-users me-2"></i>Applications
    </a>

    <div class="sidebar-item">
        <div class="toggle-btn d-flex justify-content-between align-items-center">
            <span><i class="fas fa-folder me-2"></i>Manage Applications</span>
            <i class="fas fa-chevron-down chevron-icon"></i>
        </div>
        <div class="submenu">
            <a href="accepted.php"><i class="fas fa-user-check me-2"></i>Accepted</a>
            <a href="pending.php"><i class="fas fa-hourglass-half me-2"></i>Pending</a>
            <a href="denied.php"><i class="fas fa-user-times me-2"></i>Denied</a>
        </div>
    </div>

    <a href="logout.php">
        <i class="fas fa-sign-out-alt me-2"></i>Logout
    </a>
</div>

<div class="main">

<div class="topbar d-flex justify-content-between align-items-center">
    <div class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
    <div class="d-flex align-items-center">
        <strong><?= h($username) ?></strong>
        <i class="fas fa-user-circle ms-3" style="font-size:2.5rem"></i>
    </div>
</div>

<div class="cards">
    <div class="card-stat"><small>PWDs</small><h3><?= $stats['pwds'] ?></h3></div>
    <div class="card-stat"><small>NEW</small><h3><?= $stats['new'] ?></h3></div>
    <div class="card-stat"><small>RENEW</small><h3><?= $stats['renew'] ?></h3></div>
    <div class="card-stat"><small>LOST ID</small><h3><?= $stats['lost_id'] ?></h3></div>
</div>

<div class="chart-container">
    <canvas id="statsChart"></canvas>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx = document.getElementById('statsChart');
    ctx.height = 460;

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [
          {
            label: 'New Applications',
            data: <?= json_encode($chartData['new']) ?>,
            backgroundColor: 'rgba(66, 135, 245, 0.3)',
            borderColor: '#4287f5',
            borderWidth: 2,
            pointBackgroundColor: '#4287f5',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            fill: true,
            lineTension: 0.3
          },
          {
            label: 'Renew Applications',
            data: <?= json_encode($chartData['renew']) ?>,
            backgroundColor: 'rgba(102, 51, 255, 0.3)',
            borderColor: '#6633ff',
            borderWidth: 2,
            pointBackgroundColor: '#6633ff',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            fill: true,
            lineTension: 0.3
          },
          {
            label: 'Lost ID Applications',
            data: <?= json_encode($chartData['lost_id']) ?>,
            backgroundColor: 'rgba(255, 99, 132, 0.3)',
            borderColor: '#FF6384',
            borderWidth: 2,
            pointBackgroundColor: '#FF6384',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            fill: true,
            lineTension: 0.3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: '#ddd',
            },
            ticks: {
              font: {
                size: 12,
              }
            }
          },
          x: {
            grid: {
              color: '#ddd',
            },
            ticks: {
              font: {
                size: 12,
              }
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              font: {
                size: 14,
              },
              color: '#333'
            }
          },
          tooltip: {
            backgroundColor: '#444',
            titleColor: '#fff',
            bodyColor: '#fff'
          }
        }
      }
    });
  </script>


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
    .rotate {
      transform: rotate(180deg);
      transition: transform 0.3s ease;
    }
  </style>

</body>

</html>
