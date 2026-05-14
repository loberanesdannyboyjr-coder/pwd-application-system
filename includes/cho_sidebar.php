<?php
// Do NOT call session_start() here

$firstName  = $_SESSION['first_name'] ?? '';
$lastName   = $_SESSION['last_name'] ?? '';
$username   = $_SESSION['username'] ?? 'User';

$clientName = trim($firstName . ' ' . $lastName);
if ($clientName === '') {
    $clientName = $username;
}
?>

        <!-- Tailwind + Icons -->
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


        <!-- SIDEBAR -->
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
        CHO
        </p>

        </div>

        </div>


        <!-- NAV -->
        <nav class="mt-6 space-y-1 text-[15px]">

        <a href="CHO_dashboard.php"
        class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

        <i class="fas fa-chart-line text-lg"></i>
        <span class="sidebar-text hidden">Dashboard</span>

        </a>


        <a href="members.php"
        class="flex items-center gap-4 px-4 py-3 hover:bg-blue-700 transition">

        <i class="fas fa-wheelchair text-lg"></i>
        <span class="sidebar-text hidden">Members</span>

        </a>



        <!-- Applications -->
        <div>

        <button onclick="toggleSubmenu()"
        class="flex items-center justify-between w-full px-4 py-3 hover:bg-blue-700 transition">

        <div class="flex items-center gap-4">

        <i class="fas fa-folder text-lg"></i>
        <span class="sidebar-text hidden whitespace-nowrap">Applications</span>

        </div>

        <i id="submenuIcon"
        class="fas fa-chevron-down text-sm transition-transform chevron-icon"></i>

        </button>


        <div id="submenu" class="hidden space-y-1">

        <!-- For Review -->
        <a href="applications.php"
        class="flex items-center gap-4 px-8 py-3 hover:bg-blue-700 transition">

        <i class="fas fa-hourglass-half text-lg"></i>
        <span class="sidebar-text hidden whitespace-nowrap">For Review</span>

        </a>

        <!-- Approved -->
        <a href="accepted.php"
        class="flex items-center gap-4 px-8 py-3 hover:bg-blue-700 transition">

        <i class="fas fa-check-circle text-lg"></i>
        <span class="sidebar-text hidden whitespace-nowrap">Approved</span>

        </a>

        <!-- Disapproved -->
        <a href="denied.php"
        class="flex items-center gap-4 px-8 py-3 hover:bg-blue-700 transition">

        <i class="fas fa-times-circle text-lg"></i>
        <span class="sidebar-text hidden whitespace-nowrap">Disapproved</span>

        </a>

        </div>

        </div>

        <a href="logout.php"
        class="flex items-center gap-4 px-4 py-3
        text-red-200 hover:bg-red-600 hover:text-white transition">

        <i class="fas fa-sign-out-alt text-lg"></i>
        <span class="sidebar-text hidden">Logout</span>

        </a>

        </nav>

        </aside>


        <script>

        /* SUBMENU */

        function toggleSubmenu(){

        const submenu = document.getElementById("submenu");
        const icon = document.getElementById("submenuIcon");

        submenu.classList.toggle("hidden");
        icon.classList.toggle("rotate-180");

        }

        /* SIDEBAR EXPAND */

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

        .sidebar-text {
            white-space: nowrap;
        }
            #sidebar.w-16 .chevron-icon {
                display: none;
            }
        </style>