<?php
// Include the configuration file (contains database credentials, site settings, etc.)
require_once 'config.php';
// Include the database class file (provides Database class for DB operations)
require_once 'db.php';

// Define the Auth class for handling user authentication and authorization
class Auth {
    
    /**
     * Check if a user is currently logged in.
     * @return bool True if user_id session variable exists, false otherwise.
     */
    public static function isLoggedIn() {
        // Return true if the session contains a 'user_id' key
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Require that a user is logged in; if not, redirect to the login page.
     * This method stops script execution after sending the redirect header.
     */
    public static function requireLogin() {
        // If the user is not logged in (self::isLoggedIn() returns false)
        if (!self::isLoggedIn()) {
            // Redirect to the login page (relative path /Dental/login.php)
            header("Location: /Dental/login.php");
            // Terminate the script to ensure no further code runs after redirect
            exit;
        }
    }

    /**
     * Check if the currently logged-in user has a specific role.
     * @param string $role The role to check (e.g., 'admin', 'doctor', 'patient').
     * @return bool True if the session role matches the given role, false otherwise.
     */
    public static function hasRole($role) {
        // Return true if session 'role' is set and equals the provided role
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Require that the logged-in user has a specific role.
     * If not logged in, it first calls requireLogin() to enforce login.
     * If logged in but role doesn't match, it halts execution with an error message.
     * @param string $role The required role.
     */
    public static function requireRole($role) {
        // First ensure the user is logged in
        self::requireLogin();
        // If the user does not have the required role
        if (!self::hasRole($role)) {
            // Output an error message and stop script execution
            die("Access denied. You don't have permission to view this page.");
        }
    }
    
    /**
     * Get the current user's ID from the session.
     * @return int|null The user ID if logged in, otherwise null.
     */
    public static function userId() {
        // Return the 'user_id' session variable if it exists, else return null
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Retrieve the full user record for the currently logged-in user from the database.
     * @return array|null Associative array of user data, or null if not logged in or user not found.
     */
    public static function user() {
        // If no user is logged in, return null immediately
        if (!self::isLoggedIn()) {
            return null;
        }
        
        // Get the singleton instance of the Database class
        $db = Database::getInstance();
        // Execute a SELECT query to fetch the user record by ID (using prepared statement)
        // fetchOne() returns a single row as an associative array
        return $db->fetchOne(
            "SELECT * FROM users WHERE id = ?",  // SQL query with placeholder
            [$_SESSION['user_id']],              // Parameter array (user ID)
            "i"                                   // Parameter type: i = integer
        );
    }
    
    /**
     * Attempt to log in a user with the provided username/email and password.
     * If successful, sets session variables and updates last_login timestamp.
     * @param string $username The username or email entered by the user.
     * @param string $password The plain-text password.
     * @return bool True on successful login, false on failure.
     */
    public static function login($username, $password) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch the user record where username OR email matches the input
        $user = $db->fetchOne(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$username, $username],  // Both placeholders use the same $username value
            "ss"                      // Parameter types: s = string (two strings)
        );
        
        // If a user was found AND the provided password verifies against the stored hash
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables with user data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            // Update the user's last_login timestamp in the database to NOW()
            $db->execute(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']],        // Parameter: user ID
                "i"                    // Parameter type: i = integer
            );
            
            // Login successful
            return true;
        }
        
        // Login failed (user not found or password incorrect)
        return false;
    }
    
    /**
     * Log out the current user by destroying the session.
     * @return bool Always returns true.
     */
    public static function logout() {
        // Destroy the entire session (clears all session data and removes session cookie)
        session_destroy();
        return true;
    }
    
    /**
     * Hash a plain-text password using the default bcrypt algorithm.
     * @param string $password The plain-text password.
     * @return string The hashed password.
     */
    public static function hashPassword($password) {
        // Use PHP's built-in password_hash() with the default algorithm (bcrypt)
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
?>