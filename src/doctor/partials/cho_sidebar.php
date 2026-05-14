<?php
$firstName  = $_SESSION['first_name'] ?? '';
$lastName   = $_SESSION['last_name'] ?? '';
$username   = $_SESSION['username'] ?? 'User';

$clientName = trim($firstName . ' ' . $lastName);
if ($clientName === '') {
    $clientName = $username;
}
?>

<aside id="sidebar"
class="fixed top-0 left-0 h-screen
w-64
bg-gradient-to-b from-blue-800 to-blue-900
text-white shadow-lg
transition-all duration-300
z-40 overflow-hidden">

<!-- HEADER -->
<div class="flex items-center gap-3 px-4 py-3 border-b border-blue-700">

<img src="/assets/pictures/pdao_logo.png"
class="w-9 h-9 object-contain shrink-0">

<div class="leading-tight">

<p class="text-sm font-semibold">
<?= htmlspecialchars($clientName) ?>
</p>

<p class="text-xs text-blue-200">
CHO
</p>

</div>

</div>


<!-- NAVIGATION -->
<nav class="mt-6 space-y-1 text-[15px]">

<!-- Dashboard -->
<a href="CHO_dashboard.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-chart-line text-lg"></i>
<span>Dashboard</span>

</a>


<!-- Members -->
<a href="members.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-wheelchair text-lg"></i>
<span>Members</span>

</a>


<!-- Applications -->
<a href="applications.php"
class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

<i class="fas fa-users text-lg"></i>
<span>Applications</span>

</a>


<!-- Manage Applications -->
<div>

<button onclick="toggleSubmenu()"
class="flex items-center justify-between w-full px-4 py-3 hover:bg-blue-700 transition">

<div class="flex items-center gap-4">

<i class="fas fa-folder text-lg"></i>
<span>Manage Applications</span>

</div>

<i id="submenuIcon" class="fas fa-chevron-down text-sm transition-transform"></i>

</button>


<div id="submenu" class="hidden space-y-1">

<a href="accepted.php"
class="flex items-center gap-4 px-8 py-3 hover:bg-blue-700 transition">

<i class="fas fa-user-check text-lg"></i>
<span>Accepted</span>

</a>


<a href="denied.php"
class="flex items-center gap-4 px-8 py-3 hover:bg-blue-700 transition">

<i class="fas fa-user-times text-lg"></i>
<span>Denied</span>

</a>

</div>

</div>


<!-- Logout -->
<a href="logout.php"
class="flex items-center gap-4 px-4 py-3 text-red-200 hover:bg-red-600 hover:text-white transition">

<i class="fas fa-sign-out-alt text-lg"></i>
<span>Logout</span>

</a>

</nav>

</aside>


<script>

function toggleSubmenu(){

const submenu = document.getElementById("submenu");
const icon = document.getElementById("submenuIcon");

submenu.classList.toggle("hidden");

icon.classList.toggle("rotate-180");

}

</script>