<?php
/**
 * Linkfy - vCard Export API
 * Bio sayfalarından vCard (.vcf) oluşturma
 */

require_once 'config.php';

// GET /api/vcard.php?username=john

if (!isset($_GET['username']) || empty($_GET['username'])) {
    header("HTTP/1.0 400 Bad Request");
    die('Username required');
}

$username = sanitizeInput($_GET['username']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Get bio page
    $stmt = $db->prepare("
        SELECT bp.*, u.plan, u.email 
        FROM bio_pages bp
        JOIN users u ON bp.user_id = u.id
        WHERE bp.username = ?
    ");
    $stmt->execute([$username]);
    $page = $stmt->fetch();
    
    if (!$page) {
        header("HTTP/1.0 404 Not Found");
        die('Bio page not found');
    }
    
    // Check if premium (vCard is premium feature)
    if ($page['plan'] !== 'premium') {
        header("HTTP/1.0 403 Forbidden");
        die('vCard export is a premium feature');
    }
    
    // Decode links
    $links = json_decode($page['links'], true) ?: [];
    
    // Generate vCard
    $vcard = generateVCard($page, $links);
    
    // Set headers for download
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $username . '.vcf"');
    header('Content-Length: ' . strlen($vcard));
    
    echo $vcard;
    exit;
    
} catch (Exception $e) {
    error_log("vCard Error: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    die('Error generating vCard');
}

/**
 * Generate vCard (VCF) Format
 */
function generateVCard($page, $links) {
    $vcard = "BEGIN:VCARD\r\n";
    $vcard .= "VERSION:3.0\r\n";
    
    // Full name
    $vcard .= "FN:" . escapeVCard($page['title']) . "\r\n";
    $vcard .= "N:" . escapeVCard($page['username']) . ";;;;\r\n";
    
    // Username as nickname
    $vcard .= "NICKNAME:" . escapeVCard($page['username']) . "\r\n";
    
    // Email
    if (!empty($page['email'])) {
        $vcard .= "EMAIL;TYPE=INTERNET:" . escapeVCard($page['email']) . "\r\n";
    }
    
    // Description/Note
    if (!empty($page['description'])) {
        $vcard .= "NOTE:" . escapeVCard($page['description']) . "\r\n";
    }
    
    // Profile Image (URL)
    if (!empty($page['profile_image'])) {
        $vcard .= "PHOTO;VALUE=URI:" . escapeVCard($page['profile_image']) . "\r\n";
    }
    
    // Bio page URL
    $bioUrl = SITE_URL . '/@' . $page['username'];
    $vcard .= "URL:" . escapeVCard($bioUrl) . "\r\n";
    
    // Add links as URLs
    foreach ($links as $index => $link) {
        if (!empty($link['url'])) {
            $label = !empty($link['title']) ? $link['title'] : 'Link ' . ($index + 1);
            $vcard .= "URL;TYPE=" . escapeVCard($label) . ":" . escapeVCard($link['url']) . "\r\n";
        }
    }
    
    // Organization (optional)
    $vcard .= "ORG:Linkfy\r\n";
    
    // Revision date
    $vcard .= "REV:" . date('Y-m-d\TH:i:s\Z') . "\r\n";
    
    $vcard .= "END:VCARD\r\n";
    
    return $vcard;
}

/**
 * Escape special characters for vCard format
 */
function escapeVCard($text) {
    $text = str_replace("\r\n", '\n', $text);
    $text = str_replace("\n", '\n', $text);
    $text = str_replace(",", '\,', $text);
    $text = str_replace(";", '\;', $text);
    return $text;
}
