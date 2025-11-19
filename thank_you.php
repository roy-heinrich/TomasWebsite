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
    <title>Attendance Recorded</title>
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
        
        .thank-you-container {
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
        
        .success-icon {
            font-size: 85px;
            color: #34A853;
            margin-bottom: 15px;
            animation: scale 0.5s ease-in-out;
        }
        
        @keyframes scale {
            0% { transform: scale(0.5); opacity: 0; }
            70% { transform: scale(1.2); }
            100% { transform: scale(1); opacity: 1; }
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
        
        .student-info {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 15px;
            margin: 20px 0;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 12px;
            font-size: 1.1rem;
        }
        
        .info-label {
            font-weight: bold;
            color: #1a73e8;
            width: 140px;
        }
        
        .info-value {
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
        
        .time-stamp {
            font-size: 1rem;
            color: #5f6368;
            margin-top: 25px;
        }
        
        .highlight {
            color: #1a73e8;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .thank-you-container {
                padding: 25px 20px;
            }
            
            h1 {
                font-size: 1.9rem;
            }
            
            .message {
                font-size: 1.1rem;
            }
            
            .info-item {
                font-size: 1rem;
            }
            
            .info-label {
                width: 130px;
            }
        }
        
        @media (max-width: 480px) {
            .thank-you-container {
                padding: 20px 15px;
            }
            
            .success-icon {
                font-size: 70px;
            }
            
            h1 {
                font-size: 1.7rem;
            }
            
            .message {
                font-size: 1rem;
            }
            
            .student-info {
                padding: 15px;
            }
            
            .info-item {
                font-size: 0.95rem;
                flex-direction: column;
                margin-bottom: 15px;
            }
            
            .info-label {
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
    <div class="thank-you-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Attendance Recorded!</h1>
        
        <div class="message">
            Your attendance has been successfully recorded in the system.
        </div>
        
        <div class="student-info">
            <div class="info-item">
                <span class="info-label">Time Recorded:</span>
                <span class="info-value"><?php echo date('h:i A'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Date:</span>
                <span class="info-value"><?php echo date('F j, Y'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Period:</span>
                <span class="info-value"><?php
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
                        // fallback to the same rules used by the scanner/logger
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
        </div>
        
        <div class="time-stamp">
            You can scan again in <span class="highlight">5 seconds</span>...
        </div>
        
        <button class="back-btn" id="thankBackBtn">
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

            // Back button returns to the scanner (embedded flow). If you prefer dashboard, change target.
            document.getElementById('thankBackBtn').addEventListener('click', function(){
                window.location.href = scannerUrl;
            });

            // After delay, return to scanner so user can scan again. If you want to return to dashboard instead,
            // replace scannerUrl with dashboardUrl.
            setTimeout(function(){ window.location.href = scannerUrl; }, 5000);
        })();
    </script>
</body>
</html>