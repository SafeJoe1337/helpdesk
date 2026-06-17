<?php
require 'db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Define the path to your Python executable and script
$pythonPath = 'python'; // Adjust if needed (e.g., 'C:\Python310\python.exe')
$scriptPath = 'd:\\xamp\\htdocs\\Helpdesk\\bridge_analysis.py';

// Execute the script and capture the output. 
// We add 2>&1 to redirect errors to the output so we can see them in the dashboard.
$command = "$pythonPath \"$scriptPath\" 2>&1";
$output = shell_exec($command);

if ($output === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute local AI analysis script.']);
    exit;
}

// Find the first occurrence of '{' and the last occurrence of '}'
$json_start = strpos($output, '{');
$json_end = strrpos($output, '}');

if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
    // Extract the substring that is likely the JSON object
    $json_string = substr($output, $json_start, $json_end - $json_start + 1);
    
    // Attempt to decode it to validate
    $decoded_json = json_decode($json_string, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo $json_string; // Output the clean JSON
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Extracted string is not valid JSON', 'raw' => $output, 'extracted' => $json_string, 'json_error' => json_last_error_msg()]);
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'No valid JSON structure found in script output', 'raw' => $output]);
}
