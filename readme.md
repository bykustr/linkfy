# 🔧 Linkfy v2.0 - Düzeltilmiş Versiyon

Bu versiyon, orijinal Linkfy projesindeki hataların düzeltilmiş versiyonudur.

## ✅ Düzeltilen Hatalar

### Kritik Hatalar
1. ✅ API endpoint isimlendirme tutarsızlıkları düzeltildi
2. ✅ Dashboard JavaScript'te yanlış API çağrıları düzeltildi
3. ✅ README'de dosya isimlerinin tutarsızlığı giderildi

### İyileştirmeler
1. ✅ .htaccess kısa link regex'i esnek hale getirildi (3-20 karakter)
2. ✅ Rate limit temizleme süresi optimize edildi (24 saat)
3. ✅ Error log dizini otomatik oluşturma eklendi
4. ✅ Kurulum kontrol scripti eklendi

## 📋 Düzeltme Detayları

Tüm düzeltme detayları için `FIXES.md` dosyasına bakın.

## 🚀 Hızlı Kurulum

### 1. Dosyaları Yükle
```bash
# Tüm dosyaları public_html klasörüne yükleyin
```

### 2. Kurulum Kontrolü Yap
```bash
chmod +x check-installation.sh
./check-installation.sh
```

### 3. Veritabanını Kur
```sql
-- phpMyAdmin veya MySQL CLI'da çalıştırın
source database/install.sql
source password-reset.sql
source rate-limit.sql
source custom-domain.sql
```

### 4. Config Dosyasını Düzenle
```php
// api/config.php
define('DB_NAME', 'linkfy_db');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
define('SITE_URL', 'https://yourdomain.com');
define('JWT_SECRET', 'your-random-secret-key');
```

### 5. Test Et
```bash
# API testleri
curl http://localhost/api/auth.php?action=login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test123"}'
```

## 📝 Önemli Notlar

### API Endpoint İsimleri (DOĞRU)
```
✅ /api/link.php        (linkler için)
✅ /api/bio.php         (bio sayfaları için)
✅ /api/auth.php        (authentication için)
✅ /api/password-reset.php  (şifre sıfırlama için)
✅ /api/custom-domain.php   (custom domain için)
✅ /api/vcard.php       (vcard export için)
```

### Kısa Link Formatları (DESTEKLENEN)
```
✅ /abc          (3 karakter)
✅ /abc123       (6 karakter)
✅ /my-link      (tire ile)
✅ /my_link_123  (alt çizgi ile)
✅ /custom-code-2024  (uzun kodlar)
```

### Veritabanı Dosyaları (DOĞRU İSİMLER)
```
✅ database/install.sql
✅ password-reset.sql
✅ rate-limit.sql
✅ custom-domain.sql
```

## 🐛 Sorun Giderme

### "Link bulunamadı" Hatası
- `.htaccess` dosyasının doğru konumda olduğundan emin olun
- Apache `mod_rewrite` modülünün aktif olduğunu kontrol edin
- `AllowOverride All` ayarının açık olduğunu kontrol edin

### "Veritabanı bağlantı hatası"
- `api/config.php` dosyasındaki veritabanı bilgilerini kontrol edin
- MySQL servisinin çalıştığından emin olun
- Kullanıcının doğru izinlere sahip olduğunu kontrol edin

### "API endpoint bulunamadı"
- Dosya isimlerinin doğru olduğunu kontrol edin
- Dashboard.js'te `console.log` ile hangi endpoint'e istek atıldığını görün
- Browser Network sekmesinde 404 hatalarına bakın

### Rate Limit Sorunları
- `api/config.php`'de `RATE_LIMIT_ENABLED` false yaparak devre dışı bırakın
- Veritabanında `rate_limits` tablosunun olduğunu kontrol edin
- Eski kayıtları temizlemek için cron job kurun

## 📊 Performans İpuçları

1. **Logs Klasörü**: Otomatik oluşturulur, ancak düzenli temizleme önerilir
2. **Rate Limit**: Yüksek trafik için `RATE_LIMIT_REQUESTS` değerini artırın
3. **Database**: İndekslerin doğru oluşturulduğundan emin olun
4. **Cache**: Cloudflare veya benzeri CDN kullanın

## 🔒 Güvenlik Kontrol Listesi

- [ ] `JWT_SECRET` değiştirdim
- [ ] Veritabanı şifresi güçlü
- [ ] SSL sertifikası kurulu
- [ ] `.htaccess` güvenlik başlıkları aktif
- [ ] Admin şifresi değiştirildi
- [ ] Error reporting production'da kapalı
- [ ] Logs klasörü web'den erişilebilir değil

## 📞 Destek

Sorun yaşarsanız:
1. `FIXES.md` dosyasını okuyun
2. `check-installation.sh` scriptini çalıştırın
3. PHP error loglarını kontrol edin
4. Browser console'u kontrol edin

## 📄 Lisans

MIT Lisansı - Kişisel ve ticari kullanım için ücretsiz

---

**Not**: Bu düzeltilmiş versiyondur. Orijinal proje ile karşılaştırma için `FIXES.md` dosyasına bakın.

**Versiyon**: 2.0-fixed
**Tarih**: 30 Ocak 2026
