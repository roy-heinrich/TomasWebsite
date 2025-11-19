<?php 
date_default_timezone_set('Asia/Manila');
require_once 'session.php';
// Require application session: only logged-in Admin/Teacher may access
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_role'] ?? '', ['Admin','Teacher'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Already Recorded</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            padding: 15px;
        }
        
        .already-container {
            background: white;
            width: 100%;
            max-width: 550px;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .info-icon {
            font-size: 85px;
            color: #FBBC05;
            margin-bottom: 15px;
            animation: bounce 1s infinite alternate;
        }
        
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-15px); }
        }
        
        h1 {
            color: #1a73e8;
            font-size: 2.2rem;
            margin-bottom: 15px;
        }
        
        .message {
            font-size: 1.3rem;
            color: #202124;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .attendance-details {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            margin-bottom: 12px;
            font-size: 1.1rem;
        }
        
        .detail-label {
            font-weight: bold;
            color: #1a73e8;
            width: 140px;
        }
        
        .detail-value {
            color: #202124;
            flex: 1;
        }
        
        .back-btn {
            background: #1a73e8;
            color: white;
            border: none;
            padding: 14px 35px;
            font-size: 1.1rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.4);
        }
        
        .back-btn:hover {
            background: #0d47a1;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(26, 115, 232, 0.6);
        }
        
        .back-btn:active {
            transform: translateY(0);
        }
        
        .auto-return {
            font-size: 1rem;
            color: #5f6368;
            margin-top: 25px;
        }
        
        .highlight {
            color: #1a73e8;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .already-container {
                padding: 25px 20px;
            }
            
            h1 {
                font-size: 1.9rem;
            }
            
            .message {
                font-size: 1.1rem;
            }
            
            .detail-item {
                font-size: 1rem;
            }
            
            .detail-label {
                width: 130px;
            }
        }
        
        @media (max-width: 480px) {
            .already-container {
                padding: 20px 15px;
            }
            
            .info-icon {
                font-size: 70px;
            }
            
            h1 {
                font-size: 1.7rem;
            }
            
            .message {
                font-size: 1rem;
            }
            
            .attendance-details {
                padding: 15px;
            }
            
            .detail-item {
                font-size: 0.95rem;
                flex-direction: column;
                margin-bottom: 15px;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .back-btn {
                padding: 12px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="already-container">
        <div class="info-icon">
            <i class="fas fa-info-circle"></i>
        </div>
        
        <h1>Attendance Already Recorded</h1>
        
        <div class="message">
            Your attendance for this period has already been recorded in the system.
        </div>
        
        <div class="attendance-details">
            <div class="detail-item">
                <span class="detail-label">Period:</span>
                <span class="detail-value"><?php
                    $now = date('H:i');
                    $windows = [
                        'am_login' => ['start' => '06:00', 'end' => '08:30'],
                        'am_logout' => ['start' => '10:00', 'end' => '12:30'],
                        'pm_login' => ['start' => '12:30', 'end' => '13:30'],
                        'pm_logout' => ['start' => '15:00', 'end' => '17:30'],
                    ];
                    $in = function($t, $s, $e) { return ($t >= $s && $t <= $e); };
                    if ($in($now, $windows['am_login']['start'], $windows['am_login']['end'])) {
                        $period = 'AM (Login)';
                    } elseif ($in($now, $windows['am_logout']['start'], $windows['am_logout']['end'])) {
                        $period = 'AM (Logout)';
                    } elseif ($in($now, $windows['pm_login']['start'], $windows['pm_login']['end'])) {
                        $period = 'PM (Login)';
                    } elseif ($in($now, $windows['pm_logout']['start'], $windows['pm_logout']['end'])) {
                        $period = 'PM (Logout)';
                    } else {
                        if ($now < $windows['am_login']['start']) {
                            $period = 'AM (Login)';
                        } elseif ($now > $windows['pm_logout']['end']) {
                            $period = 'PM (Logout)';
                        } elseif ($now > $windows['am_logout']['end'] && $now < $windows['pm_login']['start']) {
                            $period = 'AM (Logout)';
                        } else {
                            $period = 'AM (Login)';
                        }
                    }
                    echo $period;
                ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo date('F j, Y'); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status:</span>
                <span class="detail-value">Already Marked Present</span>
            </div>
        </div>
        
        <div class="auto-return">
            Returning to scanner in <span class="highlight">5 seconds</span>...
        </div>
        
        <button class="back-btn" id="alreadyBackBtn">
            <i class="fas fa-arrow-left mr-2"></i>Back to Scanner
        </button>
    </div>

    <script>
        (function(){
            var scannerUrl = 'barcode_scanner.php';
            var dashboardUrl = 'login.php';
            <?php if (isset($_SESSION['user']) && ($_SESSION['user']['user_role'] ?? '') === 'Admin'): ?>
                dashboardUrl = 'Admin_Dashboard.php';
            <?php elseif (isset($_SESSION['user']) && ($_SESSION['user']['user_role'] ?? '') === 'Teacher'): ?>
                dashboardUrl = 'Teacher_Account.php';
            <?php endif; ?>

            document.getElementById('alreadyBackBtn').addEventListener('click', function(){
                window.location.href = scannerUrl;
            });

            // Auto-return to scanner after delay
            setTimeout(function(){ window.location.href = scannerUrl; }, 5000);
        })();
    </script>
</body>
</html>