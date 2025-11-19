<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Teacher') {
    die("Unauthorized");
}
require_once __DIR__ . '/../config.php';

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
$advisory_section = $_SESSION['user']['advisory_section'];
$today = date('Y-m-d');

// Efficient count using indexed date and student_lrn
$countSql = "
        SELECT COUNT(*) as total
        FROM student_tbl s
        INNER JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
        WHERE s.year_section = :advisory_section
            AND s.status = 'Active'
            AND (
                    a.am_login_time IS NOT NULL OR a.am_logout_time IS NOT NULL OR
                    a.pm_login_time IS NOT NULL OR a.pm_logout_time IS NOT NULL
            )
";
$countStmt = $conn->prepare($countSql);
$countStmt->execute([':today' => $today, ':advisory_section' => $advisory_section]);
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

$offset = ($page - 1) * $perPage;

$dataSql = "
        SELECT s.lrn, a.date::text AS date, s.stud_name AS name, s.year_section AS section,
                     a.am_login_time::text AS am_login_time, a.am_logout_time::text AS am_logout_time,
                     a.pm_login_time::text AS pm_login_time, a.pm_logout_time::text AS pm_logout_time
        FROM student_tbl s
        INNER JOIN attendance_tbl a ON a.student_lrn = s.lrn AND a.date = :today
        WHERE s.year_section = :advisory_section
            AND s.status = 'Active'
            AND (
                    a.am_login_time IS NOT NULL OR a.am_logout_time IS NOT NULL OR
                    a.pm_login_time IS NOT NULL OR a.pm_logout_time IS NOT NULL
            )
        ORDER BY a.am_login_time DESC NULLS LAST, a.pm_login_time DESC NULLS LAST
        LIMIT :perPage OFFSET :offset
";

$stmt = $conn->prepare($dataSql);
$stmt->bindValue(':today', $today);
$stmt->bindValue(':advisory_section', $advisory_section);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>

