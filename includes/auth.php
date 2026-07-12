<?php
declare(strict_types=1);
// Blood Bank Management System — Session / Auth helpers
// Include this AFTER connect.php on every protected page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns the currently logged-in user (from session) or null.
 */
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id'  => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
        'donor_id' => $_SESSION['donor_id'] ?? null,
    ];
}

/**
 * Guards a page/endpoint. Redirects (HTML pages) or returns 401 JSON (api/*.php).
 *
 * @param string[] $allowed_roles  e.g. ['donor'] or ['admin','staff']. Empty = any logged-in user.
 * @param bool     $is_api         set true inside api/*.php files
 */
function require_login(array $allowed_roles = [], bool $is_api = false): array {
    $user = current_user();
    $ok = $user && (!$allowed_roles || in_array($user['role'], $allowed_roles, true));

    if (!$ok) {
        if ($is_api) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        header('Location: /login.php');
        exit;
    }

    return $user;
}

/**
 * Where to send a user right after login, based on role.
 */
function role_home(string $role): string {
    return $role === 'donor' ? '/donor/profile.php' : '/admin/dashboard.php';
}
