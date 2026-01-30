<?php
/**
 * Linkfy - Custom Domains API (Premium Feature)
 * Özel domain ekleme, doğrulama ve yönetimi
 */

require_once 'config.php';

setCorsHeaders();

// Rate limiting
$clientIP = getClientIP();
checkRateLimit($clientIP, 'domains');

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// GET    /api/custom_domains.php - Kullanıcının domainlerini listele
// POST   /api/custom_domains.php - Yeni domain ekle
// POST   /api/custom_domains.php?action=verify - Domain'i doğrula
// DELETE /api/custom_domains.php?id=123 - Domain'i sil

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($method) {
    case 'GET':
        handleGetDomains($db);
        break;
        
    case 'POST':
        if ($action === 'verify') {
            handleVerifyDomain($db, $input);
        } else {
            handleAddDomain($db, $input);
        }
        break;
        
    case 'DELETE':
        handleDeleteDomain($db);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

/**
 * Kullanıcının Domainlerini Listele
 */
function handleGetDomains($db) {
    $userData = requireAuth();
    
    $stmt = $db->prepare("
        SELECT id, domain, verified, ssl_enabled, active, created_at, verified_at
        FROM custom_domains 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userData['user_id']]);
    $domains = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $domains
    ]);
}

/**
 * Yeni Domain Ekle
 */
function handleAddDomain($db, $input) {
    $userData = requireAuth();
    
    // Check premium status
    $stmt = $db->prepare("SELECT plan FROM users WHERE id = ?");
    $stmt->execute([$userData['user_id']]);
    $user = $stmt->fetch();
    
    if ($user['plan'] !== 'premium') {
        jsonResponse([
            'success' => false,
            'message' => 'Özel domain özelliği sadece Premium kullanıcılar için geçerlidir',
            'upgrade_required' => true
        ], 403);
    }
    
    // Validation
    if (empty($input['domain'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Domain adresi gereklidir'
        ], 400);
    }
    
    $domain = strtolower(sanitizeInput($input['domain']));
    
    // Remove protocol and www
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = rtrim($domain, '/');
    
    // Validate domain format
    if (!preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,}$/i', $domain)) {
        jsonResponse([
            'success' => false,
            'message' => 'Geçersiz domain formatı'
        ], 400);
    }
    
    // Check if domain already exists
    $stmt = $db->prepare("SELECT id FROM custom_domains WHERE domain = ?");
    $stmt->execute([$domain]);
    if ($stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Bu domain zaten kullanılıyor'
        ], 400);
    }
    
    // Generate verification token
    $verificationToken = bin2hex(random_bytes(16));
    
    try {
        $stmt = $db->prepare("
            INSERT INTO custom_domains (user_id, domain, verification_token) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userData['user_id'], $domain, $verificationToken]);
        $domainId = $db->lastInsertId();
        
        jsonResponse([
            'success' => true,
            'message' => 'Domain eklendi. Şimdi doğrulama yapmanız gerekiyor.',
            'data' => [
                'id' => $domainId,
                'domain' => $domain,
                'verification_token' => $verificationToken,
                'verification_instructions' => [
                    'method_1' => [
                        'type' => 'DNS TXT Record',
                        'name' => '_linkfy-verification',
                        'value' => $verificationToken
                    ],
                    'method_2' => [
                        'type' => 'HTML File',
                        'filename' => 'linkfy-verification.txt',
                        'content' => $verificationToken,
                        'url' => "https://{$domain}/linkfy-verification.txt"
                    ]
                ]
            ]
        ], 201);
    } catch (PDOException $e) {
        error_log("Add Domain Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Domain eklenirken bir hata oluştu'
        ], 500);
    }
}

/**
 * Domain Doğrulama
 */
function handleVerifyDomain($db, $input) {
    $userData = requireAuth();
    
    if (empty($input['domain_id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Domain ID gereklidir'
        ], 400);
    }
    
    $domainId = (int)$input['domain_id'];
    
    // Get domain
    $stmt = $db->prepare("
        SELECT * FROM custom_domains 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$domainId, $userData['user_id']]);
    $domain = $stmt->fetch();
    
    if (!$domain) {
        jsonResponse([
            'success' => false,
            'message' => 'Domain bulunamadı'
        ], 404);
    }
    
    if ($domain['verified']) {
        jsonResponse([
            'success' => true,
            'message' => 'Domain zaten doğrulanmış'
        ]);
    }
    
    // Try verification methods
    $verified = false;
    $verificationMethod = '';
    
    // Method 1: DNS TXT Record
    $dnsRecords = @dns_get_record('_linkfy-verification.' . $domain['domain'], DNS_TXT);
    if ($dnsRecords) {
        foreach ($dnsRecords as $record) {
            if (isset($record['txt']) && $record['txt'] === $domain['verification_token']) {
                $verified = true;
                $verificationMethod = 'DNS TXT Record';
                break;
            }
        }
    }
    
    // Method 2: HTML File
    if (!$verified) {
        $url = "https://{$domain['domain']}/linkfy-verification.txt";
        $content = @file_get_contents($url);
        if ($content && trim($content) === $domain['verification_token']) {
            $verified = true;
            $verificationMethod = 'HTML File';
        }
    }
    
    if ($verified) {
        $stmt = $db->prepare("
            UPDATE custom_domains 
            SET verified = 1, verified_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$domainId]);
        
        jsonResponse([
            'success' => true,
            'message' => "Domain başarıyla doğrulandı ({$verificationMethod})",
            'data' => [
                'domain' => $domain['domain'],
                'verified' => true,
                'verified_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'message' => 'Domain doğrulaması başarısız. Lütfen DNS kaydınızı veya doğrulama dosyanızı kontrol edin.'
        ], 400);
    }
}

/**
 * Domain Sil
 */
function handleDeleteDomain($db) {
    $userData = requireAuth();
    
    if (!isset($_GET['id'])) {
        jsonResponse([
            'success' => false,
            'message' => 'Domain ID gereklidir'
        ], 400);
    }
    
    $domainId = (int)$_GET['id'];
    
    // Check ownership
    $stmt = $db->prepare("SELECT id FROM custom_domains WHERE id = ? AND user_id = ?");
    $stmt->execute([$domainId, $userData['user_id']]);
    
    if (!$stmt->fetch()) {
        jsonResponse([
            'success' => false,
            'message' => 'Domain bulunamadı'
        ], 404);
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM custom_domains WHERE id = ?");
        $stmt->execute([$domainId]);
        
        jsonResponse([
            'success' => true,
            'message' => 'Domain silindi'
        ]);
    } catch (PDOException $e) {
        error_log("Delete Domain Error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Domain silinirken bir hata oluştu'
        ], 500);
    }
}
