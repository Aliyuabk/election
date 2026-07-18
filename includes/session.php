<?php
/**
 * Session Management Class
 * Handles all session operations with proper security
 */
class SessionManager {
    
    /**
     * Start session if not already started
     */
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Set session value
     */
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value with optional default
     */
    public static function get($key, $default = null) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session key
     */
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session completely
     */
    public static function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 42000,
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return self::has('user_id') && self::get('logged_in') === true;
    }
    
    /**
     * Check and validate session timeout
     */
    public static function checkSessionTimeout() {
        if (self::has('last_activity')) {
            if (time() - self::get('last_activity') > SESSION_TIMEOUT) {
                self::destroy();
                return false;
            }
        }
        self::set('last_activity', time());
        return true;
    }
    
    /**
     * Regenerate session ID for security
     */
    public static function regenerate() {
        session_regenerate_id(true);
    }
    
    /**
     * Flash message system - set and retrieve one-time messages
     */
    public static function flash($key, $value = null) {
        if ($value === null) {
            $flash = self::get('flash_' . $key);
            self::remove('flash_' . $key);
            return $flash;
        }
        self::set('flash_' . $key, $value);
    }
}
?>