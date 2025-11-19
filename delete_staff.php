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

        // Get profile image
        $stmt = $conn->prepare("SELECT profile_image FROM staff_tbl WHERE id = :id");
        $stmt->execute([':id' => $staffId]);
        $staffData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Delete the staff member
        $delStmt = $conn->prepare("DELETE FROM staff_tbl WHERE id = :id");
        $delStmt->execute([':id' => $staffId]);

        if ($delStmt->rowCount() > 0) {
            // Delete the profile image from Supabase if stored there (not external URL)
            if ($staffData && !empty($staffData['profile_image']) && !filter_var($staffData['profile_image'], FILTER_VALIDATE_URL)) {
                deleteFromSupabase($staffData['profile_image']);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Staff member permanently deleted']);
        } else {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete staff member']);
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>