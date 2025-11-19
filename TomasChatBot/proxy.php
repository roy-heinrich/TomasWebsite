<?php
// Simple proxy to forward requests from the client to the configured chatbot service.
// This keeps the external service URLs out of the client-side source.
require_once __DIR__ . '/chatbot_config.php';

// Allow longer execution for proxy to wait for backend responses
@set_time_limit(120);
@ini_set('default_socket_timeout', 120);

// Allow CORS for same-origin requests (adjust as needed)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'chat';

// Build list of target URLs to try. Prefer API_URL if set, otherwise try PRIMARY and SECONDARY
$targetsToTry = [];
$api = ChatbotConfig::getApiUrl();
if (!empty($api)) {
    $base = rtrim($api, '/');
    if ($action === 'health') $targetsToTry[] = $base . '/health';
    elseif ($action === 'clear') $targetsToTry[] = $base . '/clear-context';
    else $targetsToTry[] = $base . '/chat';
} else {
    $all = ChatbotConfig::getAllEndpoints();
    foreach ($all as $k => $v) {
        $v = rtrim($v, '/');
        if ($action === 'health') $targetsToTry[] = $v . '/health';
        elseif ($action === 'clear') $targetsToTry[] = $v . '/clear-context';
        else $targetsToTry[] = $v . '/chat';
    }
}

$body = file_get_contents('php://input');

if (empty($targetsToTry)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Chatbot service not configured on server.']);
    exit;
}

$lastErr = null;
$lastHttpCode = 502;
// try up to 3 attempts for transient errors (server-side retries)
$maxRetries = 3;
$attempts = [];

foreach ($targetsToTry as $t) {
    if (empty($t)) continue;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $t);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Connection: keep-alive',
            'User-Agent: Tomas-Chatbot-Proxy/1.0'
        ]);
        if (!empty($body)) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    // Increased timeouts to handle slower backend responses (60s total, 20s connect)
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        // Force HTTP/1.1 to avoid some HTTP/2 stalls on certain hosts
        if (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 502;
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Track attempt information
        $attemptInfo = ['url' => $t, 'attempt' => $attempt, 'http' => $httpCode, 'err' => $curlErr];
        $attempts[] = $attemptInfo;
        // file-based logging removed (was writing to TomasChatBot/logs/proxy.log)

        // Treat 2xx-3xx as success; allow 4xx to be passed through as server error
        if ($response !== false && $httpCode >= 200 && $httpCode < 400) {
            http_response_code($httpCode);
            header('Content-Type: application/json');
            echo $response;
            exit;
        }

        $lastErr = $curlErr ?: 'HTTP ' . $httpCode;
        $lastHttpCode = $httpCode;

        // small backoff before retrying
        if ($attempt < $maxRetries) {
            usleep(250000); // 250ms
        }
    }
}

// If we reached here, all attempts failed — return enriched JSON error for diagnostics
http_response_code(502);
header('Content-Type: application/json');
echo json_encode([
    'error' => 'Proxy request failed',
    'detail' => $lastErr,
    'http_code' => $lastHttpCode,
    'attempts' => $attempts,
    'env_chat_primary_set' => getenv('CHATBOT_PRIMARY_URL') !== false && getenv('CHATBOT_PRIMARY_URL') !== '',
    'env_chat_secondary_set' => getenv('CHATBOT_SECONDARY_URL') !== false && getenv('CHATBOT_SECONDARY_URL') !== '',
    'env_chat_api_set' => getenv('CHATBOT_API_URL') !== false && getenv('CHATBOT_API_URL') !== ''
]);