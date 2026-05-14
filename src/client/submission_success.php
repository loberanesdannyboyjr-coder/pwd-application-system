<?php
session_start();

/*
|--------------------------------------------------
| SECURITY: allow access only after successful submit
|--------------------------------------------------
*/
if (empty($_SESSION['application_submitted'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Application Submitted</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Google Font: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    body {
      background-color: #f4f6f9;
      font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .success-card {
      max-width: 500px;
      border-radius: 10px;
    }

    .success-card p {
      font-size: 0.95rem;
      line-height: 1.55;
    }
  </style>
</head>

<body>

<!-- HEADER -->
<header class="relative bg-gradient-to-r from-blue-700 to-blue-900 text-white py-3">

  <!-- Burger button -->
  <button
    onclick="toggleSidebar()"
    class="absolute left-6 top-1/2 -translate-y-1/2 text-xl z-50">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Centered Title -->
  <div class="text-center">
    <h1 class="text-xl font-semibold">Application Submitted</h1>
  </div>
</header>

<!-- MAIN CONTENT -->
<div class="container d-flex justify-content-center align-items-center text-center"
     style="min-height: 85vh;">

  <div style="max-width: 600px;">

    <!-- CHECK ICON -->
    <div class="d-flex justify-content-center mb-4">
      <div class="rounded-circle d-flex align-items-center justify-content-center"
           style="width: 110px; height: 110px; background-color: #198754;">
        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60"
             fill="white" class="bi bi-check-lg" viewBox="0 0 16 16">
          <path d="M13.485 1.929a.75.75 0 0 1 .086 1.056l-7.25 9a.75.75 0 0 1-1.108.046L2.52 9.414a.75.75 0 1 1 1.06-1.06l2.09 2.09 6.72-8.34a.75.75 0 0 1 1.056-.086z"/>
        </svg>
      </div>
    </div>

    <!-- TITLE -->
    <h2 class="fw-bold text-success mb-4">
      Your application has successfully been submitted!
    </h2>

    <!-- MESSAGE -->
    <p class="text-muted mb-4" style="font-size: 1.05rem;">
      Thank you for completing your PWD ID application. 
      Please wait for your application status to be updated. You will be notified on the
      <strong>Homepage</strong> or under <strong>My Applications</strong>.
    </p>

  <a href="../../public/index.php"
    class="btn text-white px-4 py-2 fw-medium"
    style="background: linear-gradient(90deg, #1d4ed8); border: none; border-radius: 8px;">
    Go to Homepage
  </a>

  </div>

</div>


</body>
</html>
