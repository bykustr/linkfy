<?php
/**
 * Linkfy - Bio Page View
 * Bio sayfalarını görüntüler: @kullaniciadi
 */

require_once 'api/config.php';

if (!isset($_GET['username']) || empty($_GET['username'])) {
    header("Location: " . SITE_URL);
    exit;
}

$username = sanitizeInput($_GET['username']);

try {
    $db = Database::getInstance()->getConnection();
    
    // Get bio page
    $stmt = $db->prepare("
        SELECT bp.*, u.plan 
        FROM bio_pages bp
        JOIN users u ON bp.user_id = u.id
        WHERE bp.username = ?
    ");
    $stmt->execute([$username]);
    $page = $stmt->fetch();
    
    if (!$page) {
        // Page not found
        header("HTTP/1.0 404 Not Found");
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Sayfa Bulunamadı - Linkfy</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
            <div class="text-center">
                <h1 class="text-6xl font-bold text-gray-800 mb-4">404</h1>
                <p class="text-xl text-gray-600 mb-8">Bio sayfası bulunamadı</p>
                <a href="<?php echo SITE_URL; ?>" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 inline-block">
                    Ana Sayfaya Dön
                </a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Decode links
    $links = json_decode($page['links'], true) ?: [];
    
    // Theme styles
    $themeClasses = [
        'minimal' => 'bg-white text-gray-900',
        'gradient' => 'bg-gradient-to-br from-purple-400 to-pink-400 text-white',
        'dark' => 'bg-gray-900 text-white',
        'colorful' => 'bg-gradient-to-br from-yellow-400 via-red-500 to-pink-500 text-white'
    ];
    
    $linkClasses = [
        'minimal' => 'bg-gray-100 text-gray-900 hover:bg-gray-200',
        'gradient' => 'bg-white bg-opacity-20 hover:bg-opacity-30 backdrop-blur',
        'dark' => 'bg-gray-800 hover:bg-gray-700',
        'colorful' => 'bg-white bg-opacity-20 hover:bg-opacity-30 backdrop-blur'
    ];
    
    $theme = $page['theme'] ?? 'minimal';
    $themeClass = $themeClasses[$theme] ?? $themeClasses['minimal'];
    $linkClass = $linkClasses[$theme] ?? $linkClasses['minimal'];
    
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($page['title']); ?> - @<?php echo htmlspecialchars($username); ?></title>
        <meta name="description" content="<?php echo htmlspecialchars($page['description']); ?>">
        
        <!-- Open Graph -->
        <meta property="og:title" content="<?php echo htmlspecialchars($page['title']); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($page['description']); ?>">
        <?php if ($page['profile_image']): ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($page['profile_image']); ?>">
        <?php endif; ?>
        <meta property="og:url" content="<?php echo SITE_URL; ?>/@<?php echo htmlspecialchars($username); ?>">
        
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?php echo htmlspecialchars($page['title']); ?>">
        <meta name="twitter:description" content="<?php echo htmlspecialchars($page['description']); ?>">
        
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .link-item {
                animation: fadeIn 0.5s ease-out forwards;
                opacity: 0;
            }
            <?php for ($i = 0; $i < count($links); $i++): ?>
            .link-item:nth-child(<?php echo $i + 1; ?>) {
                animation-delay: <?php echo $i * 0.1; ?>s;
            }
            <?php endfor; ?>
        </style>
    </head>
    <body class="<?php echo $themeClass; ?> min-h-screen py-12 px-4">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <?php if ($page['profile_image']): ?>
                <img 
                    src="<?php echo htmlspecialchars($page['profile_image']); ?>" 
                    alt="<?php echo htmlspecialchars($page['title']); ?>"
                    class="w-32 h-32 rounded-full mx-auto mb-6 object-cover border-4 border-white shadow-lg"
                    onerror="this.style.display='none'"
                >
                <?php endif; ?>
                
                <h1 class="text-4xl font-bold mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
                
                <?php if ($page['description']): ?>
                <p class="text-lg opacity-90 mb-2"><?php echo nl2br(htmlspecialchars($page['description'])); ?></p>
                <?php endif; ?>
                
                <p class="opacity-75">@<?php echo htmlspecialchars($username); ?></p>
            </div>
            
            <!-- Links -->
            <div class="space-y-4">
                <?php foreach ($links as $link): ?>
                    <?php if (!empty($link['title']) && !empty($link['url'])): ?>
                    <a 
                        href="<?php echo htmlspecialchars($link['url']); ?>" 
                        target="_blank" 
                        rel="noopener noreferrer"
                        class="link-item block p-5 rounded-xl text-center font-semibold text-lg transition transform hover:scale-105 <?php echo $linkClass; ?>"
                    >
                        <?php echo htmlspecialchars($link['title']); ?>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Footer -->
            <div class="text-center mt-12 opacity-75">
                <?php if ($page['plan'] === 'premium'): ?>
                <a href="/api/vcard.php?username=<?php echo htmlspecialchars($username); ?>" 
                   class="inline-block mb-4 px-6 py-2 rounded-lg font-semibold transition <?php echo $theme === 'minimal' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white bg-opacity-20 hover:bg-opacity-30'; ?>">
                    <span class="inline-block mr-2">📇</span> vCard İndir
                </a>
                <br>
                <?php endif; ?>
                <p class="text-sm mb-2">Powered by</p>
                <a href="<?php echo SITE_URL; ?>" class="font-bold text-lg hover:underline">
                    Linkfy
                </a>
            </div>
        </div>
        
        <!-- Analytics (opsiyonel) -->
        <script>
            // Sayfa görüntüleme sayacı eklenebilir
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    error_log("Bio Page View Error: " . $e->getMessage());
    header("Location: " . SITE_URL);
    exit;
}