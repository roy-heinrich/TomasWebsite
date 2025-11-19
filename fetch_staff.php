<?php
require_once 'config.php'; // $conn is PDO
header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $staff_id = intval($_GET['id']);
    try {
        $sql = "SELECT * FROM staff_tbl WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $staff_id]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($staff) {
            // If profile_image is stored in Supabase (not URL) provide full public URL
            if (!empty($staff['profile_image']) && !filter_var($staff['profile_image'], FILTER_VALIDATE_URL)) {
                $staff['profile_image'] = getSupabaseUrl($staff['profile_image']);
            }
            echo json_encode($staff);
        } else {
            echo json_encode(['error' => 'Staff not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'No id provided']);
}
?>