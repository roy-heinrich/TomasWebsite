<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ERROR | E_PARSE);

require_once 'session.php';
header('Content-Type: application/json');

// Only allow POST and only allow authenticated Admin
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_role'], ['Admin', 'Teacher'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}



require_once 'config.php';
require_once 'mark_absent_students.php';

try {
    // Give this script extra time & memory if needed; safe for manual trigger
    if (function_exists('set_time_limit')) set_time_limit(300); // 5 minutes
    ini_set('memory_limit', '512M');

    // Run the heavy function
    mark_absent_students($conn);
    echo json_encode(['success' => true, 'message' => 'Absences processed successfully']);
} catch (Exception $e) {
    http_response_code(500);
    // Log and return sanitized message
    error_log('run_mark_absent error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while processing absences']);
}

?>