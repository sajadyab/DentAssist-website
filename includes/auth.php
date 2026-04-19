<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class Auth
{
    private static $lastError = '';

    // Check if user is logged in
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    // Require login - redirect if not logged in
    public static function requireLogin()
    {
        if (!self::isLoggedIn()) {
            header('Location: /Dental_test/login.php');
            exit;
        }
    }

    // Check if user has specific role
    public static function hasRole($role)
    {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    // Check if user is admin
    public static function isAdmin()
    {
        if (isset($_SESSION['is_admin']) && (int) $_SESSION['is_admin'] === 1) {
            return true;
        }

        // Backward-compatible fallback: treat the built-in "admin" account as admin
        // even if the DB row/column wasn't migrated correctly.
        return isset($_SESSION['username']) && strtolower((string) $_SESSION['username']) === 'admin';
    }

    // Require admin access
    public static function requireAdmin()
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            die('Access denied. Admin privileges required.');
        }
    }

    // Require specific role
    public static function requireRole($role)
    {
        self::requireLogin();
        if (!self::hasRole($role)) {
            die("Access denied. You don't have permission to view this page.");
        }
    }

    // Get current user ID
    public static function userId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    // Get current user data
    public static function user()
    {
        if (!self::isLoggedIn()) {
            return null;
        }

        $db = Database::getInstance();

        return $db->fetchOne(
            'SELECT * FROM users WHERE id = ?',
            [$_SESSION['user_id']],
            'i'
        );
    }

    // Login user
    public static function login($username, $password)
    {
        $db = Database::getInstance();

        $user = $db->fetchOne(
            'SELECT * FROM users WHERE username = ? OR email = ?',
            [$username, $username],
            'ss'
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            // Check if account is active
            if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
                self::$lastError = 'inactive';
                return false;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $isAdmin = isset($user['is_admin']) ? (int) $user['is_admin'] : 0;
            if (strtolower((string) $user['username']) === 'admin') {
                $isAdmin = 1;
            }
            $_SESSION['is_admin'] = $isAdmin;
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image'];

            // Update last login
            if (function_exists('dbColumnExists') && dbColumnExists('users', 'sync_status')) {
                $db->execute(
                    "UPDATE users SET last_login = NOW(), sync_status = 'pending' WHERE id = ?",
                    [$user['id']],
                    'i'
                );
            } else {
                $db->execute(
                    'UPDATE users SET last_login = NOW() WHERE id = ?',
                    [$user['id']],
                    'i'
                );
            }
            if (function_exists('sync_push_row_now')) {
                sync_push_row_now('users', (int) $user['id']);
            }

            return true;
        }

        self::$lastError = 'invalid';
        return false;
    }

    // Get the last login error reason
    public static function getLastError()
    {
        return self::$lastError;
    }

    // Logout user
    public static function logout()
    {
        session_destroy();

        return true;
    }

    // Hash password
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
