<?php
require 'db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'resident') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// For now, return same JSON structure as insights.php (admin AI insights)
// but scoped to resident's own reports.
$residentId = (int)$_SESSION['user_id'];

// Fetch resident reports text for analysis
$stmt = $pdo->prepare("SELECT title, description, category, status FROM reports WHERE user_id = ? OR assigned_to = ? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$residentId, $residentId]);
$rows = $stmt->fetchAll();

$payload = [
    'resident_id' => $residentId,
    'reports' => $rows,
];

$pythonPath = 'python';
$scriptPath = 'd:\\xamp\\htdocs\\Helpdesk\\bridge_analysis.py';

// We pass the payload json to the python script through stdin.
// The existing bridge_analysis.py already produces JSON to stdout.
$cmd = $pythonPath . ' "' . $scriptPath . '"';

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptorspec, $pipes);
if (!is_resource($process)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to run AI analysis script']);
    exit;
}

fwrite($pipes[0], json_encode($payload));
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);

fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($stdout === null || trim($stdout) === '') {
    http_response_code(500);
    echo json_encode(['error' => 'No output from AI script', 'stderr' => $stderr]);
    exit;
}

// Extract first json object
$json_start = strpos($stdout, '{');
$json_end = strrpos($stdout, '}');
if ($json_start === false || $json_end === false || $json_end <= $json_start) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON from AI script', 'raw' => $stdout, 'stderr' => $stderr]);
    exit;
}

$json_string = substr($stdout, $json_start, $json_end - $json_start + 1);
$decoded = json_decode($json_string, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Extracted string is not valid JSON', 'json_error' => json_last_error_msg(), 'raw' => $stdout]);
    exit;
}

echo $json_string;

