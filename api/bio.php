<?php
/**
 * Linkfy - Bio Pages API
 * Bio sayfası oluşturma, güncelleme, silme
 */

require_once 'config.php';

setCorsHeaders();

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// GET    /api/bio.php - Kullanıcının bio sayfalarını getir
// GET    /api/bio.php?username=john - Public bio sayfasını getir
// POST   /api/bio.php - Yeni bio sayfası oluştur
// PUT    /api/bio.php?id=123 - Bio sayfasını güncelle
// DELETE /api/bio.php?id=123 - Bio sayfasını sil

switch ($method) {
    case 'GET':
        if (isset($_GET['username'])) {
            handleGetPublicBioPage($db, $_GET['username']);
        } else {
            handleGetBioPages($db);
        }
        break;
        
    case 'POST':
        handleCreateBioPage($db, $input);
        break;
        
    case 'PUT':
        handleUpdateBioPage($db, $input);
        break;
        
    case 'DELETE':
        handleDeleteBioPage($db);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Kullanıcının Bio Sayfalarını Getir
 */
function handleGetBioPages($db) {
    $userData = requireAuth();
    
    $stmt = $db->prepare("
        SELECT id, username, title, description, profile_image, links, theme, created_at 
        FROM bio_pages 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userData['user_id']]);
    $bioPages = $stmt->fetchAll();
    
    // Format bio pages
    $formattedPages = array_map(function($page) {
        return [
            'id' => $page['id'],
            'username' => $page['username'],
            'title' => $page['title'],
            'description' => $page['description'],
            'profileImage' => $page['profile_image'],
            'links' => json_decode($page['links'], true) ?: [],
            'theme' => $page['theme'],
            'url' => SITE_URL . '/@' . $page['username'],
            'createdAt' => $page['created_at']
        ];
    }, $bioPages);
    
    jsonResponse([
        'success' => true,
        'data' => $formattedPages
    ]);
}

/**
 * Public Bio Sayfasını Getir
 */
function handleGetPublicBioPage($db, $username) {
    $username = sanitizeInput($username);
    
    $stmt = $db->prepare("
        SELECT bp.*, u.plan 
        FROM bio_pages bp
        JOIN users u ON bp.user_id = u.id
        WHERE bp.username = ?
    ");
    $stmt->execute([$username]);
    $page = $stmt->fetch();
    
    if (!$page) {
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası bulunamadı'
        ], 404);
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'username' => $page['username'],
            'title' => $page['title'],
            'description' => $page['description'],
            'profileImage' => $page['profile_image'],
            'links' => json_decode($page['links'], true) ?: [],
            'theme' => $page['theme'],
            'isPremium' => $page['plan'] === 'premium'
        ]
    ]);
}

/**
 * Yeni Bio Sayfası Oluştur
 */
function handleCreateBioPage($db, $input) {
    $userData = requireAuth();
    
    // Validation
    if (empty($input['title']) || empty($input['username'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Başlık ve kullanıcı adı gereklidir'
        ], 400);
    }
    
    $username = sanitizeInput($input['username']);
    $title = sanitizeInput($input['title']);
    $description = isset($input['description']) ? sanitizeInput($input['description']) : '';
    $profileImage = isset($input['profileImage']) ? sanitizeInput($input['profileImage']) : '';
    $links = isset($input['links']) ? json_encode($input['links']) : '[]';
    $theme = isset($input['theme']) ? sanitizeInput($input['theme']) : 'minimal';
    
    // Username validation
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        jsonResponse([
            'success' => false,
            'message' => 'Kullanıcı adı 3-20 karakter olmalı ve sadece harf, rakam ve alt çizgi içerebilir'
        ], 400);
    }
    
    // Check user plan limits
    $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
    $stmt->execute([$userData['user_id']]);
    $user = $stmt->fetch();
    
    if ($user['plan'] === 'free') {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM bio_pages WHERE user_id = ?");
        $stmt->execute([$userData['user_id']]);
        $pageCount = $stmt->fetch()['total'];
        
        if ($pageCount >= FREE_PLAN_BIO_PAGES) {
            jsonResponse([
                'success' => false,
                'message' => 'Ücretsiz planda maksimum ' . FREE_PLAN_BIO_PAGES . ' bio sayfası oluşturabilirsiniz. Premium'a geçin!',
                'upgrade_required' => true
            ], 403);
        }
    }
    
    // Check if username is taken
    $stmt = $db->prepare("SELECT id FROM bio_pages WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Bu kullanıcı adı zaten kullanılıyor'
        ], 400);
    }
    
    // Create bio page
    try {
        $stmt = $db->prepare("
            INSERT INTO bio_pages (user_id, username, title, description, profile_image, links, theme) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userData['user_id'],
            $username,
            $title,
            $description,
            $profileImage,
            $links,
            $theme
        ]);
        $pageId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Bio sayfası başarıyla oluşturuldu',
            'data' => [
                'id' => $pageId,
                'username' => $username,
                'title' => $title,
                'description' => $description,
                'profileImage' => $profileImage,
                'links' => json_decode($links, true),
                'theme' => $theme,
                'url' => SITE_URL . '/@' . $username,
                'createdAt' => date('Y-m-d H:i:s')
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("Create Bio Page Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası oluşturulurken bir hata oluştu'
        ], 500);
    }
}

/**
 * Bio Sayfasını Güncelle
 */
function handleUpdateBioPage($db, $input) {
    $userData = requireAuth();
    
    if (!isset($_GET['id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfa ID gereklidir'
        ], 400);
    }
    
    $pageId = (int)$_GET['id'];
    
    // Check if page belongs to user
    $stmt = $db->prepare("SELECT * FROM bio_pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$pageId, $userData['user_id']]);
    $existingPage = $stmt->fetch();
    
    if (!$existingPage) {
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası bulunamadı veya size ait değil'
        ], 404);
    }
    
    // Update fields
    $title = isset($input['title']) ? sanitizeInput($input['title']) : $existingPage['title'];
    $description = isset($input['description']) ? sanitizeInput($input['description']) : $existingPage['description'];
    $profileImage = isset($input['profileImage']) ? sanitizeInput($input['profileImage']) : $existingPage['profile_image'];
    $links = isset($input['links']) ? json_encode($input['links']) : $existingPage['links'];
    $theme = isset($input['theme']) ? sanitizeInput($input['theme']) : $existingPage['theme'];
    
    try {
        $stmt = $db->prepare("
            UPDATE bio_pages 
            SET title = ?, description = ?, profile_image = ?, links = ?, theme = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $profileImage, $links, $theme, $pageId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Bio sayfası başarıyla güncellendi',
            'data' => [
                'id' => $pageId,
                'username' => $existingPage['username'],
                'title' => $title,
                'description' => $description,
                'profileImage' => $profileImage,
                'links' => json_decode($links, true),
                'theme' => $theme,
                'url' => SITE_URL . '/@' . $existingPage['username']
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Update Bio Page Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası güncellenirken bir hata oluştu'
        ], 500);
    }
}

/**
 * Bio Sayfasını Sil
 */
function handleDeleteBioPage($db) {
    $userData = requireAuth();
    
    if (!isset($_GET['id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfa ID gereklidir'
        ], 400);
    }
    
    $pageId = (int)$_GET['id'];
    
    // Check if page belongs to user
    $stmt = $db->prepare("SELECT id FROM bio_pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$pageId, $userData['user_id']]);
    
    if (!$stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası bulunamadı veya size ait değil'
        ], 404);
    }
    
    // Delete page
    try {
        $stmt = $db->prepare("DELETE FROM bio_pages WHERE id = ?");
        $stmt->execute([$pageId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Bio sayfası başarıyla silindi'
        ]);
    } catch (PDOException $e) {
        error_log("Delete Bio Page Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Bio sayfası silinirken bir hata oluştu'
        ], 500);
    }
}