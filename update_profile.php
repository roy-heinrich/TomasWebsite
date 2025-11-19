<?php
require_once 'session.php';
require_once 'config.php';

// Fallback for redirect
$referer = $_SERVER['HTTP_REFERER'] ?? 'Admin_Dashboard.php';
$host = $_SERVER['HTTP_HOST'];
$redirectBack = (parse_url($referer, PHP_URL_HOST) === $host || parse_url($referer, PHP_URL_HOST) === null) ? $referer : 'Admin_Dashboard.php';

// Get form values
$id = $_POST['id'];
$fullname = $_POST['fullname'];
$username = $_POST['username'];
$email = $_POST['email'];
$currentPassword = $_POST['current_password'];
$newPassword = $_POST['new_password'];
$newPasswordHashed = null;

try {
    // Fetch existing user data
    $stmt = $conn->prepare("SELECT * FROM staff_tbl WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() !== 1) {
        $_SESSION['error'] = "User not found.";
        header("Location: $redirectBack");
        exit;
    }
    
    $user = $stmt->fetch();

    // 1. Username validation
    if (strlen($username) < 8) {
        $_SESSION['error'] = "Username must be at least 8 characters.";
        header("Location: $redirectBack");
        exit;
    }

    // 2. Check for duplicate username/email
    $checkStmt = $conn->prepare("SELECT * FROM staff_tbl WHERE (username = :username OR email = :email) AND id != :id");
    $checkStmt->bindParam(':username', $username);
    $checkStmt->bindParam(':email', $email);
    $checkStmt->bindParam(':id', $id);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        $_SESSION['error'] = "Username or Email is already in use.";
        header("Location: $redirectBack");
        exit;
    }

    // 3. Password change
    if (!empty($newPassword)) {
        if (!password_verify($currentPassword, $user['password'])) {
            $_SESSION['error'] = "Current password is incorrect.";
            header("Location: $redirectBack");
            exit;
        }

        if (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/', $newPassword)) {
            $_SESSION['error'] = "New password must be at least 10 characters, include 1 uppercase letter, 1 number, and 1 special character.";
            header("Location: $redirectBack");
            exit;
        }

        $newPasswordHashed = password_hash($newPassword, PASSWORD_DEFAULT);
    }

    // 4. Handle profile image upload to Supabase
    $profileImage = $user['profile_image']; // default is current image

    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = $_FILES['profile_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize = 25 * 1024 * 1024;

        if (!in_array($_FILES['profile_image']['type'], ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])) {
            $_SESSION['error'] = "Invalid image format. Only JPG, JPEG, PNG, WEBP allowed.";
            header("Location: $redirectBack");
            exit;
        }

        if ($_FILES['profile_image']['size'] > $maxSize) {
            $_SESSION['error'] = "Image size exceeds 25MB.";
            header("Location: $redirectBack");
            exit;
        }

        // Delete old image from Supabase if it exists and is not default
        if (!empty($profileImage)) {
            deleteFromSupabase($profileImage);
        }

        // Generate unique filename and upload to Supabase
        $newFileName = uniqid() . '.' . $fileExtension;
        if (uploadToSupabase($fileTmpPath, $newFileName)) {
            $profileImage = $newFileName;
        } else {
            $_SESSION['error'] = "Failed to upload image to storage.";
            header("Location: $redirectBack");
            exit;
        }
    }

    // 5. Update user info
    $updateFields = "fullname = :fullname, username = :username, email = :email, profile_image = :profile_image";
    $params = [
        ':fullname' => $fullname,
        ':username' => $username,
        ':email' => $email,
        ':profile_image' => $profileImage,
        ':id' => $id
    ];

    if ($newPasswordHashed) {
        $updateFields .= ", password = :password";
        $params[':password'] = $newPasswordHashed;
    }

    $updateStmt = $conn->prepare("UPDATE staff_tbl SET $updateFields WHERE id = :id");
    $updateStmt->execute($params);

    if ($updateStmt->rowCount() > 0) {
        $_SESSION['user']['fullname'] = $fullname;
        $_SESSION['user']['username'] = $username;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['profile_image'] = $profileImage;

        $_SESSION['success'] = "Profile updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update profile.";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

header("Location: $redirectBack");
exit;
?>