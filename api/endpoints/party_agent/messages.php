<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return mock messages
    echo json_encode([
        'success' => true,
        'data' => [
            [
                'id' => '1',
                'sender_id' => '2',
                'sender_name' => 'Coordinator',
                'receiver_id' => '1',
                'content' => 'Good morning! Please send your observations for PU-001',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'is_from_me' => false
            ],
            [
                'id' => '2',
                'sender_id' => '1',
                'sender_name' => 'Me',
                'receiver_id' => '2',
                'content' => 'All observations submitted. Everything is going smoothly.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'is_from_me' => true
            ],
            [
                'id' => '3',
                'sender_id' => '2',
                'sender_name' => 'Coordinator',
                'receiver_id' => '1',
                'content' => 'Great! Keep up the good work.',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
                'is_from_me' => false
            ]
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => $input
    ]);
    exit;
}
?>