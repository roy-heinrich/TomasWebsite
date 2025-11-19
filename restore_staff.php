<?php

require_once 'session.php';
require_once 'config.php'; // $conn is PDO

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffId = intval($_POST['staff_id'] ?? 0);

    try {
        $stmt = $conn->prepare("UPDATE staff_tbl SET status = 'Active' WHERE id = :id");
        $stmt->execute([':id' => $staffId]);

        if ($stmt->rowCount() > 0) {
            echo "success";
        } else {
            echo "error";
        }
    } catch (Exception $e) {
        echo "error";
    }
} else {
    echo "error";
}
?>