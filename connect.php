<?php
//  Blood Bank Management System — Database Connection

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $connect = null;
    if ($connect !== null) return $connect;

    $sslmode = getenv('DB_SSLMODE') ?: 'prefer';
    $data_source = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=" . $sslmode;

    try {
        $connect = new PDO($data_source, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
        exit;
    }

    return $connect;
}

// ── CORS + JSON headers ──────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Helpers used by every api/*.php file ─────────────────────
function normal(mixed $data): never {
    echo json_encode($data);
    exit;
}

function err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}
?>