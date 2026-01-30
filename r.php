<?php
/**
 * Linkfy - Link Redirect
 * Kısa linkleri orijinal URL'lere yönlendirir
 */

require_once 'api/config.php';

if (!isset($_GET['code']) || empty($_GET['code'])) {
    header("Location: " . SITE_URL);
    exit;
}

$shortCode = sanitizeInput($_GET['code']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Get link
    $stmt = $db->prepare("SELECT id, original_url, clicks FROM links WHERE short_code = ?");
    $stmt->execute([$shortCode]);
    $link = $stmt->fetch();
    
    if (!$link) {
        // Link not found
        header("HTTP/1.0 404 Not Found");
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Link Bulunamadı - Linkfy</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
            <div class="text-center">
                <h1 class="text-6xl font-bold text-gray-800 mb-4">404</h1>
                <p class="text-xl text-gray-600 mb-8">Link bulunamadı</p>
                <a href="<?php echo SITE_URL; ?>" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 inline-block">
                    Ana Sayfaya Dön
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Update click count
    $newClicks = $link['clicks'] + 1;
    $stmt = $db->prepare("UPDATE links SET clicks = ? WHERE id = ?");
    $stmt->execute([$newClicks, $link['id']]);
    
    // Log analytics (opsiyonel - premium özellik)
    try {
        $stmt = $db->prepare("
            INSERT INTO link_analytics (link_id, ip_address, user_agent, referer) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $link['id'],
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    } catch (Exception $e) {
        // Analytics logging failed, but continue with redirect
        error_log("Analytics Error: " . $e->getMessage());
    }
    
    // Redirect
    header("Location: " . $link['original_url'], true, 301);
    exit;
    
} catch (Exception $e) {
    error_log("Redirect Error: " . $e->getMessage());
    header("Location: " . SITE_URL);
    exit;
}