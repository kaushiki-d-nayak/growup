<?php
define('DB_HOST', 'growup.c944ysk6m0y8.ap-south-1.rds.amazonaws.com');
define('DB_NAME', 'growup');
define('DB_USER', 'admin');       
define('DB_PASS', 'kaushiki123');          
define('DB_CHARSET', 'utf8mb4');

require_once __DIR__ . '/../includes/dreams_schema.php';

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureDreamsBudgetSchema($pdo);
        } catch (PDOException $e) {
            // In production, log this error instead of displaying it
            die('<div style="font-family:sans-serif;color:#c0392b;padding:2rem;">
                <h2>Database Connection Error</h2>
                <p>Could not connect to the database. Please check your config/database.php settings.</p>
                <small>' . htmlspecialchars($e->getMessage()) . '</small>
            </div>');
        }
    }

    return $pdo;
}
