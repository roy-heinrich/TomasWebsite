<?php

require_once 'session.php';
require_once 'config.php'; // $conn is PDO

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['staff_id'])) {
    $staffId = (int)$_POST['staff_id'];

    try {
        $conn->beginTransaction();

        // 1. Check if staff is a teacher with assigned students
        $stmt = $conn->prepare("SELECT COUNT(*) FROM student_tbl WHERE assigned_teacher_id = :id");
        $stmt->execute([':id' => $staffId]);
        $studentCount = (int) $stmt->fetchColumn();

        if ($studentCount > 0) {
            $conn->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Cannot archive - this teacher has ' . $studentCount . ' assigned student(s). Please reassign them first.'
            ]);
            exit;
        }

        // 2. Archive the staff member
        $stmt = $conn->prepare("UPDATE staff_tbl SET status = 'Archived' WHERE id = :id");
        $stmt->execute([':id' => $staffId]);

        if ($stmt->rowCount() > 0) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Staff member archived successfully']);
        } else {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to archive staff member']);
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>