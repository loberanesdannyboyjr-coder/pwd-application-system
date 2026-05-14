<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

/* ===============================
   AUTH CHECK
   =============================== */
if (empty($_SESSION['user_id'])) {
    header('Location: /public/login_form.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

/* ===============================
   HELPER
   =============================== */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ===============================
   FETCH USER PROFILE (CORRECT)
   =============================== */
$res = pg_query_params(
    $conn,
    "SELECT first_name, last_name, email
     FROM user_account
     WHERE user_id = $1
     LIMIT 1",
    [$userId]
);

$user = pg_fetch_assoc($res);
if (!$user) {
    exit('Profile not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body
  id="pageBody"
  class="bg-gray-100 min-h-screen text-gray-800
         pl-16 transition-all duration-300">

<?php include __DIR__ . '/sidebar.php'; ?>

<!-- HEADER -->
<header class="relative bg-gradient-to-r from-blue-700 to-blue-900 text-white py-4">

  <!-- Burger -->
  <button
    onclick="toggleSidebar()"
    class="absolute left-6 top-1/2 -translate-y-1/2 text-2xl z-50">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Center title -->
  <div class="text-center">
    <h1 class="text-xl font-semibold">My Profile</h1>
  </div>
</header>

<!-- CONTENT -->
<main class="p-6">
  <div class="max-w-3xl mx-auto">

    <div class="bg-white rounded-lg shadow overflow-hidden">

      <!-- BASIC INFO -->
      <div class="pt-10 pb-6 text-center">
        <h2 class="text-lg font-semibold">
          <?= h($user['first_name']) ?> <?= h($user['last_name']) ?>
        </h2>
        <p class="text-sm text-gray-500">
          <?= h($user['email']) ?>
        </p>
      </div>

      <!-- DETAILS -->
      <div class="border-t px-6 py-4 text-sm">
        <div class="grid grid-cols-3 gap-2 mb-2">
          <span class="text-gray-500">First Name</span>
          <span class="col-span-2"><?= h($user['first_name']) ?></span>
        </div>

        <div class="grid grid-cols-3 gap-2 mb-2">
          <span class="text-gray-500">Last Name</span>
          <span class="col-span-2"><?= h($user['last_name']) ?></span>
        </div>

        <div class="grid grid-cols-3 gap-2">
          <span class="text-gray-500">Email</span>
          <span class="col-span-2"><?= h($user['email']) ?></span>
        </div>
      </div>

    </div>
  </div>
</main>

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
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.add('hidden'));
  } else {
    sidebar.classList.remove('w-16');
    sidebar.classList.add('w-64');
    body.classList.remove('pl-16');
    body.classList.add('pl-64');
    document.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
  }
}
</script>

</body>
</html>
