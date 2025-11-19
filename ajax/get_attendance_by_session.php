<?php
require_once __DIR__ . '/../session.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}



require_once __DIR__ . '/../config.php';

$period = isset($_GET['period']) ? $_GET['period'] : 'today';
$grade = isset($_GET['grade']) ? $_GET['grade'] : 'All';

try {
    // Determine date range
    switch ($period) {
        case 'week':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case 'today':
        default:
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
    }

    // Build query. IMPORTANT:
    // For the 'today' period we must NOT put the date filter in the WHERE clause
    // because that converts the LEFT JOIN into an INNER JOIN and excludes students
    // with no attendance rows for the day. Instead apply the date filter inside
    // the JOIN so all active students are counted and attendance values are NULL
    // when no row exists for that student/date.
    if ($period === 'today') {
        $query = "
            SELECT 
                SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) AS am_present,
                SUM(CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END) AS am_absent,
                SUM(CASE WHEN a.morning_status IS NULL THEN 1 ELSE 0 END) AS am_not_marked,
                SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS pm_present,
                SUM(CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END) AS pm_absent,
                SUM(CASE WHEN a.afternoon_status IS NULL THEN 1 ELSE 0 END) AS pm_not_marked
            FROM student_tbl s
            LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :start_date
            WHERE s.status = 'Active'
        ";

        $params = [':start_date' => $startDate];
    } else {
        // For range queries we'll aggregate attendance rows but restrict students
        // using LEFT JOIN and date bounds applied to the joined table to avoid
        // converting the join into an inner join.
        $query = "
            SELECT 
                SUM(CASE WHEN a.morning_status = 'Present' THEN 1 ELSE 0 END) AS am_present,
                SUM(CASE WHEN a.morning_status = 'Absent' THEN 1 ELSE 0 END) AS am_absent,
                SUM(CASE WHEN a.morning_status IS NULL THEN 0 ELSE 0 END) AS am_not_marked,
                SUM(CASE WHEN a.afternoon_status = 'Present' THEN 1 ELSE 0 END) AS pm_present,
                SUM(CASE WHEN a.afternoon_status = 'Absent' THEN 1 ELSE 0 END) AS pm_absent,
                SUM(CASE WHEN a.afternoon_status IS NULL THEN 0 ELSE 0 END) AS pm_not_marked
            FROM student_tbl s
            LEFT JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date >= :start_date AND a.date <= :end_date
            WHERE s.status = 'Active'
        ";

        $params = [':start_date' => $startDate, ':end_date' => $endDate];
    }

    if ($grade !== 'All') {
        $query .= " AND s.year_section = :grade";
        $params[':grade'] = $grade;
    }

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return raw counts instead of percentages
    echo json_encode([
        'am' => [
            'present' => (int)$data['am_present'],
            'absent' => (int)$data['am_absent'],
            'not_marked' => (int)$data['am_not_marked']
        ],
        'pm' => [
            'present' => (int)$data['pm_present'],
            'absent' => (int)$data['pm_absent'],
            'not_marked' => (int)$data['pm_not_marked']
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_attendance_by_session.php: " . $e->getMessage());
    echo json_encode([
        'am' => [
            'present' => 0,
            'absent' => 0,
            'not_marked' => 0
        ],
        'pm' => [
            'present' => 0,
            'absent' => 0,
            'not_marked' => 0
        ]
    ]);
}
?>