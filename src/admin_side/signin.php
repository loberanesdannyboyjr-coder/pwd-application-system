<?php
session_start();
require_once __DIR__ . '/../../config/paths.php';
?>

  <!DOCTYPE html> <html lang="en"> <head> <meta charset="UTF-8"> 
  <title>PWD Admin Sign-in</title> <meta name="viewport" content="width=device-width, initial-scale=1"> 
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> 
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script> <link rel="stylesheet" href="../../assets/css/global/login_signup.css"> </head> <body>

    <div class="main-wrapper">
      <!-- Left Section -->
      <div class="left-panel">
        <div class="left-content">
          <h1>Welcome to PWD<br>Admin Portal</h1>
          <p>Dedicated to Better Accessibility<br> and Support.</p>
          <img src="<?= APP_BASE_URL ?>/assets/pictures/admin.png" alt="PWD Illustration">
        </div>
      </div>

      <!-- Right Section -->
      <div class="right-panel">
        <div class="login-card">
        <img src="<?= APP_BASE_URL ?>/assets/pictures/Logo.jpg" class="logo" alt="PWD Logo">
                  <p style="font-size: 1.3rem; font-weight: 600;">Sign in</p>

          <!-- Error banner -->
          <?php if (!empty($_GET['err'])): 
            $map = [
                'empty_user' => 'Please enter your username.',
                'empty_pwd'  => 'Please enter your password.',
                'db_conn'    => 'Database connection error.',
                'no_account' => 'Account not found.',
                'invalid_pwd'=> 'Incorrect password.',
                'invalid_role' => 'Selected role does not match this account.',
            ];
            $msg = $map[$_GET['err']] ?? 'Login error. Please try again.'; ?>
            <div class="alert alert-danger py-2" role="alert"><?= htmlspecialchars($msg) ?></div>
          <?php endif; ?>


          <!-- Login Form -->
          <form action="<?= APP_BASE_URL ?>/backend/auth/admin_login_process.php" method="POST">

              <div class="form-group">
                  <input type="text"
                        name="username"
                        class="form-control"
                        placeholder="Username"
                        required>

                  <span class="form-icon">
                      <i class="fas fa-user"></i>
                  </span>
              </div>

              <div class="form-group">
                  <input type="password"
                        name="password"
                        class="form-control"
                        placeholder="Password"
                        required>

                  <span class="form-icon">
                      <i class="fas fa-lock"></i>
                  </span>
              </div>

              <button type="submit" class="btn btn-login">
                  Sign In
              </button>

          </form>
        </div>
        <img src="<?= APP_BASE_URL ?>/assets/pictures/iligan.png" class="iligan-logo" alt="Iligan Logo">
      </div>
    </div>

  </body>
  </html>
