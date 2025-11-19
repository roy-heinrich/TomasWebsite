<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}


require_once __DIR__ . '/../config.php';

$grade = isset($_GET['grade']) ? $_GET['grade'] : 'All';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;

$today = date('Y-m-d');

try {
    // Build WHERE clause for students that have any session timestamp on the date.
    $where = "s.status = 'Active'";
    $params = [':today' => $today];

    if ($grade !== 'All') {
        $where .= " AND s.year_section = :grade";
        $params[':grade'] = $grade;
    }

    // Use a CTE to compute matching attendance rows and then paginate efficiently
    $countSql = "
        SELECT COUNT(*)
        FROM student_tbl s
        INNER JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
        WHERE {$where} AND (
            a.am_login_time IS NOT NULL OR a.am_logout_time IS NOT NULL OR
            a.pm_login_time IS NOT NULL OR a.pm_logout_time IS NOT NULL
        )
    ";

    $countStmt = $conn->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($total / $perPage);

    $offset = ($page - 1) * $perPage;

    // Main data query: select raw values and let client format times
    $dataSql = "
        SELECT s.lrn,
               a.date::text AS date,
               s.stud_name AS name,
               s.year_section AS section,
               a.am_login_time::text AS am_login_time,
               a.am_logout_time::text AS am_logout_time,
               a.pm_login_time::text AS pm_login_time,
               a.pm_logout_time::text AS pm_logout_time
        FROM student_tbl s
        INNER JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
        WHERE {$where} AND (
            a.am_login_time IS NOT NULL OR a.am_logout_time IS NOT NULL OR
            a.pm_login_time IS NOT NULL OR a.pm_logout_time IS NOT NULL
        )
        ORDER BY a.am_login_time DESC NULLS LAST, a.am_logout_time DESC NULLS LAST, a.pm_login_time DESC NULLS LAST
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $conn->prepare($dataSql);
    // bind parameters
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();

    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    // Simple client-friendly formatting (avoid TO_CHAR in DB to allow index use)
    $formatted = array_map(function($r){
        return [
            'lrn' => $r['lrn'],
            'formatted_date' => date('F d, Y', strtotime($r['date'])),
            'name' => $r['name'],
            'section' => $r['section'],
            'am_login' => $r['am_login_time'] ? date('h:i A', strtotime($r['am_login_time'])) : '-',
            'am_logout' => $r['am_logout_time'] ? date('h:i A', strtotime($r['am_logout_time'])) : '-',
            'pm_login' => $r['pm_login_time'] ? date('h:i A', strtotime($r['pm_login_time'])) : '-',
            'pm_logout' => $r['pm_logout_time'] ? date('h:i A', strtotime($r['pm_logout_time'])) : '-'
        ];
    }, $rows);

    echo json_encode([
        'data' => $formatted,
        'total' => $total,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_todays_logged_attendance.php: " . $e->getMessage());
    echo json_encode([
        'data' => [],
        'total' => 0,
        'total_pages' => 0,
        'current_page' => 1,
        'per_page' => $perPage
    ]);
}
?>