<?php
session_start();
$isLoggedIn = isset($_SESSION['applicant_id']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>PWD Online Application</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- ✅ Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- ✅ Import Poppins Font from Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">



  <!-- ✅ Custom Styles -->
  <style>
    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Poppins', sans-serif;
    }

    /* Initially hide the Empowering Section */
    .empowering-section {
      opacity: 0;
      transform: translateY(50px);
      transition: opacity 0.5s, transform 0.5s;
    }

    .empowering-section.visible {
      opacity: 1;
      transform: translateY(0);
    }
  </style>
</head>

<body
  id="pageBody"
  class="bg-white flex flex-col min-h-screen text-gray-800
         <?= $isLoggedIn ? 'pl-16' : '' ?> transition-all duration-300">


<?php if ($isLoggedIn): ?>
  <?php include __DIR__ . '/../src/client/sidebar.php'; ?>
<?php endif; ?>

<header class="relative bg-gradient-to-r from-blue-700 to-blue-900 text-white py-6">

  <!-- Burger button (top-left) -->
  <?php if ($isLoggedIn): ?>
    <button
      onclick="toggleSidebar()"
      class="absolute top-4 left-6 text-white text-2xl z-50">
      <i class="fas fa-bars"></i>
    </button>
  <?php endif; ?>


  <!-- CENTERED CONTAINER (IMPORTANT PART) -->
  <div class="max-w-7xl mx-auto px-6">

    <!-- SAME LAYOUT: logos LEFT, text RIGHT -->
    <div class="flex items-center gap-6 justify-center">

      <!-- Logos (NOT centered individually) -->
      <div class="flex items-center gap-4 shrink-0">
        <img src="../assets/pictures/pdao_logo.png"
             class="w-28 h-28 object-contain" alt="PDAO Logo">
        <img src="../assets/pictures/iligan_logo.png"
             class="w-28 h-28 object-contain" alt="Iligan Logo">
      </div>

      <!-- Text -->
      <div class="max-w-xl">
        <h1 class="text-2xl md:text-2xl font-bold">
          <?= isset($_SESSION['first_name'])
            ? 'Welcome to PWD Online Application, ' . htmlspecialchars($_SESSION['first_name']) . '!'
            : 'Welcome to PWD Online Application!' ?>
        </h1>
        <p class="text-sm md:text-base mt-1 text-blue-100">
          Iligan City's official platform for empowering Persons with Disabilities.
        </p>
      </div>

    </div>
  </div>
</header>
      
<!-- Buttons Section -->
<section class="bg-white py-7 px-5 text-center mt-0">
  <div class="max-w-7xl mx-auto">
    <!-- Flex container with no wrapping to keep buttons on one line -->
    <div class="flex justify-center gap-5 mb-6">

      <!-- New Registration -->
     <a href="<?php echo $isLoggedIn ? '../src/client/form1.php?type=new' : '/public/login_form.php'; ?>"
        class="bg-blue-800 text-white font-semibold px-8 py-6 rounded-lg shadow-md hover:bg-blue-800 transition w-56 sm:w-64 flex flex-col items-center">
        <img src="../assets/pictures/newreg.png" alt="New Registration" class="w-16 h-16 mb-3" />
        <span class="text-lg font-semibold">New Registration</span>
      </a>

     <!-- Renew ID -->
      <a href="<?php echo $isLoggedIn ? '/src/client/renew.php' : '/public/login_form.php'; ?>"
        class="bg-blue-800 text-white font-semibold px-8 py-6 rounded-lg shadow-md hover:bg-blue-800 transition w-56 sm:w-64 flex flex-col items-center">
        <img src="../assets/pictures/renewreg.png" alt="Renew ID" class="w-16 h-16 mb-3" />
        <span class="text-lg font-semibold">Renew ID</span>
      </a>

      <!-- Lost ID -->
      <a href="<?php echo $isLoggedIn
 ? '/src/client/form1.php?type=lost' : '/public/login_form.php'; ?>"
        class="bg-blue-800 text-white font-semibold px-8 py-6 rounded-lg shadow-md hover:bg-blue-800 transition w-56 sm:w-64 flex flex-col items-center">
        <img src="../assets/pictures/lostid.png" alt="Lost ID" class="w-16 h-16 mb-3" />
        <span class="text-lg font-semibold">Lost ID</span>
      </a>

    </div>

<!-- Button Group: Requirements + Login/Signup -->
<div class="flex flex-col items-center gap-4 mt-10">
  
  <!-- Requirements Button -->
<a href="#requirements"
   class="bg-blue-800 text-white text-sm font-semibold px-9 py-2.5 rounded-lg shadow hover:bg-blue-900 transition">
  Requirements
</a>

  
</div>


<!-- Empowering Section (Force Full-Width Background and Content) -->
<section class="w-full bg-gradient-to-r from-blue-700 to-blue-900 text-white text-center py-20 px-6 mt-60">

    <h2 class="text-3xl md:text-4xl font-bold mb-6">Empowering Every Step</h2>
    <p class="text-sm md:text-base leading-relaxed max-w-3xl mx-auto">
      Welcome to the PWD Online ID Application — a digital space where accessibility meets simplicity.
      Apply, connect, and stay informed all in one place.
    </p>
    <a href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . '/public/login_form.php'; ?>"  class="mt-8 inline-block bg-[#4177B2] text-white font-semibold px-8 py-3 rounded-[30px] shadow border border-black hover:bg-[#3577e6] transition">
      Get Started
    </a>
  </section>

<!-- Qualifications Section (Adjusted size and margin) -->
<section id="qualifications" class="py-20 px-4 bg-white text-center mt-16">
  <h3 class="text-2xl md:text-3xl font-extrabold mb-8" style="font-family: 'Quicksand', sans-serif; color: #072176;">Qualifications for Applying for a PWD ID</h3> 
  <div class="bg-blue-50 max-w-md mx-auto p-10 rounded-lg shadow-lg text-left space-y-4 text-sm leading-relaxed border border-blue-200" style="box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);">
    <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Must be 59 years old or below</p>
    <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Resident of Iligan City only</p>
    <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Must be a Filipino citizen</p>
    <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Must have a specific type of disability</p>
  </div>
</section>


<!-- Application Requirements Section -->
<section id="requirements" class="py-20 px-4 bg-white mt-10 mb-20">
  <h3 class="text-2xl md:text-3xl font-extrabold text-center mb-12" style="font-family: 'Quicksand', sans-serif; color: #072176;">Application Requirements</h3>
  <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto text-sm">

    <!-- New Application -->
    <div class="bg-blue-50 p-8 rounded-lg shadow border border-blue-200 space-y-4 text-left">
      <div class="flex items-center gap-2">
    <img src="../assets/pictures/new_icon.png" alt="New" class="w-6 h-6" />
        <h4 class="text-blue-700 font-semibold text-lg mb-3">New Application</h4>
      </div>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Filled-out registration form</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> 1 whole body picture</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Barangay Certificate of Residency / Indigency</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Doctor's Referral / Medical Certificate</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> 1 pc 1x1 ID picture</p>

    </div>

    <!-- ID Renewal -->
    <div class="bg-blue-50 p-8 rounded-lg shadow border border-blue-200 space-y-4 text-left">
      <div class="flex items-center gap-2">
        <img src="../assets/pictures/renew_icon.png" alt="Renew" class="w-6 h-6" />
        <h4 class="text-blue-700 font-semibold text-lg mb-3">ID Renewal</h4>
      </div>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Update form</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Upload old PWD ID</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Updated Barangay Certificate of Residency / Indigency</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> UpdatedDoctor's Referral / Medical Certificate</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Updated 1x1 ID picture</p>
    </div>

    <!-- Lost ID -->
    <div class="bg-blue-50 p-8 rounded-lg shadow border border-blue-200 space-y-4 text-left">
      <div class="flex items-center gap-2">
        <img src="../assets/pictures/lostid_icon.png" alt="Lost" class="w-6 h-6" />
        <h4 class="text-blue-700 font-semibold text-lg mb-3">Lost ID</h4>
      </div>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Update form</p>
      <p><img src="../assets/pictures/check.png" alt="Check" class="w-4 h-4 inline-block" /> Affidavit of Loss</p>
    </div>

<footer
  class="fixed bottom-0 left-0 w-full
         bg-gradient-to-b from-blue-800 to-blue-900
         text-white text-center
         py-5 px-4
         shadow-inner z-30">

  <p class="text-sm font-semibold">
    © 2025 PWD Online ID Application. All Rights Reserved.
  </p>
  <p class="text-xs mt-1 text-blue-100 italic">
    Designed with care and inclusivity.
  </p>

</footer>

  <!-- ✅ JavaScript to detect when the Empowering Section is in view -->
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const empoweringSection = document.querySelector('.empowering-section');
        if (!empoweringSection) return; // no target, nothing to observe (prevents error)

        const observer = new IntersectionObserver((entries, obs) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              empoweringSection.classList.add('visible');
              obs.disconnect();
            }
          });
        }, { threshold: 0.5 });

        observer.observe(empoweringSection);
      });
    </script>

<script>
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const body = document.getElementById('pageBody');

  const isExpanded = sidebar.classList.contains('w-64');

  if (isExpanded) {
    // Collapse
    sidebar.classList.remove('w-64');
    sidebar.classList.add('w-16');
    body.classList.remove('pl-64');
    body.classList.add('pl-16');

    document.querySelectorAll('.sidebar-text').forEach(el => {
      el.classList.add('hidden');
    });
  } else {
    // Expand
    sidebar.classList.remove('w-16');
    sidebar.classList.add('w-64');
    body.classList.remove('pl-16');
    body.classList.add('pl-64');

    document.querySelectorAll('.sidebar-text').forEach(el => {
      el.classList.remove('hidden');
    });
  }
}
</script>


</body>
</html>