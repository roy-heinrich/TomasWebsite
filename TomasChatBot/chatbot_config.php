<?php
/**
 * TOMAS Chatbot Configuration
 * Secure API endpoint management
 */
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name && getenv($name) === false) {
            putenv("$name=$value");
            $_SERVER[$name] = $value;
        }
    }
}


// 🚨 SECURITY: Environment-based API configuration
class ChatbotConfig {
    
    // Default fallback URLs intentionally left empty to avoid embedding
    // public service endpoints in source control. Provide endpoints
    // via environment variables instead (see .env instructions below).
    private static $defaultUrls = [
        'primary' => '',
        'secondary' => '',
    ];
    /**
     * Get the best available endpoint with health checking
     */
    public static function getBestEndpoint() {
        $endpoints = self::getAllEndpoints();
        
        // Check each endpoint in order of preference
        foreach ($endpoints as $name => $url) {
            if (self::isApiAccessible($url . '/health')) {
                return $url;
            }
        }
        
        // Fallback to primary if all checks fail
        return self::getApiUrl();
    }
    
    /**
     * Get the primary API URL from environment or default
     */
    public static function getApiUrl() {
        // Prefer explicit aggregate variable first (single URL for all endpoints)
        $envUrl = getenv('CHATBOT_API_URL') ?: ($_SERVER['CHATBOT_API_URL'] ?? null);
        if (!empty($envUrl)) return rtrim($envUrl, '/');

        // Fallback to primary/secondary variables (use primary if set)
        $primary = getenv('CHATBOT_PRIMARY_URL') ?: ($_SERVER['CHATBOT_PRIMARY_URL'] ?? null);
        if (!empty($primary)) return rtrim($primary, '/');

        // As a last resort return empty string (do not embed service URLs here)
        return '';
    }
    
    /**
     * Get health check URL
     */
    public static function getHealthUrl() {
        return self::getApiUrl() . '/health';
    }
    
    /**
     * Get chat endpoint URL
     */
    public static function getChatUrl() {
        return self::getApiUrl() . '/chat';
    }
    
    /**
     * Get clear context URL
     */
    public static function getClearContextUrl() {
        return self::getApiUrl() . '/clear-context';
    }
    
    /**
     * Get all available endpoints for failover
     */
    public static function getAllEndpoints() {
        // Build endpoint list from environment variables; do not expose hard-coded URLs
        $list = [];
        $primary = getenv('CHATBOT_PRIMARY_URL') ?: ($_SERVER['CHATBOT_PRIMARY_URL'] ?? null);
        $secondary = getenv('CHATBOT_SECONDARY_URL') ?: ($_SERVER['CHATBOT_SECONDARY_URL'] ?? null);

        if (!empty($primary)) $list['primary'] = rtrim($primary, '/');
        if (!empty($secondary)) $list['secondary'] = rtrim($secondary, '/');

        // If nothing configured, fall back to any non-empty defaults (which are empty by default)
        foreach (self::$defaultUrls as $k => $v) {
            if (empty($list[$k]) && !empty($v)) $list[$k] = rtrim($v, '/');
        }

        return $list;
    }
    
    /**
     * Check if API is accessible
     */
    public static function isApiAccessible($url = null) {
        $url = $url ?: self::getHealthUrl();
        
        // Simple check without exposing the URL
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET'
            ]
        ]);
        
        $result = @file_get_contents($url, false, $context);
        return $result !== false;
    }
}

// 🚨 SECURITY: Hide sensitive information
if (!defined('CHATBOT_SECURE_MODE')) {
    define('CHATBOT_SECURE_MODE', true);
}

// Export configuration for use in widget
$chatbotApiUrl = ChatbotConfig::getApiUrl();
$chatbotHealthUrl = ChatbotConfig::getHealthUrl();
$chatbotChatUrl = ChatbotConfig::getChatUrl();
$chatbotClearUrl = ChatbotConfig::getClearContextUrl();
?>