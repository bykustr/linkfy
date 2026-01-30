<?php
/**
 * Linkfy - Yapılandırma Dosyası
 * Veritabanı bağlantısı ve site ayarları
 */

// Veritabanı Ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'linkfy_db');          // Veritabanı adınızı buraya yazın
define('DB_USER', 'linkfy_user');        // Veritabanı kullanıcı adınızı buraya yazın
define('DB_PASS', 'your_password_here'); // Veritabanı şifrenizi buraya yazın
define('DB_CHARSET', 'utf8mb4');

// Site Ayarları
define('SITE_URL', 'https://linkfy.tr'); // Domain adresinizi buraya yazın (sonunda / olmadan)
define('SITE_NAME', 'Linkfy');
define('ADMIN_EMAIL', 'admin@linkfy.tr');

// Güvenlik Ayarları
define('JWT_SECRET', 'your_jwt_secret_key_here_change_this'); // Güvenli bir anahtar üretin
define('SESSION_LIFETIME', 86400); // 24 saat (saniye cinsinden)

// Rate Limiting Ayarları
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // İstek sayısı
define('RATE_LIMIT_WINDOW', 3600);  // Zaman penceresi (saniye) - 1 saat
define('RATE_LIMIT_BAN_TIME', 1800); // Ban süresi (saniye) - 30 dakika

// Plan Limitleri
define('FREE_PLAN_LINKS', 10);
define('FREE_PLAN_BIO_PAGES', 1);
define('PREMIUM_PLAN_LINKS', -1); // -1 = sınırsız
define('PREMIUM_PLAN_BIO_PAGES', -1);

// Kısa Link Ayarları
define('SHORT_CODE_LENGTH', 6);

// Hata Raporlama (production'da kapatın)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Production'da 0 yapın
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

// Timezone
date_default_timezone_set('Europe/Istanbul');

/**
 * Veritabanı Bağlantısı
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Veritabanı bağlantı hatası. Lütfen daha sonra tekrar deneyin.'
            ]));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Bağlantı kopyalanmasını engelle
    private function __clone() {}
    public function __wakeup() {}
}

/**
 * Helper Functions
 */

// JSON Response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// CORS Headers
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Input Sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate Email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate URL
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Generate Random String
function generateRandomString($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get Client IP
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Create JWT Token
function createToken($userId, $email) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $userId,
        'email' => $email,
        'iat' => time(),
        'exp' => time() + SESSION_LIFETIME
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

// Verify JWT Token
function verifyToken($token) {
    if (empty($token)) {
        return false;
    }
    
    $tokenParts = explode('.', $token);
    if (count($tokenParts) !== 3) {
        return false;
    }
    
    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if ($base64UrlSignature !== $signatureProvided) {
        return false;
    }
    
    $payloadData = json_decode($payload, true);
    
    if ($payloadData['exp'] < time()) {
        return false;
    }
    
    return $payloadData;
}

// Get Authorization Token from Header
function getBearerToken() {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

// Require Authentication
function requireAuth() {
    $token = getBearerToken();
    $userData = verifyToken($token);
    
    if (!$userData) {
        jsonResponse([
            'success' => false,
            'message' => 'Yetkisiz erişim. Lütfen giriş yapın.'
        ], 401);
    }
    
    return $userData;
}

/**
 * Rate Limiting
 */
function checkRateLimit($identifier, $endpoint = 'api') {
    if (!RATE_LIMIT_ENABLED) {
        return true;
    }
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if blocked
        $stmt = $db->prepare("
            SELECT blocked_until FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$identifier, $endpoint]);
        $blocked = $stmt->fetch();
        
        if ($blocked) {
            $remainingTime = strtotime($blocked['blocked_until']) - time();
            jsonResponse([
                'success' => false,
                'message' => 'Çok fazla istek. Lütfen ' . ceil($remainingTime / 60) . ' dakika sonra tekrar deneyin.',
                'retry_after' => $remainingTime
            ], 429);
        }
        
        // Get or create rate limit record
        $stmt = $db->prepare("
            SELECT * FROM rate_limits 
            WHERE identifier = ? AND endpoint = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $endpoint, RATE_LIMIT_WINDOW]);
        $limit = $stmt->fetch();
        
        if ($limit) {
            // Update request count
            $newCount = $limit['requests'] + 1;
            
            if ($newCount > RATE_LIMIT_REQUESTS) {
                // Block user
                $blockedUntil = date('Y-m-d H:i:s', time() + RATE_LIMIT_BAN_TIME);
                $stmt = $db->prepare("
                    UPDATE rate_limits 
                    SET requests = ?, blocked_until = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newCount, $blockedUntil, $limit['id']]);
                
                jsonResponse([
                    'success' => false,
                    'message' => 'Rate limit aşıldı. ' . (RATE_LIMIT_BAN_TIME / 60) . ' dakika engellendiniz.',
                    'retry_after' => RATE_LIMIT_BAN_TIME
                ], 429);
            }
            
            $stmt = $db->prepare("UPDATE rate_limits SET requests = ? WHERE id = ?");
            $stmt->execute([$newCount, $limit['id']]);
        } else {
            // Create new record
            $stmt = $db->prepare("
                INSERT INTO rate_limits (identifier, endpoint, requests, window_start) 
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE requests = 1, window_start = NOW(), blocked_until = NULL
            ");
            $stmt->execute([$identifier, $endpoint]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Rate Limit Error: " . $e->getMessage());
        return true; // Don't block on error
    }
}