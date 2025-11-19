<?php
// Load from environment variables (production) or .env file (local development)
// Priority: environment variables > .env file
function getEnv($key, $default = null) {
    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }
    // Fall back to .env file for local development
    static $env = null;
    if ($env === null) {
        $envFile = __DIR__ . '/.env';
        $env = file_exists($envFile) ? parse_ini_file($envFile) : [];
    }
    return $env[$key] ?? $default;
}

define('DB_HOST', getEnv('DB_HOST'));
define('DB_PORT', getEnv('DB_PORT'));
define('DB_USER', getEnv('DB_USER'));
define('DB_PASSWORD', getEnv('DB_PASSWORD'));
define('DB_NAME', getEnv('DB_NAME'));

define('MAIL_HOST', getEnv('MAIL_HOST'));
define('MAIL_PORT', getEnv('MAIL_PORT'));
define('MAIL_USERNAME', getEnv('MAIL_USERNAME'));
define('MAIL_PASSWORD', getEnv('MAIL_PASSWORD'));
define('MAIL_ENCRYPTION', getEnv('MAIL_ENCRYPTION'));
define('MAIL_FROM_ADDRESS', getEnv('MAIL_FROM_ADDRESS'));
define('MAIL_FROM_NAME', getEnv('MAIL_FROM_NAME'));

// Supabase Storage configuration
define('SUPABASE_STORAGE_KEY', getEnv('SUPABASE_STORAGE_KEY'));
define('SUPABASE_URL', getEnv('SUPABASE_URL'));

// default bucket for profile images (used by update_profile.php)
define('SUPABASE_BUCKET', 'profile_images');
// bucket for welcome/teacher images
define('SUPABASE_BUCKET_WELC', 'welc_profile');

// Create PostgreSQL connection with PDO
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $conn = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Hide credentials and error details
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Connection Error</title>
        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                color: #2c3e50;
            }
            
            .error-container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                padding: 40px;
                text-align: center;
                max-width: 450px;
                width: 90%;
                animation: fadeIn 0.8s ease-out;
            }
            
            .icon-container {
                margin-bottom: 25px;
            }
            
            .connection-icon {
                font-size: 60px;
                color: #3498db;
                animation: pulse 2s infinite ease-in-out;
            }
            
            .spinner {
                width: 70px;
                height: 70px;
                border: 5px solid rgba(52, 152, 219, 0.2);
                border-radius: 50%;
                border-top-color: #3498db;
                animation: rotate 1.5s linear infinite;
                margin: 0 auto;
            }
            
            h1 {
                margin: 0 0 15px 0;
                font-weight: 600;
                color: #34495e;
            }
            
            p {
                margin: 0;
                line-height: 1.6;
                color: #7f8c8d;
            }
            
            .action-button {
                margin-top: 25px;
                background: #3498db;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 50px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
            }
            
            .action-button:hover {
                background: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 6px 8px rgba(52, 152, 219, 0.3);
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='icon-container'>
                <div class='spinner'></div>
            </div>
            <h1>Connection Issue</h1>
            <p>We're having trouble connecting to our services. This might be due to internet connectivity issues.</p>
            <p>Please check your connection and try again.</p>
            <button class='action-button' onclick='window.location.reload()'>Try Again</button>
        </div>
    </body>
    </html>";
    exit;
}

// Supabase Storage helper functions (bucket parameter optional)
function uploadToSupabase($filePath, $objectName, $bucket = SUPABASE_BUCKET) {
    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $objectName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Use PUT to upload/replace an object
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($filePath));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SUPABASE_STORAGE_KEY,
        'Content-Type: application/octet-stream',
        'x-upsert: true'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

function deleteFromSupabase($objectName, $bucket = SUPABASE_BUCKET) {
    $url = SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $objectName;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . SUPABASE_STORAGE_KEY
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 204 || ($httpCode >= 200 && $httpCode < 300);
}

function getSupabaseUrl($objectName, $bucket = SUPABASE_BUCKET) {
    if (empty($objectName)) return '';
    return SUPABASE_URL . '/storage/v1/object/public/' . $bucket . '/' . $objectName;
}


// Chatbot DB connection
try {
    $chatbot_dsn = "pgsql:host=" . getEnv('CHATBOT_DB_HOST') . ";port=" . getEnv('CHATBOT_DB_PORT') . ";dbname=" . getEnv('CHATBOT_DB_NAME');
    $chatbot_conn = new PDO($chatbot_dsn, getEnv('CHATBOT_DB_USER'), getEnv('CHATBOT_DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Same error HTML as above
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Connection Error</title>
        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            @keyframes rotate {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                color: #2c3e50;
            }
            
            .error-container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                padding: 40px;
                text-align: center;
                max-width: 450px;
                width: 90%;
                animation: fadeIn 0.8s ease-out;
            }
            
            .icon-container {
                margin-bottom: 25px;
            }
            
            .connection-icon {
                font-size: 60px;
                color: #3498db;
                animation: pulse 2s infinite ease-in-out;
            }
            
            .spinner {
                width: 70px;
                height: 70px;
                border: 5px solid rgba(52, 152, 219, 0.2);
                border-radius: 50%;
                border-top-color: #3498db;
                animation: rotate 1.5s linear infinite;
                margin: 0 auto;
            }
            
            h1 {
                margin: 0 0 15px 0;
                font-weight: 600;
                color: #34495e;
            }
            
            p {
                margin: 0;
                line-height: 1.6;
                color: #7f8c8d;
            }
            
            .action-button {
                margin-top: 25px;
                background: #3498db;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 50px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 6px rgba(52, 152, 219, 0.2);
            }
            
            .action-button:hover {
                background: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 6px 8px rgba(52, 152, 219, 0.3);
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='icon-container'>
                <div class='spinner'></div>
            </div>
            <h1>Connection Issue</h1>
            <p>We're having trouble connecting to our services. This might be due to internet connectivity issues.</p>
            <p>Please check your connection and try again.</p>
            <button class='action-button' onclick='window.location.reload()'>Try Again</button>
        </div>
    </body>
    </html>";
    exit;
}

// Chatbot file-storage helpers and 'chatbot-docs' bucket removed per request.
?>