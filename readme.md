Linkfy - Tam Özellikli URL Kısaltma & Link Bio Platformu
🎉 v2.0 - Yeni Özelliklerle!
✨ YENİ ÖZELLİKLER (v2.0)
1. 🔐 Şifre Sıfırlama

E-posta ile şifre sıfırlama
Güvenli token sistemi
1 saatlik geçerlilik süresi

2. 🚦 API Rate Limiting

Kötü niyetli kullanımı engelleme
IP bazlı limit kontrolü
100 istek/saat (varsayılan)
Otomatik ban sistemi

3. 🌐 Custom Domain Support (Premium)

Kendi domaininizi kullanın
DNS ve HTML doğrulama
SSL desteği
Domain yönetim paneli

4. 📇 vCard Export (Premium)

Bio sayfalarından vCard oluşturma
Tek tıkla kişilere ekleme
Tüm link bilgilerini içerir
.vcf formatı

5. 🌍 Multi-Language Support

Türkçe ve İngilizce
Otomatik dil tespiti
localStorage ile tercih kaydetme
Tüm sayfalarda dil değiştirme


📦 GÜNCELLENMIŞ DOSYA YAPISI
linkfy/
├── index.html
├── login.html
├── register.html
├── dashboard.html
├── reset-password.html          ⭐ YENİ
├── bio.php
├── r.php
├── .htaccess
├── api/
│   ├── config.php               ⭐ GÜNCELLENDI
│   ├── auth.php                 ⭐ GÜNCELLENDI
│   ├── links.php
│   ├── bio.php
│   ├── password_reset.php       ⭐ YENİ
│   ├── custom_domains.php       ⭐ YENİ
│   └── vcard.php                ⭐ YENİ
├── assets/
│   └── js/
│       ├── dashboard.js
│       └── lang.js              ⭐ YENİ
├── database/
│   ├── install.sql              ⭐ GÜNCELLENDI
│   ├── password_reset.sql       ⭐ YENİ
│   ├── rate_limits.sql          ⭐ YENİ
│   └── custom_domains.sql       ⭐ YENİ
└── README.md
TOPLAM: 20 dosya (6 yeni, 4 güncellendi)

🚀 KURULUM
Adım 1: Dosyaları Upload Edin

Tüm dosyaları public_html klasörüne yükleyin
Doğru klasör yapısını oluşturun

Adım 2: Veritabanı Kurulumu
sql-- 1. Ana veritabanı
database/install.sql

-- 2. Yeni özellik tabloları
database/password_reset.sql
database/rate_limits.sql
database/custom_domains.sql
phpMyAdmin'de:

linkfy_db seçin
Tüm SQL dosyalarını sırayla içe aktarın

Adım 3: Yapılandırma
api/config.php:
phpdefine('DB_NAME', 'linkfy_db');
define('DB_USER', 'linkfy_user');
define('DB_PASS', 'YOUR_PASSWORD');
define('SITE_URL', 'https://linkfy.tr');
define('JWT_SECRET', 'CHANGE_THIS_RANDOM_STRING');

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_REQUESTS', 100); // 100 istek/saat
define('RATE_LIMIT_WINDOW', 3600);  // 1 saat
Adım 4: Test Edin

https://linkfy.tr → Kayıt ol
Dashboard'a giriş yap
Yeni özellikleri dene!


🎯 GÜNCELLENMIŞ ÖZELLİKLER
✅ Temel Özellikler

URL Kısaltma (linkfy.tr/abc123)
Bio Sayfaları (linkfy.tr/@kullaniciadi)
QR Kod Oluşturma
4 Tema (Minimal, Gradient, Dark, Colorful)
İstatistikler (tıklama sayısı)

⭐ Premium Özellikler

Sınırsız link ve bio sayfası
Custom Domain support
vCard export
Detaylı analytics
Premium temalar

🔒 Güvenlik

JWT Authentication
Password hashing (bcrypt)
SQL Injection koruması
XSS koruması
Rate limiting
CSRF koruması

🌐 Yeni Özellikler

✅ Şifre sıfırlama
✅ API rate limiting
✅ Custom domain
✅ vCard export
✅ Multi-language (TR/EN)


📚 KULLANIM KILAVUZU
Şifre Sıfırlama

Login sayfasında "Şifremi unuttum" tıklayın
E-posta adresinizi girin
Gelen linke tıklayın
Yeni şifrenizi belirleyin

Custom Domain Ekleme (Premium)

Dashboard → "Domainlerim"
"Domain Ekle" butonuna tıklayın
Domain adresinizi girin (örn: links.mysite.com)
Doğrulama talimatlarını izleyin:

Yöntem 1: DNS TXT Record
Name: _linkfy-verification
Type: TXT
Value: [verilen token]
Yöntem 2: HTML Dosyası
Dosya: linkfy-verification.txt
İçerik: [verilen token]
URL: https://yourdomain.com/linkfy-verification.txt

"Doğrula" butonuna tıklayın

vCard İndirme (Premium)

Bio sayfanıza gidin
"vCard İndir" butonuna tıklayın
.vcf dosyası otomatik indirilir
Telefon/bilgisayara import edin

Dil Değiştirme

Navbar'daki dil simgesine tıklayın
TR ↔ EN arasında geçiş yapın
Tercih otomatik kaydedilir


⚙️ YÖNETİM
Rate Limit Ayarlama
api/config.php:
phpdefine('RATE_LIMIT_REQUESTS', 100); // İstek sayısı
define('RATE_LIMIT_WINDOW', 3600);  // Süre (saniye)
define('RATE_LIMIT_BAN_TIME', 1800); // Ban süresi
E-posta Yapılandırması (Şifre Sıfırlama)
PHPMailer kullanarak gerçek e-posta gönderin:
api/password_reset.php içinde:
php// Composer ile PHPMailer kurun
// composer require phpmailer/phpmailer

$mail = new PHPMailer\PHPMailer\PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'your-email@gmail.com';
$mail->Password = 'your-app-password';
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
Veritabanı Temizleme (Cron Job)
Eski kayıtları temizleyin:
bash# Günlük çalışacak cron job
0 2 * * * php /path/to/cleanup.php
cleanup.php:
php<?php
require_once 'api/config.php';
$db = Database::getInstance()->getConnection();

// Eski password reset token'ları
$db->query("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");

// Eski rate limit kayıtları
$db->query("DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

🔧 SORUN GİDERME
Rate Limit Hatası
Hata: "Too many requests"
Çözüm:

config.php'de RATE_LIMIT_ENABLED = false yapın (geçici)
Veya RATE_LIMIT_REQUESTS değerini artırın

Şifre Sıfırlama E-postası Gelmiyor
Çözüm:

PHPMailer kurulu mu kontrol edin
SMTP ayarlarını kontrol edin
Spam klasörünü kontrol edin
Development'ta: Konsol loglarına bakın (debug mode)

Custom Domain Doğrulanmıyor
Çözüm:

DNS değişikliklerinin yayılması 24 saat sürebilir
dig _linkfy-verification.yourdomain.com TXT komutuyla kontrol edin
HTML dosyası yönteminde dosya yolunu kontrol edin

vCard İndirilmiyor
Çözüm:

Premium plan aktif mi kontrol edin
Bio sayfasında e-posta adresi var mı kontrol edin
PHP'de file download izinleri kontrol edin


📊 VERİTABANI TABLOLARI
Yeni Tablolar (v2.0)
password_resets

Şifre sıfırlama token'ları
1 saatlik geçerlilik
Tek kullanımlık

rate_limits

IP bazlı istek takibi
Otomatik ban sistemi
Zaman penceresi kontrolü

custom_domains

Kullanıcı domainleri
Doğrulama durumu
SSL bilgisi

domain_links

Domain-link ilişkileri
Hangi link hangi domainde


🎨 GELİŞTİRME
Yeni Dil Ekleme
assets/js/lang.js:
javascriptconst translations = {
    tr: { /* ... */ },
    en: { /* ... */ },
    de: {  // Yeni dil
        home: 'Startseite',
        // ...
    }
};
Yeni Tema Ekleme
dashboard.html ve bio.php:
javascript// Tema ekle
themes = {
    neon: {
        bg: 'bg-black',
        text: 'text-neon-green',
        // ...
    }
};

📈 PERFORMANS
Optimizasyon İpuçları

CDN Kullanın:

Tailwind CSS → CloudFlare CDN
Lucide Icons → unpkg CDN


Veritabanı İndeksleri:

Tüm foreign key'ler indeksli
Email, username unique indeksli


Rate Limiting:

Gereksiz veritabanı sorgularını engeller
DDoS koruması sağlar


Caching:

Bio sayfaları cache edilebilir
QR kodlar cache edilebilir




🔐 GÜVENLİK ÖNERİLERİ

SSL Sertifikası:

Let's Encrypt ile ücretsiz SSL
HTTPS yönlendirmesi aktif (.htaccess)


Güçlü Şifreler:

JWT_SECRET değiştirin
Veritabanı şifresi güçlü olsun


Firewall:

cPanel Firewall'u aktif edin
Sadece gerekli portları açın


Güncellemeler:

PHP ve MySQL güncel tutun
Düzenli güvenlik yamaları


Backup:

Günlük otomatik backup
cPanel backup'ları aktif




📝 LİSANS
Bu proje MIT Lisansı altında lisanslanmıştır.
Kişisel ve ticari kullanım için ücretsizdir.

🤝 KATKIDA BULUNMA

Fork edin
Feature branch oluşturun (git checkout -b feature/amazing)
Commit edin (git commit -m 'Add amazing feature')
Push edin (git push origin feature/amazing)
Pull Request açın


📧 DESTEK
Sorunlarınız için:

GitHub Issues açın
README'yi okuyun
PHP error loglarını kontrol edin


🎉 TEBR İKLER!
Linkfy platformunuz artık tamamen hazır ve tüm modern özelliklere sahip! 🚀
Yeni Özellikler:

✅ Şifre sıfırlama
✅ Rate limiting
✅ Custom domains
✅ vCard export
✅ Multi-language

İyi kullanımlar! 💙
