# Git Tabanlı Otomatik Güncelleme Sistemi

Bu sistem, projenizin GitHub üzerinden otomatik güncelleme almasını sağlar. Hem local geliştirme hem de canlı sunucu ortamında çalışır.

## 🚀 Kurulum

### 1. GitHub Repository Oluşturma

1. GitHub'da yeni bir repository oluşturun: `erhan-nakliye-teklif`
2. Repository'yi public veya private yapabilirsiniz

### 2. Konfigürasyon

`tools/auto-update-config.json` dosyasını düzenleyin:

```json
{
    "github": {
        "owner": "salihbyk",
        "repo": "erhan-nakliye-teklif",
        "token": null  // Private repo için Personal Access Token
    }
}
```

### 3. Admin Panel Konfigürasyonu

`admin/update-manager.php` dosyasındaki GitHub config'i güncelleyin:

```php
$gitUpdateConfig = [
    'repo_owner' => 'salihbyk',
    'repo_name' => 'erhan-nakliye-teklif',
    'github_token' => null // Private repo için token
];
```

## 📦 Paket Oluşturma ve Yayınlama

### Otomatik Release (Önerilen)

1. **Terminal/PowerShell'de:**
```bash
cd C:\laragon\www\erhan\tools
php git-package-creator.php create patch "Açıklama"
```

Versiyon tipleri:
- `patch`: 2.0.0 → 2.0.1 (küçük düzeltmeler)
- `minor`: 2.0.0 → 2.1.0 (yeni özellikler)
- `major`: 2.0.0 → 3.0.0 (büyük değişiklikler)

2. **Git'e yükleme:**
Script size soracak, "y" diyerek otomatik Git release oluşturabilirsiniz.

### Manuel Release

1. **Version dosyasını güncelleyin:**
```bash
echo "2.0.1" > version.txt
```

2. **Git commit ve tag:**
```bash
git add -A
git commit -m "Release v2.0.1: Yeni özellikler"
git tag -a v2.0.1 -m "Version 2.0.1"
git push origin main
git push origin v2.0.1
```

3. GitHub Actions otomatik olarak release oluşturacak.

## 🔄 Güncelleme Kontrolü ve Yükleme

### Admin Panel Üzerinden

1. Admin Panel > Sistem Güncelleme sayfasına gidin
2. "GitHub Güncellemeleri" bölümünde "Güncellemeleri Kontrol Et" butonuna tıklayın
3. Yeni güncelleme varsa "Güncellemeyi Yükle" butonuna tıklayın
4. Sistem otomatik olarak:
   - Mevcut sistemi yedekler
   - Güncellemeyi indirir
   - Dosyaları günceller
   - Migration'ları çalıştırır

### Terminal Üzerinden

```bash
# Güncelleme kontrolü
php tools/git-auto-update.php check

# Güncelleme yükleme
php tools/git-auto-update.php install
```

## 🔐 Private Repository için GitHub Token

Private repository kullanıyorsanız:

1. GitHub > Settings > Developer settings > Personal access tokens
2. "Generate new token" ile yeni token oluşturun
3. Gerekli izinler: `repo` (Full control of private repositories)
4. Token'ı `auto-update-config.json` dosyasına ekleyin

## 📁 Dosya Yapısı

```
erhan/
├── .github/
│   └── workflows/
│       ├── create-release.yml      # Otomatik release workflow
│       └── manual-release.yml      # Manuel release workflow
├── tools/
│   ├── git-auto-update.php        # Git güncelleme sistemi
│   ├── git-package-creator.php    # Paket oluşturma aracı
│   └── auto-update-config.json    # Konfigürasyon dosyası
├── admin/
│   └── update-manager.php         # Güncelleme yönetimi sayfası
└── version.txt                    # Mevcut versiyon
```

## 🛡️ Güvenlik Notları

1. **Hassas Dosyalar:** `config/database.php` gibi dosyalar otomatik olarak hariç tutulur
2. **Yedekleme:** Her güncelleme öncesi otomatik yedek alınır
3. **Rollback:** Hata durumunda otomatik geri alma yapılır

## ⚡ Hızlı Başlangıç

1. İlk kurulum için projeyi Git'e yükleyin:
```bash
git init
git add -A
git commit -m "Initial commit"
git remote add origin https://github.com/salihbyk/erhan-nakliye-teklif.git
git push -u origin main
```

2. İlk release'i oluşturun:
```bash
cd tools
php git-package-creator.php create major "İlk sürüm"
```

## 🐛 Sorun Giderme

### "Güncelleme kontrolü başarısız" hatası
- GitHub kullanıcı adı ve repo adını kontrol edin
- Private repo ise token'ı kontrol edin

### "ZIP dosyası açılamadı" hatası
- PHP ZIP extension'ının yüklü olduğundan emin olun
- Temp dizinine yazma izinlerini kontrol edin

### Migration hataları
- SQL dosyalarında syntax hatası olup olmadığını kontrol edin
- Database kullanıcısının gerekli izinlere sahip olduğundan emin olun

## 📞 Destek

Sorunlar için GitHub Issues bölümünü kullanabilirsiniz.
