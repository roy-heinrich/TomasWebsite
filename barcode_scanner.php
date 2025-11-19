
<?php
require_once 'session.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_role'] ?? '', ['Admin','Teacher'])) {
    // Not authorized — redirect to main login
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: url('images/bluee.webp') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            overflow-y: auto;
        }

        .scanner-container {
            width: 100%;
            max-width: 1100px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 5px solid #1a73e8;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding: 0 10px;
        }

        .header-content {
            flex: 1;
            text-align: left;
        }

        .header-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }

        .school-logo {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #1a73e8;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }

        h1 {
            color: #1a73e8;
            font-size: 2.2rem;
            margin-bottom: 6px;
        }

        .subtitle {
            color: #5f6368;
            font-size: 1.2rem;
            font-weight: 500;
        }

        .datetime-container {
            background: none;
            padding: 0;
            margin: 0;
            box-shadow: none;
            color: #1a73e8;
            text-align: right;
        }

        .time {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .date, .day {
            font-size: 1.1rem;
        }

        .scanner-area {
            position: relative;
            margin: 10px 0;
            padding: 25px 20px;
            background: #f8f9fa;
            border-radius: 15px;
            border: 3px dashed #1a73e8;
        }

        .scan-line {
            position: absolute;
            top: 0;
            left: 10%;
            width: 80%;
            height: 4px;
            background: #1a73e8;
            box-shadow: 0 0 20px rgba(26, 115, 232, 0.8);
            animation: scan 3s infinite linear;
        }

        @keyframes scan {
            0% { top: 0; }
            50% { top: calc(100% - 4px); }
            100% { top: 0; }
        }

        .scanner-icon {
            font-size: 3.8rem;
            color: #1a73e8;
            margin-bottom: 10px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .instruction {
            font-size: 1.2rem;
            color: #202124;
            margin-bottom: 15px;
            font-weight: 500;
        }

        #barcode-input {
            width: 100%;
            max-width: 500px;
            height: 55px;
            padding: 14px 18px;
            font-size: 1.3rem;
            border: 3px solid #1a73e8;
            border-radius: 12px;
            text-align: center;
            outline: none;
            transition: all 0.3s;
            margin: 0 auto;
            display: block;
            box-shadow: 0 5px 15px rgba(26, 115, 232, 0.2);
        }

        #barcode-input:focus {
            border-color: #0d47a1;
            box-shadow: 0 0 20px rgba(13, 71, 161, 0.6);
            transform: scale(1.03);
        }

        .status-message {
            font-size: 1.2rem;
            margin-top: 15px;
            padding: 12px;
            border-radius: 10px;
            display: none;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }

        .footer {
            margin-top: 10px;
            color: #5f6368;
            font-size: 1rem;
        }

        .powered-by {
            font-weight: 600;
            color: #1a73e8;
            font-size: 1rem;
            margin-top: 5px;
        }

        .home-button {
            display: inline-block;
            margin-top: 5px;
            padding: 10px 18px;
            background-color: #1a73e8;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            border: none;
            /* Use a subtle bluish shadow instead of a harsh black outline */
            box-shadow: 0 6px 18px rgba(26, 115, 232, 0.12);
            transition: all 0.18s ease;
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        .home-button:hover {
            background-color: #0d47a1;
            box-shadow: 0 8px 22px rgba(13, 71, 161, 0.14);
            cursor: pointer;
        }

        /* Remove default browser focus outline and use a soft brand-colored focus ring */
        .home-button:focus,
        .home-button:active {
            outline: none;
            box-shadow: 0 0 0 4px rgba(26, 115, 232, 0.12);
        }

       @media (max-width: 768px) {
    .scanner-container {
        padding: 20px 12px;
    }

    .header {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }

    .header-content, .header-right {
        align-items: center;
        text-align: center;
    }

    .school-logo {
        width: 80px;
        height: 80px;
    }

    h1 {
        font-size: 1.4rem;
    }

    .subtitle {
        font-size: 0.9rem;
    }

    .datetime-container .time {
        font-size: 1.2rem;
    }

    .datetime-container .date,
    .datetime-container .day {
        font-size: 0.9rem;
    }

    .scanner-icon {
        font-size: 2.5rem;
    }

    .instruction {
        font-size: 1rem;
    }

    #barcode-input {
        font-size: 0.8rem;
        height: 45px;
        padding: 10px 14px;
    }

    .status-message {
        font-size: 1rem;
    }

    .footer {
        font-size: 0.85rem;
    }

    .powered-by {
        font-size: 0.85rem;
    }

    .home-button {
        font-size: 0.85rem;
        padding: 8px 14px;
    }
      .datetime-container {
        text-align: center;
    }
}

       /* Add these new styles for the confirmation modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            transform: translateY(-20px);
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-content h3 {
            color: #1a73e8;
            margin-bottom: 15px;
        }
        
        .modal-content p {
            margin-bottom: 20px;
            font-size: 1.1rem;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .modal-btn {
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            border: none;
        }
        
        .confirm-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .confirm-btn:hover {
            background-color: #bb2d3b;
        }
        
        .cancel-btn {
            background-color: #6c757d;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #5c636a;
        }
    </style>
</head>
<body>
    <div class="scanner-container">
        <div class="header">
            <img src="images/hdd.png" alt="School Logo" class="school-logo">
            <div class="header-content">
                <h1>Tomas SM. Bautista ES</h1>
                <div class="subtitle">Student Attendance System</div>
            </div>
            <div class="header-right">
                <div class="datetime-container">
                    <div class="time" id="current-time">00:00:00 AM</div>
                    <div class="date" id="current-date">January 1, 2023</div>
                    <div class="day" id="current-day">Monday</div>
                </div>
            </div>
        </div>

        <div class="scanner-area">
            <div class="scan-line"></div>
            <div class="scanner-icon"><i class="fas fa-barcode"></i></div>
            <div class="instruction">Scan Student Barcode</div>
            <input type="text" id="barcode-input" placeholder="Place barcode here or enter LRN" autocomplete="off" autofocus>
            <div id="status-message" class="status-message"></div>
        </div>

         <button type="button" class="home-button" id="backToDashboard"><i class="fas fa-arrow-left"></i> Back to Dashboard</button>

        <div class="footer">
            <p>Position barcode in front of the scanner or manually enter LRN</p>
            <p class="powered-by">Powered by TSM Bautista Attendance System</p>
        </div>
    </div>

    <!-- Back-to-dashboard button is handled by JS below (role-aware) -->

    <script>
        const PH_TIME_OFFSET = 8 * 60 * 60 * 1000;

        function updateDateTime() {
            const now = new Date();
            const phTime = new Date(now.getTime() + PH_TIME_OFFSET);
            let hours = phTime.getUTCHours();
            const minutes = phTime.getUTCMinutes().toString().padStart(2, '0');
            const seconds = phTime.getUTCSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            const hours12 = hours.toString().padStart(2, '0');
            document.getElementById('current-time').textContent = `${hours12}:${minutes}:${seconds} ${ampm}`;
            document.getElementById('current-date').textContent = phTime.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            document.getElementById('current-day').textContent = days[phTime.getUTCDay()];
        }

        updateDateTime();
        setInterval(updateDateTime, 1000);

        document.getElementById('barcode-input').addEventListener('input', function(e) {
            const barcode = e.target.value.trim();
            if (barcode.length === 12) {
                submitBarcode(barcode);
            }
        });

        // timer handle for auto-hiding status messages
        let statusHideTimer = null;

        // Show a status message. type can be: 'processing' (won't auto-hide), 'success', 'error', or undefined.
        function showStatusMessage(text, type) {
            const statusMessage = document.getElementById('status-message');
            statusMessage.style.display = 'block';
            statusMessage.textContent = text;
            // reset classes and apply base class
            statusMessage.className = 'status-message';
            if (type === 'success') statusMessage.classList.add('success');
            else if (type === 'error') statusMessage.classList.add('error');

            // clear any previous hide timer
            if (statusHideTimer) {
                clearTimeout(statusHideTimer);
                statusHideTimer = null;
            }

            // auto-hide after 5s for non-processing messages
            if (type !== 'processing') {
                statusHideTimer = setTimeout(() => {
                    statusMessage.style.display = 'none';
                    statusHideTimer = null;
                }, 5000);
            }
        }

        function hideStatusMessage() {
            const statusMessage = document.getElementById('status-message');
            statusMessage.style.display = 'none';
            if (statusHideTimer) {
                clearTimeout(statusHideTimer);
                statusHideTimer = null;
            }
        }

        function submitBarcode(lrn) {
            // show processing message and DO NOT auto-hide it
            showStatusMessage('Processing attendance...', 'processing');

            const formData = new FormData();
            formData.append('student_lrn', lrn);

            fetch('attendance_logger.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // show success and auto-hide after 5s
                    showStatusMessage(data.message, 'success');
                    setTimeout(() => window.location.href = 'thank_you.php', 1500);
                } else {
                    if (data.already_logged) {
                        setTimeout(() => window.location.href = 'already_logged.php', 1500);
                    }
                    // show error and auto-hide after 5s
                    showStatusMessage(data.message, 'error');
                }
                document.getElementById('barcode-input').value = '';
            })
            .catch(error => {
                // show error and auto-hide after 5s
                showStatusMessage('Error: ' + error.message, 'error');
                document.getElementById('barcode-input').value = '';
            });
        }

        function focusScanner() {
            document.getElementById('barcode-input').focus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            focusScanner();
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) focusScanner();
            });
            window.addEventListener('focus', focusScanner);
        });

        window.addEventListener('pageshow', function(event) {
            if (event.persisted) focusScanner();
        });


        // Role-aware Back button: Admin -> Admin_Dashboard.php, Teacher -> Teacher_Account.php
        (function(){
            var dashboardUrl = 'login.php';
            <?php if (isset($_SESSION['user']) && ($_SESSION['user']['user_role'] ?? '') === 'Admin'): ?>
                dashboardUrl = 'Admin_Dashboard.php';
            <?php elseif (isset($_SESSION['user']) && ($_SESSION['user']['user_role'] ?? '') === 'Teacher'): ?>
                dashboardUrl = 'Teacher_Account.php';
            <?php endif; ?>

            var btn = document.getElementById('backToDashboard');
            if (btn) {
                btn.addEventListener('click', function(){
                    window.location.href = dashboardUrl;
                });
            }
        })();
    </script>
</body>
</html>