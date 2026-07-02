<?php
// get-subscription.php - AJAX endpoint for fetching subscription data
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM subscriptions WHERE id = ?");
$stmt->execute([$id]);
$subscription = $stmt->fetch();

if ($subscription) {
    echo json_encode(['success' => true, 'subscription' => $subscription]);
} else {
    echo json_encode(['success' => false, 'message' => 'Subscription not found']);
}
?>