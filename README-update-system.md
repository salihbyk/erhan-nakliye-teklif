# Güncelleme Sistemi Dokümantasyonu

Bu belge, nakliye teklif sistemi için oluşturulan güncelleme sisteminin kullanımını açıklar.

## Sistem Özellikleri

### 1. Otomatik Güncelleme Yöneticisi
- **Dosya**: `admin/update-manager.php`
- Admin paneli üzerinden ZIP dosyası yükleme
- Otomatik veritabanı yedekleme
- Migration çalıştırma
- Dosya senkronizasyonu

### 2. Güncelleme Paketleme Scripti
- **Dosya**: `tools/package-update.php`
- Değişiklikleri otomatik tespit etme
- ZIP paketi oluşturma
- Migration dosyalarını dahil etme

### 3. Otomatik Senkronizasyon
- **Dosya**: `tools/auto-sync.php`
- FTP/SFTP/Rsync desteği
- Değişen dosyaları otomatik yükleme
- Yedekleme ve geri alma

## Kullanım Rehberi

### Localhost'ta Geliştirme

1. **Değişikliklerinizi yapın**
   ```bash
   # Normal geliştirme işlemleri
   ```

2. **Güncelleme paketi oluşturun**
   ```bash
   cd tools
   php package-update.php "1.1.0" "Yeni özellikler eklendi"
   ```

3. **Otomatik paketleme (Web arayüzü)**
   ```
   http://localhost/erhan/tools/package-update.php
   ```

### Sunucuya Yükleme

#### Yöntem 1: Admin Panel (Önerilen)
1. Admin paneline giriş yapın
2. "Sistem Güncelleme" menüsüne gidin
3. Oluşturulan ZIP dosyasını yükleyin
4. "Güncellemeyi Yükle" butonuna tıklayın

#### Yöntem 2: Otomatik Senkronizasyon
1. Senkronizasyon ayarlarını yapın:
   ```bash
   cd tools
   php auto-sync.php
   ```

2. FTP/SFTP bilgilerini girin
3. Otomatik senkronizasyon başlatın

## Dosya Yapısı

### Güncelleme Paketi İçeriği
```
update_package_1.1.0.zip
├── update_config.json      # Yapılandırma
├── files/                  # Güncellenecek dosyalar
│   ├── admin/
│   ├── api/
│   └── ...
└── migrations/             # Veritabanı değişiklikleri
    ├── add-new-feature.sql
    └── ...
```

### Yapılandırma Dosyası (update_config.json)
```json
{
    "version": "1.1.0",
    "description": "Yeni özellikler eklendi",
    "created_at": "2025-01-15 10:30:00",
    "migrations": [
        "add-new-feature.sql"
    ],
    "files": [
        {
            "source": "admin/index.php",
            "target": "admin/index.php"
        }
    ]
}
```

## Veritabanı Tabloları

### system_updates
Güncelleme geçmişini tutar:
```sql
id, version, description, update_date, success, backup_file, notes
```

### migrations
Migration geçmişini tutar:
```sql
id, migration_name, executed_at, version, success, error_message
```

### system_backups
Yedekleme geçmişini tutar:
```sql
id, backup_type, backup_file, backup_size, created_at, description, status
```

## Güvenlik Önlemleri

1. **Yedekleme**: Her güncelleme öncesi otomatik yedekleme
2. **Doğrulama**: ZIP dosyası içeriği kontrol edilir
3. **Geri alma**: Yedek dosyalarla geri alma mümkün
4. **Hata yönetimi**: Hata durumunda işlem durdurulur

## Sorun Giderme

### Güncelleme Başarısız
1. Hata loglarını kontrol edin
2. Yedek dosyalardan geri alın
3. Dosya izinlerini kontrol edin

### Veritabanı Hatası
1. Migration dosyalarını kontrol edin
2. Yedek veritabanını geri yükleyin
3. Manuel SQL çalıştırın

### Dosya Yükleme Hatası
1. PHP upload limitlerini kontrol edin
2. Dosya izinlerini kontrol edin
3. Disk alanını kontrol edin

## Komut Satırı Kullanımı

### Paket Oluşturma
```bash
php tools/package-update.php "1.1.0" "Açıklama"
```

### Senkronizasyon
```bash
# Normal senkronizasyon
php tools/auto-sync.php

# Test modu
php tools/auto-sync.php ftp --dry-run

# Belirli yöntemle
php tools/auto-sync.php sftp
```

## Yapılandırma

### Senkronizasyon Ayarları (sync-config.json)
```json
{
    "sync_methods": {
        "ftp": {
            "enabled": true,
            "host": "ftp.example.com",
            "username": "user",
            "password": "pass",
            "remote_path": "/public_html/"
        }
    },
    "excluded_paths": [
        "tools/",
        "backups/",
        "config/database.php"
    ]
}
```

## İpuçları

1. **Düzenli yedekleme**: Haftalık otomatik yedekleme aktif
2. **Test etme**: Dry-run modu ile test edin
3. **Versiyon takibi**: Semantic versioning kullanın
4. **Migration**: Veri değişiklikleri için migration kullanın
5. **Monitoring**: Log dosyalarını düzenli kontrol edin

## Destek

Sorun yaşadığınızda:
1. Log dosyalarını kontrol edin (`update_log.txt`, `sync.log`)
2. Sistem bilgilerini kontrol edin (Admin Panel > Sistem Güncelleme)
3. Yedek dosyalarınızın varlığını doğrulayın
