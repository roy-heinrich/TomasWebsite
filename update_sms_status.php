
<?php
date_default_timezone_set('Asia/Manila');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sms_id = intval($_POST['sms_id'] ?? 0);
    if ($sms_id > 0) {
        require_once 'config.php'; // provides PDO $conn
        try {

          $sent_at = date('Y-m-d H:i:s');
$stmt = $conn->prepare("UPDATE sms_queue SET status = 'sent', sent_at = :sent_at WHERE id = :id");
$stmt->execute([':id' => $sms_id, ':sent_at' => $sent_at]);
            echo $stmt->rowCount() > 0 ? "ok" : "fail";
        } catch (Exception $ex) {
            echo "error";
        }
    } else {
        echo "invalid";
    }
}
?>