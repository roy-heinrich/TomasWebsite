<?php
require_once __DIR__ . '/../session.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    die("Unauthorized");
}



require_once __DIR__ . '/../config.php';

$grade = isset($_GET['grade']) ? $_GET['grade'] : 'All';

try {
    // Build query
    $query = "SELECT 
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) AS male,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) AS female
        FROM student_tbl
        WHERE status = 'Active'";
    
    $params = [];
    
    if ($grade !== 'All') {
        $query .= " AND year_section = :grade";
        $params[':grade'] = $grade;
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetch();

    // Return raw counts instead of percentages
    echo json_encode([
        'male' => (int)$data['male'],
        'female' => (int)$data['female']
    ]);
} catch (PDOException $e) {
    error_log("Database error in get_gender_distribution.php: " . $e->getMessage());
    echo json_encode([
        'male' => 0,
        'female' => 0
    ]);
}
?>