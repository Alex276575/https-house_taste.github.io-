<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "<br>";
echo "Session: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not active') . "<br>";

$root = realpath(__DIR__ . '/..');
echo "Root path: $root<br>";

$dbFile = $root . '/config/database.php';
echo "DB file exists: " . (file_exists($dbFile) ? 'Yes' : 'No') . "<br>";

if (file_exists($dbFile)) {
    require_once $dbFile;
    echo "Database class loaded: " . (class_exists('Database') ? 'Yes' : 'No') . "<br>";

    try {
        $pdo = Database::getInstance()->getConnection();
        echo "DB connection: OK<br>";
    } catch (Exception $e) {
        echo "DB connection error: " . $e->getMessage() . "<br>";
    }
}
