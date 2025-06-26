<?php
session_start();

$DB_HOST = 'localhost';
$DB_USER = 'user_db';
$DB_PASS = '+:i0J_uJOdyr=(5n';
$DB_NAME = 'user_db';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Tidak dapat terhubung ke database. " . $e->getMessage());
}
?>