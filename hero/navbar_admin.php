<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PWD Online Application</title>
  
  <style>
    :root {
      --nav-bg: #14255A;
      --primary-blue: #14258A;
      --light-gray: #F0F1F5;
      --border-gray: #CED4DA;
      --text-gray: #6C757D;
      --radius: 6px;
      --circle-size: 30px;
    }
    
    .navbar {
      background-color: var(--nav-bg);
    }

    .navbar-brand, .nav-link {
      color: #fff !important;
      font-weight: 600;
      transition: transform 0.2s ease, color 0.2s ease;
    }

    .navbar-brand:hover, .nav-link:hover {
      transform: scale(1.05);
      color: #e0e0e0;
    }
    
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg" style="background-color: #14255A;">
  <div class="container-fluid">
    <a class="navbar-brand text-white" href="/src/admin_side/dashboard.php">
      <img src="../../assets/pictures/white.png" alt="Logo" width="32" height="32" class="me-2" />
      Persons with Disabilities Affairs Office
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link text-white" href="/src/admin_side/dashboard.php">Home</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="#">Contact</a></li>
      </ul>
    </div>
  </div>
</nav>
</body>
</html>