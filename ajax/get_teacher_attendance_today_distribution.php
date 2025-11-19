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

$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN a.$statusColumn = 'Present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.$statusColumn = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN a.$statusColumn IS NULL THEN 1 ELSE 0 END) AS not_marked_count,
        COUNT(*) AS total_students
    FROM student_tbl s
    LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
    WHERE s.year_section = :advisory_section AND s.status = 'Active'
");
$stmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$data = $stmt->fetch();

$hasData = ($data['present_count'] > 0 || $data['absent_count'] > 0);
$total = $data['present_count'] + $data['absent_count'] + $data['not_marked_count'];
if ($total > 0) {
    $presentPct = round(($data['present_count'] / $total) * 100);
    $absentPct = round(($data['absent_count'] / $total) * 100);
    $notMarkedPct = 100 - $presentPct - $absentPct;
} else {
    $presentPct = $absentPct = $notMarkedPct = 0;
}

echo json_encode([
    'present' => $presentPct,
    'absent' => $absentPct,
    'not_marked' => $notMarkedPct,
    'present_count' => $data['present_count'],
    'absent_count' => $data['absent_count'],
    'not_marked_count' => $data['not_marked_count'],
    'has_data' => $hasData,
    'total_students' => $data['total_students']
]);
?>