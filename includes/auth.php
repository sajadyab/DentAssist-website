<?php
require_once 'config.php';
require_once 'db.php';

class Auth {
    
    // Check if user is logged in
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Require login - redirect if not logged in
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: /Dental/login.php");
            exit;
        }
    }
    
    // Check if user has specific role
    public static function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    // Check if user is admin
    public static function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    // Require admin access
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            die("Access denied. Admin privileges required.");
        }
    }
    
    // Require specific role
    public static function requireRole($role) {
        self::requireLogin();
        if (!self::hasRole($role)) {
            die("Access denied. You don't have permission to view this page.");
        }
    }
    
    // Get current user ID
    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    // Get current user data
    public static function user() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']],
            "i"
        );
    }
    
    // Login user
    public static function login($username, $password) {
        $db = Database::getInstance();
        
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$username, $username],
            "ss"
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            // Update last login
            $db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']],
                "i"
            );
            
            return true;
        }
        
        return false;
    }
    
    // Logout user
    public static function logout() {
        session_destroy();
        return true;
    }
    
    // Hash password
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
?>