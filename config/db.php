<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '5432';
$dbname = getenv('DB_NAME') ?: 'pdao_db';
$user = getenv('DB_USER') ?: getenv('USER') ?: 'postgres';
$password = getenv('DB_PASS') ?: '';

$connectionString = "host=$host port=$port dbname=$dbname user=$user";
if ($password !== '') {
    $connectionString .= " password=$password";
}

$conn = pg_connect($connectionString);

if (!$conn) {
    die("Database connection failed: " . pg_last_error($conn));
}
