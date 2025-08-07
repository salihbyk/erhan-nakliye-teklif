# WordPress Benzeri Otomatik Güncelleme Sistemi

Bu sistem, WordPress'teki eklenti güncelleme sistemi gibi çalışan tam otomatik bir güncelleme sistemi sağlar.

## 🚀 Özellikler

### ✨ **Otomatik Tespit**
- Git commit'leri otomatik tespit edilir
- Değişen dosyalar analiz edilir
- Migration dosyaları otomatik dahil edilir

### 📦 **Otomatik Paketleme**
- Her commit sonrası güncelleme paketi oluşur
- ZIP formatında paketleme
- Changelog otomatik oluşturulur

### 🌐 **Güncelleme Sunucusu**
- Merkezi güncelleme dağıtım sunucusu
- API tabanlı güncelleme kontrol
- Güvenli dosya dağıtımı

### 🔔 **WordPress Benzeri Bildirimler**
- Admin panelinde güncelleme bildirimleri
- Tek tık ile güncelleme
- Progress bar ve durum göstergesi

## 📋 Sistem Bileşenleri

### 1. **Otomatik Güncelleme Sistemi**
- **Dosya**: `tools/auto-update-system.php`
- Değişiklikleri tespit eder
- Güncelleme paketleri oluşturur
- Sunucuya otomatik yükler

### 2. **Güncelleme Sunucusu**
- **Dosya**: `tools/update-server.php`
- API endpoints sağlar
- Güncelleme dosyalarını dağıtır
- Versiyon kontrolü yapar

### 3. **Admin Panel Bildirimleri**
- **Dosya**: `admin/update-notifications.php`
- WordPress benzeri bildirimler
- Tek tık güncelleme
- Detaylı changelog gösterimi

### 4. **Git Hook'ları**
- **Dosya**: `tools/git-hooks/post-commit`
- Commit sonrası otomatik çalışır
- Güncelleme paketi oluşturur
- Log tutar

## 🛠️ Kurulum

### 1. Git Hook'larını Kur
```bash
php tools/setup-git-hooks.php
```

### 2. Güncelleme Sunucusunu Yapılandır
```json
{
    "update_server_url": "https://updates.your-domain.com/api",
    "project_id": "nakliye-teklif-system",
    "auto_upload": true
}
```

### 3. Admin Panelini Kontrol Et
- Admin Panel → Dashboard
- Güncelleme bildirimleri otomatik görünür

## 💻 Kullanım

### **Localhost'ta Geliştirme:**

1. **Normal commit (otomatik güncelleme):**
```bash
git add .
git commit -m "Yeni özellik eklendi"
# → Otomatik güncelleme paketi oluşur
```

2. **Skip güncelleme:**
```bash
git commit -m "Minor fix [skip-update]"
# → Güncelleme paketi oluşmaz
```

3. **Manuel güncelleme:**
```bash
php tools/auto-update-system.php detect
```

### **Sunucuda (Admin Panel):**

1. **Otomatik bildirim:**
   - Güncelleme mevcut olduğunda banner gösterilir
   - "Şimdi Güncelle" butonuna tıklayın

2. **Manuel kontrol:**
   - Dashboard'ta "Güncelleme Kontrol" butonuna tıklayın

3. **Tek tık güncelleme:**
   - Bildirimde "Şimdi Güncelle" → Otomatik yükleme

## 🔧 Yapılandırma

### **Auto-Update Config** (`tools/auto-update-config.json`)
```json
{
    "enabled": true,
    "update_server_url": "https://updates.your-domain.com/api",
    "project_id": "nakliye-teklif-system",
    "auto_upload": false,
    "auto_notify": true,
    "min_changes": 1,
    "excluded_files": [
        "tools/",
        "backups/",
        "uploads/",
        "*.log",
        "*.backup.*"
    ]
}
```

### **Sistem Ayarları** (Veritabanı)
- `update_server_url`: Güncelleme sunucu adresi
- `project_id`: Proje kimliği
- `last_update_check`: Son kontrol zamanı
- `update_notification_shown`: Bildirim durumu

## 📊 Workflow

### **1. Geliştirme Süreci:**
```
Kod Değişikliği → Git Commit → Post-Commit Hook →
Güncelleme Paketi → Sunucuya Yükleme → Bildirim
```

### **2. Güncelleme Süreci:**
```
Admin Panel → Bildirim Göster → Kullanıcı Tıkla →
İndir & Yükle → Veritabanı Güncelle → Dosya Senkronize
```

## 🔍 İzleme ve Log

### **Log Dosyaları:**
- `tools/auto-update.log` - Otomatik işlemler
- `update_log.txt` - Güncelleme geçmişi
- `sync.log` - Senkronizasyon logları

### **Veritabanı Tabloları:**
- `system_updates` - Güncelleme geçmişi
- `migrations` - Migration geçmişi
- `system_backups` - Yedekleme geçmişi
- `system_settings` - Sistem ayarları

## 🛡️ Güvenlik

### **Otomatik Yedekleme:**
- Her güncelleme öncesi otomatik backup
- Veritabanı ve dosya yedekleme
- Geri alma desteği

### **Doğrulama:**
- SHA256 dosya doğrulama
- API key kimlik doğrulama
- Güvenli dosya aktarımı

### **Hata Yönetimi:**
- Transaction tabanlı güncelleme
- Rollback desteği
- Detaylı hata logları

## 🎯 WordPress Benzeri Özellikler

### **✅ Gerçekleştirilen:**
- Otomatik güncelleme tespiti
- Banner bildirimleri
- Tek tık güncelleme
- Progress göstergesi
- Changelog gösterimi
- Yedekleme sistemi

### **🔄 WordPress Benzeri Akış:**
1. **Bildirim**: "Yeni güncelleme mevcut!"
2. **Detay**: Changelog ve versiyon bilgisi
3. **Onay**: "Şimdi Güncelle" butonu
4. **Progress**: Yükleme durumu
5. **Tamamlama**: Başarı mesajı + sayfa yenileme

## 🚨 Sorun Giderme

### **Hook Çalışmıyor:**
```bash
# Hook izinlerini kontrol et
ls -la .git/hooks/post-commit

# Manuel test
php tools/auto-update-system.php detect
```

### **Güncelleme Başarısız:**
1. Log dosyalarını kontrol edin
2. Veritabanı yedeklerini kontrol edin
3. Dosya izinlerini kontrol edin

### **Bildirim Görünmüyor:**
```php
// Cache temizle
updateSystemSetting('last_update_check', 0);
updateSystemSetting('update_notification_shown', 0);
```

## 📞 Destek

Problem yaşadığınızda:
1. Log dosyalarını kontrol edin
2. Veritabanı ayarlarını doğrulayın
3. Hook yapılandırmasını kontrol edin
4. Manuel test yapın

## 🎉 Tamamlandı!

WordPress benzeri otomatik güncelleme sistemi başarıyla kuruldu! Artık:

- ✅ Her commit otomatik güncelleme paketi oluşturur
- ✅ Admin panelinde bildirimler gösterilir
- ✅ Tek tıkla güncelleme yapılabilir
- ✅ Otomatik yedekleme ve geri alma mevcut

**Sistem tamamen çalışır durumda! 🚀**
