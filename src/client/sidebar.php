<?php
$firstName  = $_SESSION['first_name'] ?? '';
$lastName   = $_SESSION['last_name'] ?? '';
$clientName = trim($firstName . ' ' . $lastName);

if ($clientName === '') {
    $clientName = 'Applicant';
}
?>

<aside id="sidebar"
class="fixed top-0 left-0 h-screen
w-16
bg-gradient-to-b from-blue-800 to-blue-900
text-white shadow-lg
transition-all duration-300
z-40 overflow-hidden">

<!-- HEADER -->
<div class="flex items-center gap-3 px-4 py-3 border-b border-blue-700">

<img src="/assets/pictures/pdao_logo.png"
class="w-9 h-9 object-contain shrink-0">

<div class="sidebar-text hidden leading-tight">

<p class="text-sm font-semibold">
<?= htmlspecialchars($clientName) ?>
</p>

<p class="text-xs text-blue-200">
Applicant
</p>

</div>

</div>


<!-- NAV -->
<nav class="mt-6 space-y-1 text-[15px]">

<!-- Home -->
<a href="/public/index.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-home text-lg"></i>
<span class="sidebar-text hidden whitespace-nowrap">Home</span>

</a>

<!-- My Applications -->
<a href="/src/client/my_applications.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-folder-open text-lg"></i>
<span class="sidebar-text hidden whitespace-nowrap">My Applications</span>

</a>

<!-- Profile -->
<a href="/src/client/profile.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-user text-lg"></i>
<span class="sidebar-text hidden whitespace-nowrap">Profile</span>

</a>

<!-- Logout -->
<a href="/public/logout.php"
class="flex items-center gap-4 px-4 py-3
text-red-200 hover:bg-red-600 hover:text-white transition">

<i class="fas fa-sign-out-alt text-lg"></i>
<span class="sidebar-text hidden whitespace-nowrap">Logout</span>

</a>

</nav>

</aside>
