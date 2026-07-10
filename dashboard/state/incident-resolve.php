<?php
// ============================================================
// STATE COORDINATOR - INCIDENT RESOLVE
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
        $resolution_notes = isset($_POST['resolution_notes']) ? trim($_POST['resolution_notes']) : '';
        
        if ($incident_id <= 0) {
            $error = 'Invalid incident ID.';
        } elseif (empty($resolution_notes)) {
            $error = 'Resolution notes are required.';
        } else {
            try {
                // Verify incident exists
                $stmt = $db->prepare("SELECT id, title FROM incidents WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$incident_id, $tenant_id]);
                $incident = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$incident) {
                    $error = 'Incident not found.';
                } else {
                    // Resolve incident
                    $stmt = $db->prepare("
                        UPDATE incidents 
                        SET status = 'resolved', 
                            resolved_by = ?, 
                            resolved_at = NOW(),
                            resolution_notes = ?,
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$user_id, $resolution_notes, $incident_id]);
                    
                    logActivity($user_id, 'incident_resolved', "Resolved incident ID: $incident_id");
                    
                    // Send email notification to reporter
                    try {
                        $stmt = $db->prepare("
                            SELECT u.email, u.first_name, u.last_name 
                            FROM incidents i
                            JOIN users u ON i.reporter_id = u.id
                            WHERE i.id = ?
                        ");
                        $stmt->execute([$incident_id]);
                        $reporter = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($reporter && !empty($reporter['email'])) {
                            $subject = "Incident Resolved - " . APP_NAME;
                            $message = "Dear {$reporter['first_name']},\n\n";
                            $message .= "The incident you reported has been resolved.\n\n";
                            $message .= "Incident: {$incident['title']}\n";
                            $message .= "Resolution Notes: $resolution_notes\n\n";
                            $message .= "Thank you for your report.\n\n";
                            $message .= "Best regards,\n" . APP_NAME . " Team";
                            
                            sendEmail($reporter['email'], $subject, $message);
                        }
                    } catch (Exception $e) {
                        error_log("Resolution email failed: " . $e->getMessage());
                    }
                    
                    $success = "Incident resolved successfully!";
                }
            } catch (Exception $e) {
                $error = 'Error resolving incident: ' . $e->getMessage();
                error_log("Incident resolution error: " . $e->getMessage());
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