<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}



require_once __DIR__ . '/../config.php';

$grade = isset($_GET['grade']) ? $_GET['grade'] : 'All';
$session = isset($_GET['session']) ? $_GET['session'] : 'AM';

$today = date('Y-m-d');
$statusColumn = ($session === 'AM') ? 'morning_status' : 'afternoon_status';

try {
    // Build where clause
    $query = "
        SELECT 
            SUM(CASE WHEN a.$statusColumn = 'Present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.$statusColumn = 'Absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.$statusColumn IS NULL THEN 1 ELSE 0 END) AS not_marked_count,
            COUNT(*) AS total_students
        FROM student_tbl s
        LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
        WHERE s.status = 'Active'
    ";
    
    $params = [':today' => $today];
    
    if ($grade !== 'All') {
        $query .= " AND s.year_section = :grade";
        $params[':grade'] = $grade;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetch();

    // Check if any attendance has been recorded
    $hasData = ($data['present_count'] > 0 || $data['absent_count'] > 0);

    // Calculate percentages
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
} catch (PDOException $e) {
    error_log("Database error in get_attendance_today_distribution.php: " . $e->getMessage());
    echo json_encode([
        'present' => 0,
        'absent' => 0,
        'not_marked' => 0,
        'present_count' => 0,
        'absent_count' => 0,
        'not_marked_count' => 0,
        'has_data' => false,
        'total_students' => 0
    ]);
}
?>