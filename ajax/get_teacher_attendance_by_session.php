<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Teacher') {
    die("Unauthorized");
}
require_once __DIR__ . '/../config.php';

$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$advisory_section = $_SESSION['user']['advisory_section'];

// Determine date range
switch ($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        break;
    case 'today':
    default:
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
}

// For the 'today' period we must apply the date filter inside the LEFT JOIN
// so students without an attendance row for today are still returned (their
// attendance columns will be NULL and should count as Not Marked). For range
// periods (week/month) we keep a broader approach but note that per-student
// aggregation may be required for more accurate metrics.

// Get total students in advisory section for percentage calculation
$totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM student_tbl WHERE year_section = :advisory_section AND status = 'Active'");
$totalStmt->execute([':advisory_section' => $advisory_section]);
$totalStudents = (int)$totalStmt->fetchColumn();

// Combine AM and PM aggregates into a single query to reduce round-trips
if ($period === 'today') {
    $sql = "
        SELECT
            SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) AS am_present,
            SUM(CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END) AS am_absent,
            SUM(CASE WHEN a.morning_status IS NULL THEN 1 ELSE 0 END) AS am_not_marked,
            SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS pm_present,
            SUM(CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END) AS pm_absent,
            SUM(CASE WHEN a.afternoon_status IS NULL THEN 1 ELSE 0 END) AS pm_not_marked
        FROM student_tbl s
        LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :startDate
        WHERE s.year_section = :advisory_section AND s.status = 'Active'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':startDate' => $startDate, ':advisory_section' => $advisory_section]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $amData = ['present' => $row['am_present'], 'absent' => $row['am_absent'], 'not_marked' => $row['am_not_marked']];
    $pmData = ['present' => $row['pm_present'], 'absent' => $row['pm_absent'], 'not_marked' => $row['pm_not_marked']];
} else {
    // Range aggregation (week/month) - apply date bounds on the JOIN
    $sql = "
        SELECT
            SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) AS am_present,
            SUM(CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END) AS am_absent,
            SUM(CASE WHEN a.morning_status IS NULL THEN 0 ELSE 0 END) AS am_not_marked,
            SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS pm_present,
            SUM(CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END) AS pm_absent,
            SUM(CASE WHEN a.afternoon_status IS NULL THEN 0 ELSE 0 END) AS pm_not_marked
        FROM student_tbl s
        LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date >= :startDate AND a.date <= :endDate
        WHERE s.year_section = :advisory_section AND s.status = 'Active'
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate, ':advisory_section' => $advisory_section]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $amData = ['present' => $row['am_present'], 'absent' => $row['am_absent'], 'not_marked' => 0];
    $pmData = ['present' => $row['pm_present'], 'absent' => $row['pm_absent'], 'not_marked' => 0];
}

// Calculate percentages if there are students
if ($totalStudents > 0) {
    $amPresentPercent = round(($amData['present'] / $totalStudents) * 100);
    $amAbsentPercent = round(($amData['absent'] / $totalStudents) * 100);
    $amNotMarkedPercent = 100 - $amPresentPercent - $amAbsentPercent;

    $pmPresentPercent = round(($pmData['present'] / $totalStudents) * 100);
    $pmAbsentPercent = round(($pmData['absent'] / $totalStudents) * 100);
    $pmNotMarkedPercent = 100 - $pmPresentPercent - $pmAbsentPercent;
} else {
    $amPresentPercent = $amAbsentPercent = $amNotMarkedPercent = 0;
    $pmPresentPercent = $pmAbsentPercent = $pmNotMarkedPercent = 0;
}

echo json_encode([
    'am' => [
        'present' => $amPresentPercent,
        'absent' => $amAbsentPercent,
        'not_marked' => $amNotMarkedPercent,
        'present_count' => (int)$amData['present'],
        'absent_count' => (int)$amData['absent'],
        'not_marked_count' => (int)$amData['not_marked'],
        'total_students' => (int)$totalStudents
    ],
    'pm' => [
        'present' => $pmPresentPercent,
        'absent' => $pmAbsentPercent,
        'not_marked' => $pmNotMarkedPercent,
        'present_count' => (int)$pmData['present'],
        'absent_count' => (int)$pmData['absent'],
        'not_marked_count' => (int)$pmData['not_marked'],
        'total_students' => (int)$totalStudents
    ]
]);
?>