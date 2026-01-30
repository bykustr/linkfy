<?php
/**
 * Linkfy - Password Reset API
 * Şifre sıfırlama işlemleri
 */

require_once 'config.php';

setCorsHeaders();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// POST /api/password-reset.php?action=request - Şifre sıfırlama talebi
// POST /api/password-reset.php?action=verify  - Token doğrulama
// POST /api/password-reset.php?action=reset   - Yeni şifre ayarlama

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'request':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handlePasswordResetRequest($db, $input);
        break;
        
    case 'verify':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handleTokenVerification($db, $input);
        break;
        
    case 'reset':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        handlePasswordReset($db, $input);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Şifre Sıfırlama Talebi
 */
function handlePasswordResetRequest($db, $input) {
    if (empty($input['email'])) {
        jsonResponse([
            'success' => false,
            'message' => 'E-posta adresi gereklidir'
        ], 400);
    }
    
    $email = sanitizeInput($input['email']);
    
    if (!isValidEmail($email)) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz e-posta adresi'
        ], 400);
    }
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Güvenlik için: E-posta bulunamasa bile başarılı mesajı göster
        jsonResponse([
            'success' => true,
            'message' => 'Eğer bu e-posta kayıtlıysa, şifre sıfırlama linki gönderildi'
        ]);
    }
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Save token
    try {
        $stmt = $db->prepare("
            INSERT INTO password_resets (email, token, expires_at) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$email, $token, $expiresAt]);
        
        // Send email (gerçek uygulamada PHPMailer kullanın)
        $resetLink = SITE_URL . "/reset-password.html?token=" . $token;
        
        // Email gönderme simülasyonu (gerçekte PHPMailer ile gönderin)
        $emailSent = sendPasswordResetEmail($email, $user['username'], $resetLink);
        
        jsonResponse([
            'success' => true,
            'message' => 'Şifre sıfırlama linki e-posta adresinize gönderildi',
            'debug' => [
                'reset_link' => $resetLink, // Production'da kaldırın!
                'token' => $token // Production'da kaldırın!
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Password Reset Request Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
        ], 500);
    }
}

/**
 * Token Doğrulama
 */
function handleTokenVerification($db, $input) {
    if (empty($input['token'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Token gereklidir'
        ], 400);
    }
    
    $token = sanitizeInput($input['token']);
    
    $stmt = $db->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz veya süresi dolmuş token'
        ], 400);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Token geçerli',
        'data' => [
            'email' => $reset['email']
        ]
    ]);
}

/**
 * Yeni Şifre Ayarlama
 */
function handlePasswordReset($db, $input) {
    if (empty($input['token']) || empty($input['password'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Token ve yeni şifre gereklidir'
        ], 400);
    }
    
    $token = sanitizeInput($input['token']);
    $password = $input['password'];
    
    // Password validation
    if (strlen($password) < 6) {
        jsonResponse([
            'success' => false,
            'message' => 'Şifre en az 6 karakter olmalıdır'
        ], 400);
    }
    
    // Verify token
    $stmt = $db->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? AND used = 0 AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz veya süresi dolmuş token'
        ], 400);
    }
    
    try {
        // Update password
        $hashedPassword = hashPassword($password);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $reset['email']]);
        
        // Mark token as used
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$reset['id']]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Şifreniz başarıyla güncellendi. Artık giriş yapabilirsiniz.'
        ]);
    } catch (PDOException $e) {
        error_log("Password Reset Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Şifre güncellenirken bir hata oluştu'
        ], 500);
    }
}

/**
 * E-posta Gönderme (Simülasyon)
 * Gerçek uygulamada PHPMailer veya SMTP kullanın
 */
function sendPasswordResetEmail($email, $username, $resetLink) {
    // PHPMailer ile gerçek e-posta gönderimi:
    /*
    require 'vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'your-email@gmail.com';
    $mail->Password = 'your-app-password';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    
    $mail->setFrom('noreply@linkfy.tr', 'Linkfy');
    $mail->addAddress($email, $username);
    $mail->Subject = 'Şifre Sıfırlama - Linkfy';
    $mail->isHTML(true);
    $mail->Body = "
        <h2>Merhaba {$username},</h2>
        <p>Şifrenizi sıfırlamak için aşağıdaki linke tıklayın:</p>
        <p><a href='{$resetLink}'>Şifremi Sıfırla</a></p>
        <p>Bu link 1 saat geçerlidir.</p>
        <p>Eğer bu talebi siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.</p>
    ";
    
    return $mail->send();
    */
    
    // Şimdilik simülasyon
    error_log("Password reset email would be sent to: {$email}");
    error_log("Reset link: {$resetLink}");
    
    return true;
}
