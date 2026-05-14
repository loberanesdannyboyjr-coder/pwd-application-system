<?php
session_start();
require_once __DIR__ . '/../../config/paths.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PDAO Admin Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/b  ootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- External Global CSS -->
  <link rel="stylesheet" href="/assets/css/global/base.css">
  <link rel="stylesheet" href="/assets/css/global/layout.css">

  <style>
    .sidebar.closed {
      width: 0;
      opacity: 0;
      padding: 0;
    }

    .sidebar .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 30px;
    }

    .sidebar h4 {
      margin: 0;
      font-weight: bold;
    }

    .sidebar a {
      color: white;
      text-decoration: none;
      display: block;
      margin: 10px 0;
      padding: 8px 12px;
      border-radius: 8px;
      transition: background-color 0.3s ease, color 0.3s ease;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: rgba(255, 255, 255, 0.1);
      color: white !important;
    }

    .sidebar-item {
      margin-top: 10px;
    }

    .toggle-btn {
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
      transition: background 0.3s;
    }

    .sidebar-item .toggle-btn:hover {
      background-color: rgba(255, 255, 255, 0.1);
      color: #f5f5f5 !important;
    }

    .chevron-icon {
      transition: transform 0.3s ease;
    }

    .rotate {
      transform: rotate(180deg);
    }

    .submenu {
      overflow: hidden;
      max-height: 0;
      transition: max-height 0.4s ease;
    }

    .submenu-link {
      display: block;
      padding: 8px 20px;
      color: white;
      font-size: 0.95rem;
      border-radius: 6px;
      margin: 4px 0;
      text-decoration: none;
      transition: background 0.3s;
    }

    .submenu-link:hover {
      background-color: rgba(255, 255, 255, 0.15);
      color: white !important;
    }

    .no-wrap {
      white-space: nowrap;
    }

    hr {
      border: 0;
      height: 1px;
      background: white;
      margin: 10px 0;
      width: 100%;
    }
  </style>

  <div class="sidebar">
    <div class="logo">
      <img src="/assets/pictures/white.png" alt="logo" width="40">
      <h4>PDAO</h4>
    </div>
    <hr>
    <a class="active"><i class="fas fa-chart-line me-2"></i>Dashboard</a>
    <a><i class="fas fa-users me-2"></i>Members</a>

    <div class="sidebar-item">
      <div class="toggle-btn d-flex justify-content-between align-items-center">
        <span class="no-wrap"><i class="fas fa-folder me-2"></i>Manage Applications</span>
        <i class="fas fa-chevron-down chevron-icon"></i>
      </div>
      <div class="submenu">
        <a href="#" class="submenu-link"><i class="fas fa-plus me-2"></i>New Applications</a>
        <a href="#" class="submenu-link"><i class="fas fa-redo me-2"></i>Renew ID</a>
        <a href="#" class="submenu-link"><i class="fas fa-id-badge me-2"></i>Lost ID</a>
      </div>
    </div>

    <a><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
  </div>

  <script>
    document.querySelectorAll('.toggle-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const submenu = btn.nextElementSibling;
        const icon = btn.querySelector('.chevron-icon');
        submenu.style.maxHeight = submenu.style.maxHeight ? null : submenu.scrollHeight + "px";
        icon.classList.toggle('rotate');
      });
    });
  </script>