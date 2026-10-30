<?php
/**
 * Database Connection Handler (PDO Singleton)
 */

require_once __DIR__ . '/../config/app.php';

function getDb(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $configFile = __DIR__ . '/../config/database.php';
        if (!file_exists($configFile)) {
            die("Database configuration file not found. Please copy config/database.example.php to config/database.php.");
        }
        $config = require $configFile;
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
        } catch (PDOException $e) {
            error_log("Database connection failure: " . $e->getMessage());
            die("<div style='font-family: sans-serif; padding: 2rem; background: #fff5f5; border: 1px solid #feb2b2; margin: 2rem; border-radius: 8px;'>
                <h2 style='color: #c53030; margin-top:0;'>Database Connection Error</h2>
                <p>Could not connect to the RMU database server. Please ensure MySQL is running in XAMPP and <code>rmu_db</code> has been imported.</p>
                <small style='color: #718096;'>Technical details: " . htmlspecialchars($e->getMessage()) . "</small>
            </div>");
        }
    }
    return $pdo;
}

