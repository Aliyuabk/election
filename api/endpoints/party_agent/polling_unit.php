<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// For testing - return mock data
echo json_encode([
    'success' => true,
    'data' => [
        'id' => '1',
        'name' => 'KANGIRE YAMMA/AREWA/KANGIRE P.S',
        'code' => 'PU-001',
        'ward' => 'Kangire',
        'lga' => 'Birnin Kudu',
        'state' => 'Jigawa',
        'election' => '2027 Governorship Election',
        'coordinator' => 'Aliyu Abubakar'
    ]
]);
?>