<?php
/**
 * Linkfy - Links API
 * Kısa link oluşturma, listeleme, silme
 */

require_once 'config.php';

setCorsHeaders();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// GET    /api/links.php - Kullanıcının tüm linklerini getir
// POST   /api/links.php - Yeni link oluştur
// DELETE /api/links.php?id=123 - Link sil

switch ($method) {
    case 'GET':
        handleGetLinks($db);
        break;
        
    case 'POST':
        handleCreateLink($db, $input);
        break;
        
    case 'DELETE':
        handleDeleteLink($db);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Kullanıcının Linklerini Getir
 */
function handleGetLinks($db) {
    $userData = requireAuth();
    
    $stmt = $db->prepare("
        SELECT id, original_url, short_code, clicks, created_at 
        FROM links 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userData['user_id']]);
    $links = $stmt->fetchAll();
    
    // Format links
    $formattedLinks = array_map(function($link) {
        return [
            'id' => $link['id'],
            'originalUrl' => $link['original_url'],
            'shortCode' => $link['short_code'],
            'shortUrl' => SITE_URL . '/' . $link['short_code'],
            'clicks' => (int)$link['clicks'],
            'createdAt' => $link['created_at']
        ];
    }, $links);
    
    jsonResponse([
        'success' => true,
        'data' => $formattedLinks
    ]);
}

/**
 * Yeni Link Oluştur
 */
function handleCreateLink($db, $input) {
    $userData = requireAuth();
    
    // Validation
    if (empty($input['originalUrl'])) {
        jsonResponse([
            'success' => false,
            'message' => 'URL gereklidir'
        ], 400);
    }
    
    $originalUrl = sanitizeInput($input['originalUrl']);
    
    // URL validation
    if (!isValidUrl($originalUrl)) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz URL formatı'
        ], 400);
    }
    
    // Check user plan limits
    $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
    $stmt->execute([$userData['user_id']]);
    $user = $stmt->fetch();
    
    if ($user['plan'] === 'free') {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM links WHERE user_id = ?");
        $stmt->execute([$userData['user_id']]);
        $linkCount = $stmt->fetch()['total'];
        
        if ($linkCount >= FREE_PLAN_LINKS) {
            jsonResponse([
                'success' => false,
                'message' => 'Ücretsiz planda maksimum ' . FREE_PLAN_LINKS . ' link oluşturabilirsiniz. Premium'a geçin!',
                'upgrade_required' => true
            ], 403);
        }
    }
    
    // Generate unique short code
    $shortCode = generateUniqueShortCode($db);
    
    // Custom short code support (premium feature)
    if (!empty($input['customCode']) && $user['plan'] === 'premium') {
        $customCode = sanitizeInput($input['customCode']);
        if (preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $customCode)) {
            // Check if custom code is available
            $stmt = $db->prepare("SELECT id FROM links WHERE short_code = ?");
            $stmt->execute([$customCode]);
            if (!$stmt->fetch()) {
                $shortCode = $customCode;
            }
        }
    }
    
    // Create link
    try {
        $stmt = $db->prepare("
            INSERT INTO links (user_id, original_url, short_code, clicks) 
            VALUES (?, ?, ?, 0)
        ");
        $stmt->execute([$userData['user_id'], $originalUrl, $shortCode]);
        $linkId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Link başarıyla oluşturuldu',
            'data' => [
                'id' => $linkId,
                'originalUrl' => $originalUrl,
                'shortCode' => $shortCode,
                'shortUrl' => SITE_URL . '/' . $shortCode,
                'clicks' => 0,
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("Create Link Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Link oluşturulurken bir hata oluştu'
        ], 500);
    }
}

/**
 * Link Sil
 */
function handleDeleteLink($db) {
    $userData = requireAuth();
    
    if (!isset($_GET['id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Link ID gereklidir'
        ], 400);
    }
    
    $linkId = (int)$_GET['id'];
    
    // Check if link belongs to user
    $stmt = $db->prepare("SELECT id FROM links WHERE id = ? AND user_id = ?");
    $stmt->execute([$linkId, $userData['user_id']]);
    
    if (!$stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Link bulunamadı veya size ait değil'
        ], 404);
    }
    
    // Delete link
    try {
        $stmt = $db->prepare("DELETE FROM links WHERE id = ?");
        $stmt->execute([$linkId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Link başarıyla silindi'
        ]);
    } catch (PDOException $e) {
        error_log("Delete Link Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Link silinirken bir hata oluştu'
        ], 500);
    }
}

/**
 * Generate Unique Short Code
 */
function generateUniqueShortCode($db, $attempts = 0) {
    if ($attempts > 10) {
        throw new Exception('Could not generate unique short code');
    }
    
    $shortCode = generateRandomString(SHORT_CODE_LENGTH);
    
    // Check if code exists
    $stmt = $db->prepare("SELECT id FROM links WHERE short_code = ?");
    $stmt->execute([$shortCode]);
    
    if ($stmt->fetch()) {
        // Code exists, try again
        return generateUniqueShortCode($db, $attempts + 1);
    }
    
    return $shortCode;
}