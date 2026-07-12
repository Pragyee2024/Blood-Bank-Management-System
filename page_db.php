<?php
// Plain PDO connection for HTML page files (request_form.php, status.php, dashboard.php, approve.php).
// db_connect.php is reserved for api/*.php — it forces JSON output + CORS headers.

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $connect = null;
    if ($connect !== null) return $connect;

    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;

    try {
        $connect = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("DB connection failed: " . $e->getMessage());
    }

    return $connect;
}
