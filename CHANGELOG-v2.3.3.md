# Versiyon 2.3.3 Değişiklikleri

**Yayın Tarihi:** 2025-01-23

## 🎉 Yeni Özellikler

### CC Email Desteği
- Teklif formu formulünde "CC E-posta Adresi" alanı eklendi
- Mail gönderilirken CC adresine de kopya gönderilebiliyor
- Müşteri veritabanında cc_email alanı eklendi
- İsteğe bağlı alan (zorunlu değil)

### Description (Ek Açıklama) İyileştirmeleri
- Ek Açıklama alanı müşteri görünümünde gösteriliyor
- Admin panelde düzenlenebilir olarak eklendi
- PDF çıktısında tam genişlikte gösteriliyor
- Çok satırlı metin desteği

### Currency Enum Düzeltmesi
- quote_templates tablosunda para birimi enum'u düzeltildi
- TL yerine ISO standardı TRY kullanılıyor
- USD, EUR, TRY seçenekleri mevcut

## 🔧 Teknik İyileştirmeler

### Veritabanı Migration Sistemi
- Otomatik migration sistemi iyileştirildi
- `setup/migration_v2_3_3.php` dosyası eklendi
- `update_config.json` otomatik oluşturuluyor
- Migration'lar güncelleme sırasında otomatik çalışıyor

### Dosyalar

**Yeni Dosyalar:**
- `setup/add-cc-email-column.php` - CC email migration
- `setup/fix-currency-enum.php` - Currency enum migration
- `setup/migration_v2_3_3.php` - Ana migration dosyası

**Güncellenen Dosyalar:**
- `index.php` - CC email formu
- `assets/js/script.js` - Form verisi toplama
- `api/submit-quote.php` - CC email kaydetme
- `api/send-quote-email.php` - CC email gönderme
- `view-quote.php` - Description görünümü
- `admin/view-quote.php` - Description düzenleme
- `view-quote-pdf.php` - PDF description
- `tools/git-package-creator.php` - Update config oluşturma
- `admin/update-manager.php` - Migration sistemi

## 📝 Güncelleme Talimatları

### Domain'de Kurulu Sistemi Güncelleme

1. **Admin Panel > Güncelleme Yöneticisi**'ne gidin
2. "Güncelleme Kontrolü" butonuna tıklayın
3. v2.3.3 güncellemesi görünecektir
4. "Güncellemeyi Yükle" butonuna tıklayın
5. Sistem otomatik olarak:
   - Veritabanı yedeği alacak
   - Yeni dosyaları kopyalayacak
   - Migration'ları çalıştıracak
   - Sistem versiyonunu güncelleyecek

### Manuel Migration (Gerekirse)

Eğer otomatik güncelleme çalışmazsa:

```bash
cd setup/
php migration_v2_3_3.php
```

## ⚠️ Önemli Notlar

- Güncelleme öncesi **mutlaka yedek alın**
- cc_email kolonu customers tablosuna ekleniyor
- quote_templates tablosunda currency enum değişiyor
- Mevcut veriler korunuyor, kayıp olmayacak

## 🐛 Bilinen Sorunlar

Yok

## 💡 İpuçları

- CC email alanı isteğe bağlıdır, boş bırakılabilir
- Description alanı admin panelde tıklayarak düzenlenebilir
- PDF'te description alanı otomatik olarak görünür

