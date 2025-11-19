<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}



require_once __DIR__ . '/../config.php';

$grade = isset($_GET['grade']) ? $_GET['grade'] : 'All';

// Calculate dates for the past 7 days
$dates = [];
$percentages = [];

try {
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[] = date('M j', strtotime($date));
        
        // Build query with parameter binding
        $query = "
            SELECT 
                (SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) +
                 SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END)) AS present_sessions,
                COUNT(DISTINCT s.student_id) * 2 AS total_sessions
            FROM student_tbl s
            LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :date
            WHERE s.status = 'Active'
        ";
        
        $params = [':date' => $date];
        
        if ($grade !== 'All') {
            $query .= " AND s.year_section = :grade";
            $params[':grade'] = $grade;
        }
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetch();
        
        $present = $data['present_sessions'] ?? 0;
        $total = $data['total_sessions'] ?? 0;
        
        $percentage = ($total > 0) ? round(($present / $total) * 100) : 0;
        $percentages[] = $percentage;
    }
    
    echo json_encode([
        'labels' => $dates,
        'percentages' => $percentages
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_attendance_trend.php: " . $e->getMessage());
    echo json_encode([
        'labels' => [],
        'percentages' => []
    ]);
}
?>