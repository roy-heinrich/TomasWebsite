<?php

require_once 'config.php'; // provides $conn (PDO)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
    $section = trim($_POST['section']);

    try {
        $stmt = $conn->prepare("SELECT id, fullname FROM staff_tbl WHERE advisory_section = :section AND user_role = 'Teacher' AND status = 'Active' ORDER BY fullname ASC");
        $stmt->execute([':section' => $section]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $teachers = [];
        foreach ($rows as $r) {
            $teachers[] = ['id' => $r['id'], 'name' => $r['fullname']];
        }

        header('Content-Type: application/json');
        echo json_encode($teachers);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

header('HTTP/1.1 400 Bad Request');
echo json_encode(['error' => 'Invalid request']);