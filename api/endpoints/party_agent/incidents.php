<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return mock incidents
    echo json_encode([
        'success' => true,
        'data' => [
            [
                'id' => '1',
                'title' => 'Minor Disruption',
                'description' => 'A minor disruption was reported at PU-001. Situation has been resolved.',
                'location' => 'Kangire, Birnin Kudu',
                'type' => 'Violence',
                'date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'status' => 'resolved',
                'polling_unit_id' => '1'
            ],
            [
                'id' => '2',
                'title' => 'Material Delay',
                'description' => 'Election materials arrived 30 minutes late at PU-001.',
                'location' => 'Kangire, Birnin Kudu',
                'type' => 'Delay',
                'date' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'status' => 'reported',
                'polling_unit_id' => '1'
            ]
        ]
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'success' => true,
        'message' => 'Incident reported successfully',
        'data' => $input
    ]);
    exit;
}
?>