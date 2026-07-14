<?php

$host = "localhost";
$port = "5432";
$dbname = "recommendation_database";
$username = "postgres";
$password = "admin";

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$dbname}",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $error) {
    die("Database connection failed: " . $error->getMessage());
}