
<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Teacher') {
    die("Unauthorized");
}
require_once __DIR__ . '/../config.php';

$advisory_section = $_SESSION['user']['advisory_section'];
$dates = [];
$percentages = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('M j', strtotime($date));

    $stmt = $conn->prepare("
        SELECT 
            (SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) +
             SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END)) AS present_sessions,
            COUNT(DISTINCT s.student_id) * 2 AS total_sessions
        FROM student_tbl s
        LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :date
        WHERE s.status = 'Active' AND s.year_section = :advisory_section
    ");
    $stmt->execute([':date' => $date, ':advisory_section' => $advisory_section]);
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
?>