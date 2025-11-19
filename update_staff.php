<?php

require_once 'session.php';
require_once 'config.php'; // $conn is PDO

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['user']['user_role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $staff_id = intval($_POST['staff_id']);
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));  // Convert to lowercase
        $status = $_POST['status'] ?? 'Active';
        $advisory = isset($_POST['advisory']) ? trim($_POST['advisory']) : null;

        if (strlen($username) < 8) {
            throw new Exception("Username must be at least 8 characters.");
        }

        // Check for username/email duplicates with case-insensitive email comparison
        $stmt = $conn->prepare("SELECT id FROM staff_tbl WHERE (username = :username OR LOWER(email) = LOWER(:email)) AND id != :id");
        $stmt->execute([':username' => $username, ':email' => $email, ':id' => $staff_id]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Username or Email already exists!");
        }

        // Get current image and role
        $stmt = $conn->prepare("SELECT profile_image, user_role FROM staff_tbl WHERE id = :id");
        $stmt->execute([':id' => $staff_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            throw new Exception("Staff not found.");
        }
        $current_image = $staff['profile_image'];
        $currentRole = $staff['user_role'];

        // Check for duplicate advisory section
        if ($currentRole == 'Teacher' && !empty($advisory)) {
            $checkStmt = $conn->prepare("SELECT id FROM staff_tbl 
                                        WHERE advisory_section = :advisory 
                                        AND user_role = 'Teacher' 
                                        AND status != 'Archived'
                                        AND id != :id");
            $checkStmt->execute([':advisory' => $advisory, ':id' => $staff_id]);
            if ($checkStmt->rowCount() > 0) {
                throw new Exception("Advisory section is already assigned to another teacher.");
            }
        }

        // Handle profile image - upload to Supabase
        $profile_image_name = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['profile_image']['tmp_name'];
            $fileName = $_FILES['profile_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $safeFileName = uniqid() . '.' . $fileExtension;

            $allowedExtensions = ['jpeg', 'jpg', 'png', 'webp'];
            $allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $fileType = $_FILES['profile_image']['type'];
            $fileSize = $_FILES['profile_image']['size'];
            $maxSize = 25 * 1024 * 1024;

            if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMime)) {
                throw new Exception("Only JPEG, JPG, PNG, and WEBP files are allowed.");
            }

            if ($fileSize > $maxSize) {
                throw new Exception("Profile image size must not exceed 25MB.");
            }

            // Delete old image from Supabase if it exists and is not a URL (ui-avatars)
            if (!empty($current_image) && !filter_var($current_image, FILTER_VALIDATE_URL)) {
                deleteFromSupabase($current_image);
            }

            if (!uploadToSupabase($fileTmpPath, $safeFileName)) {
                throw new Exception("Failed to upload image to storage.");
            }

            $profile_image_name = $safeFileName;
        }

        // Build update query
        $sql = "UPDATE staff_tbl SET fullname = :fullname, username = :username, email = :email, status = :status, advisory_section = :advisory";
        $params = [
            ':fullname' => $fullname,
            ':username' => $username,
            ':email' => $email,
            ':status' => $status,
            ':advisory' => $advisory,
            ':id' => $staff_id
        ];

        if ($profile_image_name) {
            $sql .= ", profile_image = :profile_image";
            $params[':profile_image'] = $profile_image_name;
        }

        $sql .= " WHERE id = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $response['success'] = true;
        $response['message'] = "Staff updated successfully!";
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
?>