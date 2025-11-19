<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}

require_once __DIR__ . '/../config.php';

$session = isset($_GET['session']) ? $_GET['session'] : 'AM';

// Calculate today's date
$today = date('Y-m-d');

// Determine which column to check based on session
$statusColumn = ($session === 'AM') ? 'morning_status' : 'afternoon_status';

try {
    // Get total active students
    $stmt = $conn->query("SELECT COUNT(*) FROM student_tbl WHERE status = 'Active'");
    $totalStudents = $stmt->fetchColumn();

    // Get present count
    $stmt = $conn->prepare("SELECT COUNT(*) FROM attendance_tbl WHERE date = :today AND $statusColumn = 'Present'");
    $stmt->bindParam(':today', $today);
    $stmt->execute();
    $presentCount = $stmt->fetchColumn();

    // Calculate percentage
    $percentage = ($totalStudents > 0) ? round(($presentCount / $totalStudents) * 100) : 0;

    echo $percentage;
} catch (PDOException $e) {
    error_log("Database error in get_todays_attendance_percentage.php: " . $e->getMessage());
    echo "Error";
}
?>