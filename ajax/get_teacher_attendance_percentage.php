<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Teacher') {
    die("Unauthorized");
}
require_once __DIR__ . '/../config.php';

$session = isset($_GET['session']) ? $_GET['session'] : 'AM';
$advisory_section = $_SESSION['user']['advisory_section'];
$today = date('Y-m-d');
$statusColumn = ($session === 'AM') ? 'morning_status' : 'afternoon_status';

$totalStmt = $conn->prepare("SELECT COUNT(*) FROM student_tbl WHERE status = 'Active' AND year_section = :advisory_section");
$totalStmt->execute([':advisory_section' => $advisory_section]);
$totalStudents = $totalStmt->fetchColumn();

$presentStmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.student_lrn)
    FROM attendance_tbl a
    JOIN student_tbl s ON a.student_lrn = s.lrn
    WHERE a.date = :today AND a.$statusColumn = 'Present' AND s.year_section = :advisory_section
");
$presentStmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$presentCount = $presentStmt->fetchColumn();

$percentage = ($totalStudents > 0) ? round(($presentCount / $totalStudents) * 100) : 0;
echo $percentage;
?>