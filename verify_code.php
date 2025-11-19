<?php
require_once 'config.php'; 

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'];
$code = $data['code'];

try {
    // Use ILIKE for case-insensitive comparison in PostgreSQL
    $stmt = $conn->prepare("SELECT reset_token, reset_token_expiry, email FROM staff_tbl WHERE email ILIKE :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff || $staff['reset_token'] !== $code) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification code.']);
        exit;
    }

    $current_time = date('Y-m-d H:i:s');
    if ($current_time > $staff['reset_token_expiry']) {
        echo json_encode(['success' => false, 'message' => 'Verification code has expired.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Verification successful.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>