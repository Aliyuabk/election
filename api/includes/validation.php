<?php
/**
 * Input Validation Helper for Mobile App
 */

class Validator {
    /**
     * Validate required fields
     */
    public static function required($data, $fields) {
        $errors = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Nigerian format)
     */
    public static function validatePhone($phone) {
        // Nigerian phone numbers: 080xxxxxxx, 090xxxxxxx, 081xxxxxxx, 070xxxxxxx
        return preg_match('/^(0[7-9][0-9]{9})$/', $phone) || 
               preg_match('/^\+234[7-9][0-9]{9}$/', $phone);
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate integer
     */
    public static function validateInt($value) {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }
    
    /**
     * Validate float
     */
    public static function validateFloat($value) {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }
    
    /**
     * Validate latitude
     */
    public static function validateLat($lat) {
        return is_numeric($lat) && $lat >= -90 && $lat <= 90;
    }
    
    /**
     * Validate longitude
     */
    public static function validateLng($lng) {
        return is_numeric($lng) && $lng >= -180 && $lng <= 180;
    }
    
    /**
     * Validate date format (Y-m-d)
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Validate password strength
     */
    public static function validatePassword($password) {
        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number';
        }
        return true;
    }
    
    /**
     * Validate enum value
     */
    public static function validateEnum($value, $allowed) {
        return in_array($value, $allowed);
    }
    
    /**
     * Validate base64 string
     */
    public static function validateBase64($str) {
        return base64_encode(base64_decode($str, true)) === $str;
    }
}