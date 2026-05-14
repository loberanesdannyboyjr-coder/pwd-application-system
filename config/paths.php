<?php
// config/paths.php
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Since DocumentRoot points to PWD-Application-System, basePath is empty
$basePath = '';

define('APP_BASE_URL', $proto . '://' . rtrim($host, '/') . $basePath);
define('ADMIN_BASE', rtrim(APP_BASE_URL, '/') . '/src/admin_side');
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '/PWD-Application-System');
}

