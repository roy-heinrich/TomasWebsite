<?php
// AJAX endpoint: return absent sessions count for a student within a date range
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../session.php';
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';

// Only allow logged-in teachers (and admins optionally)
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_role'] ?? '', ['Teacher','Admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$teacher = $_SESSION['user'];
$advisory_section = $teacher['advisory_section'] ?? null;

$student_lrn = $_POST['student_lrn'] ?? '';
$date_from = $_POST['date_from'] ?? '';
$date_to = $_POST['date_to'] ?? '';

// Basic validation
if (empty($student_lrn) || empty($date_from) || empty($date_to)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// Normalize dates
$from = date('Y-m-d', strtotime($date_from));
$to = date('Y-m-d', strtotime($date_to));
if ($from > $to) {
    // swap
    $tmp = $from; $from = $to; $to = $tmp;
}

// No fixed client-side limit: rely on DB index (idx_attendance_tbl_lrn_date) for performance.
// If needed in future, make this configurable via config.php and add logging for very large ranges.

// Verify student belongs to teacher's advisory section (if teacher role)
if (($teacher['user_role'] ?? '') === 'Teacher' && $advisory_section) {
    $sStmt = $conn->prepare("SELECT 1 FROM student_tbl WHERE lrn = :lrn AND year_section = :ys AND status = 'Active' LIMIT 1");
    $sStmt->execute([':lrn' => $student_lrn, ':ys' => $advisory_section]);
    $ok = $sStmt->fetchColumn();
    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Student not found in your section']);
        exit;
    }
}

// Efficient count: sum of absent morning + absent afternoon within date range
$countSql = "SELECT COALESCE(SUM(
    (CASE WHEN morning_status = 'Absent' THEN 1 ELSE 0 END) +
    (CASE WHEN afternoon_status = 'Absent' THEN 1 ELSE 0 END)
),0) AS absent_sessions
FROM attendance_tbl
WHERE student_lrn = :lrn
  AND date BETWEEN :from AND :to";

$stmt = $conn->prepare($countSql);
$stmt->execute([':lrn' => $student_lrn, ':from' => $from, ':to' => $to]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$count = isset($res['absent_sessions']) ? (int)$res['absent_sessions'] : 0;

echo json_encode(['success' => true, 'absent_sessions' => $count]);
exit;

?>
