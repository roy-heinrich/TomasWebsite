<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}



require_once __DIR__ . '/../config.php';

// Always use today's date
$date = date('Y-m-d');

try {
    // Aggregate present sessions per section in a single query.
    // present_sessions counts morning+afternoon Present per section.
    $sql = "
        SELECT s.year_section AS section,
               COUNT(DISTINCT s.lrn) AS total_students,
               SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) +
               SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS present_sessions
        FROM student_tbl s
        LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :date
        WHERE s.status = 'Active' AND s.year_section IS NOT NULL
        GROUP BY s.year_section
        ORDER BY section ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date]);

    $sectionsData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total_students = (int)$row['total_students'];
        $present_sessions = (int)$row['present_sessions'];
        $total_sessions = $total_students * 2;
        $percentage = $total_sessions > 0 ? (int)round(($present_sessions / $total_sessions) * 100) : 0;

        $sectionsData[] = ['section' => $row['section'], 'percentage' => $percentage];
    }

    // Sort by percentage desc, then name
    usort($sectionsData, function($a, $b) {
        if ($b['percentage'] === $a['percentage']) return strcmp($a['section'], $b['section']);
        return $b['percentage'] - $a['percentage'];
    });

    $topSections = array_slice($sectionsData, 0, 5);

    $labels = array_map(function($r){ return $r['section']; }, $topSections);
    $percentages = array_map(function($r){ return $r['percentage']; }, $topSections);

    echo json_encode(['labels' => $labels, 'percentages' => $percentages]);
} catch (PDOException $e) {
    error_log("Database error in get_top_sections.php: " . $e->getMessage());
    echo json_encode(['labels' => [], 'percentages' => []]);
}
?>