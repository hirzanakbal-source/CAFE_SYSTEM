<?php
/**
 * ====================================================================
 * Cafe Digital - Configuration with Security Enhancements
 * ====================================================================
 * Rate Limiting | Input Validation | API Key Management
 * ====================================================================
 */

// ===== ENVIRONMENT CONFIGURATION =====
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'cafe_digital_system');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// ===== SECURITY CONFIGURATION =====
define('ENABLE_RATE_LIMITING', true);
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 900);
define('API_KEY_LENGTH', 64);
define('API_KEY_PREFIX', 'cafd_');

// ===== SESSION CONFIGURATION =====
define('SESSION_TIMEOUT', 1800);
define('SESSION_COOKIE_SECURE', true);
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Strict');

// ===== LOGGING =====
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_ERRORS', true);
define('LOG_API_CALLS', true);

// ===== INPUT VALIDATION RULES =====
const INPUT_RULES = [
    'email' => [
        'type' => 'email',
        'max_length' => 255,
        'required' => true,
    ],
    'password' => [
        'type' => 'string',
        'min_length' => 8,
        'max_length' => 128,
        'required' => true,
        'pattern' => '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,128}$/',
    ],
    'phone' => [
        'type' => 'string',
        'pattern' => '/^[0-9\-\+\s\(\)]{7,20}$/',
        'max_length' => 20,
    ],
    'name' => [
        'type' => 'string',
        'min_length' => 2,
        'max_length' => 100,
        'pattern' => '/^[a-zA-Z\s\-\'\.]{2,100}$/',
    ],
    'menu_name' => [
        'type' => 'string',
        'min_length' => 3,
        'max_length' => 150,
        'required' => true,
    ],
    'price' => [
        'type' => 'decimal',
        'min' => 0,
        'max' => 99999.99,
        'required' => true,
    ],
    'quantity' => [
        'type' => 'integer',
        'min' => 0,
        'max' => 10000,
        'required' => true,
    ],
    'coupon_code' => [
        'type' => 'string',
        'min_length' => 3,
        'max_length' => 50,
        'pattern' => '/^[A-Z0-9\-_]{3,50}$/',
        'required' => true,
    ],
];

// ===== CREATE LOGS DIRECTORY =====
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// ===== DATABASE CONNECTION =====
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die($e->getMessage());
}

// ===== RATE LIMITING CLASS =====
class RateLimiter {
    private $pdo;
    private $enabled;
    private $max_requests;
    private $time_window;

    public function __construct($pdo, $enabled = true, $max_requests = RATE_LIMIT_REQUESTS, $time_window = RATE_LIMIT_WINDOW) {
        $this->pdo = $pdo;
        $this->enabled = $enabled;
        $this->max_requests = $max_requests;
        $this->time_window = $time_window;
    }

    private function getClientId() {
        $ip = $this->getClientIp();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . $ua);
    }

    private function getClientIp() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function isLimited($identifier = null) {
        if (!$this->enabled) return false;

        $identifier = $identifier ?? $this->getClientId();
        $now = time();
        $window_start = $now - $this->time_window;

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limit (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(64) NOT NULL,
                    request_count INT DEFAULT 1,
                    window_start INT NOT NULL,
                    last_request INT NOT NULL,
                    INDEX idx_identifier (identifier),
                    INDEX idx_window (window_start)
                )
            ");

            $stmt = $this->pdo->prepare("
                SELECT request_count, window_start
                FROM rate_limit
                WHERE identifier = ? AND window_start > ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $window_start]);
            $record = $stmt->fetch();

            if (!$record) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO rate_limit (identifier, request_count, window_start, last_request)
                    VALUES (?, 1, ?, ?)
                ");
                $stmt->execute([$identifier, $now, $now]);
                return false;
            }

            $request_count = $record['request_count'] + 1;
            $stmt = $this->pdo->prepare("
                UPDATE rate_limit
                SET request_count = ?, last_request = ?
                WHERE identifier = ? AND window_start = ?
            ");
            $stmt->execute([$request_count, $now, $identifier, $record['window_start']]);

            if ($request_count > $this->max_requests) {
                $this->logRateLimitEvent($identifier, $request_count);
                return true;
            }

            return false;

        } catch (PDOException $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return false;
        }
    }

    public function getRemainingRequests($identifier = null) {
        if (!$this->enabled) return $this->max_requests;

        $identifier = $identifier ?? $this->getClientId();
        $now = time();
        $window_start = $now - $this->time_window;

        try {
            $stmt = $this->pdo->prepare("
                SELECT request_count
                FROM rate_limit
                WHERE identifier = ? AND window_start > ?
                LIMIT 1
            ");
            $stmt->execute([$identifier, $window_start]);
            $record = $stmt->fetch();

            if (!$record) {
                return $this->max_requests;
            }

            return max(0, $this->max_requests - $record['request_count']);

        } catch (PDOException $e) {
            error_log("Get remaining requests error: " . $e->getMessage());
            return $this->max_requests;
        }
    }

    private function logRateLimitEvent($identifier, $count) {
        $log_file = LOG_DIR . '/rate_limit.log';
        $log_entry = date('Y-m-d H:i:s') . " | Identifier: $identifier | Requests: $count | IP: " . $this->getClientIp() . "\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    public function cleanup() {
        $threshold = time() - ($this->time_window * 2);
        try {
            $this->pdo->prepare("DELETE FROM rate_limit WHERE window_start < ?")->execute([$threshold]);
        } catch (PDOException $e) {
            error_log("Rate limit cleanup error: " . $e->getMessage());
        }
    }
}

// ===== INPUT VALIDATOR CLASS =====
class InputValidator {
    public static function validate($input, $field_name) {
        if (!isset(INPUT_RULES[$field_name])) {
            return ['valid' => true];
        }

        $rules = INPUT_RULES[$field_name];

        if (!empty($rules['required']) && empty($input)) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' is required'];
        }

        if (empty($input)) {
            return ['valid' => true];
        }

        switch ($rules['type'] ?? 'string') {
            case 'email':
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    return ['valid' => false, 'error' => 'Invalid email format'];
                }
                break;

            case 'integer':
                if (!filter_var($input, FILTER_VALIDATE_INT)) {
                    return ['valid' => false, 'error' => 'Must be an integer'];
                }
                $input = (int)$input;
                break;

            case 'decimal':
                if (!filter_var($input, FILTER_VALIDATE_FLOAT)) {
                    return ['valid' => false, 'error' => 'Must be a decimal number'];
                }
                $input = (float)$input;
                break;

            case 'string':
                $input = (string)$input;
                break;
        }

        $length = strlen($input);
        if (isset($rules['min_length']) && $length < $rules['min_length']) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' must be at least ' . $rules['min_length'] . ' characters'];
        }
        if (isset($rules['max_length']) && $length > $rules['max_length']) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' must not exceed ' . $rules['max_length'] . ' characters'];
        }

        if (isset($rules['min']) && (float)$input < $rules['min']) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' must be at least ' . $rules['min']];
        }
        if (isset($rules['max']) && (float)$input > $rules['max']) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' must not exceed ' . $rules['max']];
        }

        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $input)) {
            return ['valid' => false, 'error' => ucfirst($field_name) . ' format is invalid'];
        }

        return ['valid' => true, 'value' => $input];
    }

    public static function validateMultiple($inputs) {
        $results = [];
        $all_valid = true;

        foreach ($inputs as $field => $value) {
            $result = self::validate($value, $field);
            $results[$field] = $result;
            if (!$result['valid']) {
                $all_valid = false;
            }
        }

        return ['valid' => $all_valid, 'results' => $results];
    }

    public static function sanitize($input, $type = 'string') {
        $input = (string)$input;

        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'integer':
                return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return (float)filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
    }
}

// ===== API KEY MANAGER CLASS =====
class ApiKeyManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function generateKey($user_id, $name = '', $permissions = []) {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS api_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    api_key VARCHAR(255) NOT NULL UNIQUE,
                    api_key_hash VARCHAR(255) NOT NULL UNIQUE,
                    user_id INT NOT NULL,
                    name VARCHAR(255),
                    permissions JSON,
                    is_active TINYINT DEFAULT 1,
                    last_used_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME,
                    INDEX idx_user (user_id),
                    INDEX idx_key_hash (api_key_hash)
                )
            ");

            $raw_key = bin2hex(random_bytes(32));
            $api_key = API_KEY_PREFIX . $raw_key;
            $api_key_hash = hash('sha256', $api_key);

            $stmt = $this->pdo->prepare("
                INSERT INTO api_keys (api_key_hash, user_id, name, permissions, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $api_key_hash,
                $user_id,
                $name ?: 'API Key ' . date('Y-m-d'),
                json_encode($permissions),
            ]);

            return [
                'success' => true,
                'api_key' => $api_key,
                'message' => 'API Key generated successfully. Save it in a secure location.',
            ];

        } catch (PDOException $e) {
            error_log("API key generation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to generate API key'];
        }
    }

    public function validateKey($api_key) {
        if (empty($api_key) || strpos($api_key, API_KEY_PREFIX) !== 0) {
            return ['valid' => false, 'error' => 'Invalid API key format'];
        }

        try {
            $api_key_hash = hash('sha256', $api_key);

            $stmt = $this->pdo->prepare("
                SELECT ak.id, ak.user_id, ak.permissions
                FROM api_keys ak
                WHERE ak.api_key_hash = ? AND ak.is_active = 1
                AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
                LIMIT 1
            ");

            $stmt->execute([$api_key_hash]);
            $record = $stmt->fetch();

            if (!$record) {
                return ['valid' => false, 'error' => 'Invalid or expired API key'];
            }

            $update_stmt = $this->pdo->prepare("
                UPDATE api_keys SET last_used_at = NOW() WHERE id = ?
            ");
            $update_stmt->execute([$record['id']]);

            return [
                'valid' => true,
                'user_id' => $record['user_id'],
                'permissions' => json_decode($record['permissions'], true) ?? [],
            ];

        } catch (PDOException $e) {
            error_log("API key validation error: " . $e->getMessage());
            return ['valid' => false, 'error' => 'Authentication failed'];
        }
    }

    public function listKeys($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name, is_active, last_used_at, created_at, expires_at
                FROM api_keys
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");

            $stmt->execute([$user_id]);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            error_log("List API keys error: " . $e->getMessage());
            return [];
        }
    }

    public function revokeKey($api_key_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE api_keys
                SET is_active = 0
                WHERE id = ? AND user_id = ?
            ");

            $result = $stmt->execute([$api_key_id, $user_id]);

            return [
                'success' => (bool)$result,
                'message' => $result ? 'API key revoked successfully' : 'Failed to revoke API key',
            ];

        } catch (PDOException $e) {
            error_log("Revoke API key error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to revoke API key'];
        }
    }

    public function hasPermission($api_key_id, $required_permission) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT permissions FROM api_keys WHERE id = ? LIMIT 1
            ");

            $stmt->execute([$api_key_id]);
            $record = $stmt->fetch();

            if (!$record) {
                return false;
            }

            $permissions = json_decode($record['permissions'], true) ?? [];
            return in_array($required_permission, $permissions) || in_array('*', $permissions);

        } catch (PDOException $e) {
            error_log("Check permission error: " . $e->getMessage());
            return false;
        }
    }
}

// ===== SECURITY LOGGER CLASS =====
class SecurityLogger {
    public static function log($event_type, $details = [], $severity = 'info') {
        $log_file = LOG_DIR . '/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_id = $_SESSION['customer_id'] ?? $_SESSION['admin_id'] ?? 'guest';

        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'event_type' => $event_type,
            'severity' => $severity,
            'user_id' => $user_id,
            'ip_address' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details,
        ]) . "\n";

        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    public static function logApiCall($endpoint, $method, $user_id = null, $response_code = 200) {
        if (!LOG_API_CALLS) return;

        $log_file = LOG_DIR . '/api_calls.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'endpoint' => $endpoint,
            'method' => $method,
            'user_id' => $user_id,
            'ip_address' => $ip,
            'response_code' => $response_code,
        ]) . "\n";

        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

// ===== INITIALIZE SECURITY COMPONENTS =====
$rate_limiter = new RateLimiter($pdo, ENABLE_RATE_LIMITING);
$api_key_manager = new ApiKeyManager($pdo);
$input_validator = new InputValidator();

// ===== PERIODIC CLEANUP =====
if (rand(1, 100) === 1) {
    $rate_limiter->cleanup();
}
?>
