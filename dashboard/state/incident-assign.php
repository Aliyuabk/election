<?php
// ============================================================
// STATE COORDINATOR - INCIDENT ASSIGN
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// GENERATE CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Security validation failed. Please try again.';
    } else {
        $incident_id = isset($_POST['incident_id']) ? (int)$_POST['incident_id'] : 0;
        $assign_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        
        if ($incident_id <= 0 || $assign_user_id <= 0) {
            $error = 'Invalid incident or user ID.';
        } else {
            try {
                // Verify incident exists
                $stmt = $db->prepare("SELECT id, title FROM incidents WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$incident_id, $tenant_id]);
                $incident = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$incident) {
                    $error = 'Incident not found.';
                } else {
                    // Verify user exists
                    $stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$assign_user_id, $tenant_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        $error = 'User not found.';
                    } else {
                        // Assign incident
                        $stmt = $db->prepare("
                            UPDATE incidents 
                            SET assigned_to = ?, status = 'acknowledged', updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$assign_user_id, $incident_id]);
                        
                        logActivity($user_id, 'incident_assigned', "Assigned incident ID: $incident_id to user ID: $assign_user_id");
                        
                        // Send email notification
                        try {
                            $subject = "Incident Assigned - " . APP_NAME;
                            $message = "Dear {$user['first_name']},\n\n";
                            $message .= "An incident has been assigned to you.\n\n";
                            $message .= "Incident: {$incident['title']}\n";
                            $message .= "Please log in to view and manage this incident.\n\n";
                            $message .= "Login: " . APP_URL . "/auth/login.php\n\n";
                            $message .= "Best regards,\n" . APP_NAME . " Team";
                            
                            $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
                            $stmt->execute([$assign_user_id]);
                            $user_email = $stmt->fetchColumn();
                            
                            if ($user_email) {
                                sendEmail($user_email, $subject, $message);
                            }
                        } catch (Exception $e) {
                            error_log("Assignment email failed: " . $e->getMessage());
                        }
                        
                        $success = "Incident assigned successfully!";
                    }
                }
            } catch (Exception $e) {
                $error = 'Error assigning incident: ' . $e->getMessage();
                error_log("Incident assignment error: " . $e->getMessage());
            }
        }
    }
}

// Redirect back with message
if (!empty($success)) {
    $_SESSION['flash_message'] = $success;
    $_SESSION['flash_type'] = 'success';
} elseif (!empty($error)) {
    $_SESSION['flash_message'] = $error;
    $_SESSION['flash_type'] = 'error';
}

header('Location: incident-view.php?id=' . ($incident_id ?? 0));
exit();
?>