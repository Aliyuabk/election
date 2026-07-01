<?php
session_start();

class SessionManager {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    public static function has($key) {
        return isset($_SESSION[$key]);
    }
    
    public static function remove($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return self::has('user_id') && self::get('logged_in') === true;
    }
    
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
    
    public static function regenerate() {
        session_regenerate_id(true);
    }
}
?>