<?php
/**
 * Linkfy - Authentication API
 * Kullanıcı kaydı, girişi ve oturum yönetimi
 */

require_once 'config.php';

setCorsHeaders();

// Rate limiting check
$clientIP = getClientIP();
checkRateLimit($clientIP, 'auth');

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// POST /api/auth.php?action=register
// POST /api/auth.php?action=login
// GET  /api/auth.php?action=me
// POST /api/auth.php?action=logout

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handleRegister($db, $input);
        break;
        
    case 'login':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handleLogin($db, $input);
        break;
        
    case 'me':
        if ($method !== 'GET') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handleGetCurrentUser($db);
        break;
        
    case 'logout':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handleLogout();
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Kullanıcı Kaydı
 */
function handleRegister($db, $input) {
    // Validation
    if (empty($input['email']) || empty($input['password']) || empty($input['username'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Tüm alanları doldurun'
        ], 400);
    }
    
    $email = sanitizeInput($input['email']);
    $password = $input['password'];
    $username = sanitizeInput($input['username']);
    
    // Email validation
    if (!isValidEmail($email)) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz e-posta adresi'
        ], 400);
    }
    
    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        jsonResponse([
            'success' => false,
            'message' => 'Kullanıcı adı 3-20 karakter olmalı ve sadece harf, rakam ve alt çizgi içerebilir'
        ], 400);
    }
    
    // Password validation
    if (strlen($password) < 6) {
        jsonResponse([
            'success' => false,
            'message' => 'Şifre en az 6 karakter olmalıdır'
        ], 400);
    }
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Bu e-posta adresi zaten kayıtlı'
        ], 400);
    }
    
    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Bu kullanıcı adı zaten kullanılıyor'
        ], 400);
    }
    
    // Create user
    $hashedPassword = hashPassword($password);
    $stmt = $db->prepare("INSERT INTO users (email, password, username, plan) VALUES (?, ?, ?, 'free')");
    
    try {
        $stmt->execute([$email, $hashedPassword, $username]);
        $userId = $db->lastInsertId();
        
        // Create token
        $token = createToken($userId, $email);
        
        jsonResponse([
            'success' => true,
            'message' => 'Kayıt başarılı',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'username' => $username,
                    'plan' => 'free'
                ],
                'token' => $token
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("Registration Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Kayıt sırasında bir hata oluştu'
        ], 500);
    }
}

/**
 * Kullanıcı Girişi
 */
function handleLogin($db, $input) {
    // Validation
    if (empty($input['email']) || empty($input['password'])) {
        jsonResponse([
            'success' => false,
            'message' => 'E-posta ve şifre gereklidir'
        ], 400);
    }
    
    $email = sanitizeInput($input['email']);
    $password = $input['password'];
    
    // Get user
    $stmt = $db->prepare("SELECT id, email, password, username, plan FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse([
            'success' => false,
            'message' => 'E-posta veya şifre hatalı'
        ], 401);
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password'])) {
        jsonResponse([
            'success' => false,
            'message' => 'E-posta veya şifre hatalı'
        ], 401);
    }
    
    // Create token
    $token = createToken($user['id'], $user['email']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Giriş başarılı',
        'data' => [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'plan' => $user['plan']
            ],
            'token' => $token
        ]
    ]);
}

/**
 * Mevcut Kullanıcı Bilgisi
 */
function handleGetCurrentUser($db) {
    $userData = requireAuth();
    
    // Get user details
    $stmt = $db->prepare("SELECT id, email, username, plan, created_at FROM users WHERE id = ?");
    $stmt->execute([$userData['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse([
            'success' => false,
            'message' => 'Kullanıcı bulunamadı'
        ], 404);
    }
    
    // Get user stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM links WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $linkCount = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM bio_pages WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $bioCount = $stmt->fetch()['total'];
    
    $stmt = $db->prepare("SELECT SUM(clicks) as total FROM links WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $totalClicks = $stmt->fetch()['total'] ?: 0;
    
    jsonResponse([
        'success' => true,
        'data' => [
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'],
                'plan' => $user['plan'],
                'created_at' => $user['created_at']
            ],
            'stats' => [
                'links' => (int)$linkCount,
                'bio_pages' => (int)$bioCount,
                'total_clicks' => (int)$totalClicks
            ]
        ]
    ]);
}

/**
 * Çıkış
 */
function handleLogout() {
    // Token-based auth kullanıldığı için client-side'da token silinecek
    jsonResponse([
        'success' => true,
        'message' => 'Çıkış başarılı'
    ]);
}