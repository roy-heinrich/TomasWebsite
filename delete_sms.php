<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sms_id = intval($_POST['sms_id'] ?? 0);
    if ($sms_id > 0) {
        require_once 'config.php'; // provides PDO $conn
        try {
            $stmt = $conn->prepare("UPDATE sms_queue SET status = 'deleted' WHERE id = :id");
            $stmt->execute([':id' => $sms_id]);
            echo $stmt->rowCount() > 0 ? "ok" : "fail";
        } catch (Exception $ex) {
            echo "error";
        }
    } else {
        echo "invalid";
    }
}
?>