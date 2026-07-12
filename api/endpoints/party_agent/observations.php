<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return mock observations
    echo json_encode([
        'success' => true,
        'data' => [
            [
                'id' => '1',
                'title' => 'Voting Process Observation',
                'description' => 'The voting process at PU-001 is smooth and orderly. Voters are following all guidelines.',
                'date' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'status' => 'submitted',
                'polling_unit_id' => '1'
            ],
            [
                'id' => '2',
                'title' => 'Voter Turnout',
                'description' => 'Good voter turnout observed at PU-001. Over 200 voters have already cast their votes.',
                'date' => date('Y-m-d H:i:s', strtotime('-4 hours')),
                'status' => 'submitted',
                'polling_unit_id' => '1'
            ],
            [
                'id' => '3',
                'title' => 'Material Availability',
                'description' => 'All election materials are available and in good condition.',
                'date' => date('Y-m-d H:i:s', strtotime('-6 hours')),
                'status' => 'draft',
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
        'message' => 'Observation submitted successfully',
        'data' => $input
    ]);
    exit;
}
?>