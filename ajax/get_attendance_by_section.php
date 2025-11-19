<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}


require_once __DIR__ . '/../config.php';

// Only show today's data
$date = date('Y-m-d');

try {
    // Aggregate in a single query per section to avoid N+1 queries
    $sql = "
        SELECT
            COALESCE(s.year_section, '') AS section,
            COUNT(*) AS total_students,
            SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) AS am_present,
            SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS pm_present
        FROM student_tbl s
        LEFT JOIN attendance_tbl a
            ON a.student_lrn = s.lrn AND a.date = :date
        WHERE s.status = 'Active'
          AND s.year_section IS NOT NULL
        GROUP BY s.year_section
        ORDER BY s.year_section ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date]);

    $labels = [];
    $amPercentages = [];
    $pmPercentages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total = (int)$row['total_students'];
        $amPresent = (int)$row['am_present'];
        $pmPresent = (int)$row['pm_present'];

        $amPct = $total > 0 ? (int)round(($amPresent / $total) * 100) : 0;
        $pmPct = $total > 0 ? (int)round(($pmPresent / $total) * 100) : 0;

        $labels[] = $row['section'];
        $amPercentages[] = $amPct;
        $pmPercentages[] = $pmPct;
    }

    echo json_encode([
        'labels' => $labels,
        'am_percentages' => $amPercentages,
        'pm_percentages' => $pmPercentages
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_attendance_by_section.php: " . $e->getMessage());
    echo json_encode([
        'labels' => [],
        'am_percentages' => [],
        'pm_percentages' => []
    ]);
}
?>