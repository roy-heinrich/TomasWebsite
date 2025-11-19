<?php
require_once 'config.php'; 

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'];
$password = $data['password'];

try {
    // First get the exact email from database using case-insensitive search
    $stmt = $conn->prepare("SELECT email FROM staff_tbl WHERE email ILIKE :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
        exit;
    }
    
    $exact_email = $staff['email'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $update = $conn->prepare("UPDATE staff_tbl SET password = :password, reset_token = NULL, reset_token_expiry = NULL WHERE email = :email");
    $update->bindParam(':password', $hashed_password);
    $update->bindParam(':email', $exact_email);
    $update->execute();

    if ($update->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>