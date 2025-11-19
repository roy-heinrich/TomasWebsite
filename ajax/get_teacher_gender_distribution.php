<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user'])) {
    die("Unauthorized");
}
require_once __DIR__ . '/../config.php';

$advisory_section = $_SESSION['user']['advisory_section'];

$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female
    FROM student_tbl
    WHERE status = 'Active' AND year_section = :advisory_section
");
$stmt->execute([':advisory_section' => $advisory_section]);
$data = $stmt->fetch();

echo json_encode([
    'male' => (int)$data['male'],
    'female' => (int)$data['female']
]);
?>