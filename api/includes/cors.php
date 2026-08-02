<?php
// Enable CORS (Cross-Origin Resource Sharing)
// This is essential for allowing your React Native app to communicate with this API

// Allow requests from any origin (for development)
// In production, you should replace '*' with your specific app's origin
header("Access-Control-Allow-Origin: *");

// Allow these specific HTTP methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow these headers in requests
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Allow credentials (cookies, authorization headers) to be sent
header("Access-Control-Allow-Credentials: true");

// Set the max age for preflight requests (in seconds)
// This caches the preflight response for 1 hour to reduce repeated OPTIONS requests
header("Access-Control-Max-Age: 3600");

// Handle preflight (OPTIONS) requests
// A preflight request is sent by the browser before the actual request for security.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // For a simple OPTIONS request, just send the headers and exit.
    // We don't need to process any further PHP logic.
    http_response_code(200);
    exit;
}

?>