<?php
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php'; 

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'];

try {
    // Use ILIKE for case-insensitive comparison in PostgreSQL
    $stmt = $conn->prepare("SELECT fullname, status, email FROM staff_tbl WHERE email ILIKE :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
        exit;
    }

    if ($staff['status'] !== 'Active') {
        echo json_encode(['success' => false, 'message' => 'Account is not active.']);
        exit;
    }

    $verification_code = rand(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

    // Use the exact email from database for update (to preserve case)
    $exact_email = $staff['email'];
    
    $update = $conn->prepare("UPDATE staff_tbl SET reset_token = :token, reset_token_expiry = :expiry WHERE email = :email");
    $update->bindParam(':token', $verification_code);
    $update->bindParam(':expiry', $expiry);
    $update->bindParam(':email', $exact_email);
    $update->execute();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
        $mail->addAddress($exact_email, $staff['fullname']);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code';
        $mail->Body    = "Your verification code is: <strong>$verification_code</strong><br><br>This code will expire in 5 minutes.";

        $mail->send();

        echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Email could not be sent.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>