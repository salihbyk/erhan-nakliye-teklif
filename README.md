# Nakliye Teklif Sistemi

Modern ve kullanıcı dostu nakliye teklif oluşturma ve yönetim sistemi.

## 🚀 Özellikler

### Ana Özellikler
- **İlerlemeli Form**: 3 adımlı kullanıcı dostu teklif formu
- **Çoklu Taşıma Modu**: Karayolu, Havayolu, Deniz Yolu, Konteyner
- **Otomatik Fiyatlandırma**: Dinamik fiyat hesaplama sistemi
- **E-posta Bildirimleri**: Otomatik teklif e-postaları
- **Teklif Görüntüleme**: Web üzerinden teklif detayları
- **Admin Panel**: Kapsamlı yönetim paneli

### Admin Panel Özellikleri
- **Dashboard**: İstatistikler ve grafikler
- **Teklif Yönetimi**: Tüm teklifleri görüntüleme ve düzenleme
- **Müşteri Yönetimi**: Müşteri bilgileri ve geçmiş
- **Taşıma Modları**: Şablonları ve fiyatları düzenleme
- **E-posta Logları**: Gönderilen e-postaları takip etme
- **Kullanıcı Yönetimi**: Admin kullanıcı rolleri

## 🛠️ Teknolojiler

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 8.0+, MySQL 8.0+
- **Kütüphaneler**:
  - Font Awesome (İkonlar)
  - Chart.js (Grafikler)
  - Bootstrap (UI Framework)

## 📋 Kurulum

### Gereksinimler
- XAMPP, WAMP veya benzeri PHP sunucusu
- PHP 8.0 veya üzeri
- MySQL 8.0 veya üzeri
- mod_rewrite aktif

### Adım Adım Kurulum

1. **Projeyi İndirin**
   ```bash
   git clone https://github.com/username/nakliye-teklif-sistemi.git
   cd nakliye-teklif-sistemi
   ```

2. **Veritabanını Oluşturun**
   - phpMyAdmin'e gidin
   - `setup/database.sql` dosyasını çalıştırın
   - Veritabanı otomatik olarak oluşturulacak

3. **Veritabanı Ayarlarını Düzenleyin**
   - `config/database.php` dosyasını açın
   - Veritabanı bilgilerinizi girin:
   ```php
   private $host = 'localhost';
   private $db_name = 'nakliye_teklif';
   private $username = 'root';
   private $password = '';
   ```

4. **E-posta Ayarlarını Yapın**
   - `includes/functions.php` dosyasında e-posta ayarlarını düzenleyin
   - SMTP kullanmak için PHPMailer entegrasyonu yapabilirsiniz

5. **Projeyi Çalıştırın**
   - XAMPP/WAMP'ı başlatın
   - Proje dosyalarını `htdocs` klasörüne kopyalayın
   - Tarayıcıda `http://localhost/nakliye-teklif-sistemi` adresine gidin

## 🔐 Varsayılan Giriş Bilgileri

**Admin Panel:**
- Kullanıcı Adı: `admin`
- Şifre: `admin123`
- URL: `http://localhost/nakliye-teklif-sistemi/admin/`

## 📝 Kullanım

### Müşteri Tarafı

1. **Teklif Formu Doldurma**
   - Müşteri bilgilerini girin
   - Taşıma modunu seçin
   - Yük detaylarını belirtin
   - Teklifi gönderin

2. **Teklif Görüntüleme**
   - E-postada gelen linke tıklayın
   - Teklif detaylarını inceleyin
   - İletişim butonlarını kullanın

### Admin Tarafı

1. **Dashboard**
   - Genel istatistikleri görüntüleyin
   - Son teklifleri inceleyin
   - Aylık grafikleri takip edin

2. **Teklif Yönetimi**
   - Tüm teklifleri listeleyin
   - Teklif durumlarını güncelleyin
   - Detayları düzenleyin

3. **Taşıma Modları**
   - Fiyatları güncelleyin
   - E-posta şablonlarını düzenleyin
   - Modları aktif/pasif yapın

## 🎨 Özelleştirme

### Taşıma Modu Ekleme

Yeni taşıma modu eklemek için:

1. Veritabanında `transport_modes` tablosuna kayıt ekleyin
2. İkon için Font Awesome sınıfı kullanın
3. E-posta şablonunu oluşturun

### E-posta Şablonları

E-posta şablonlarında kullanılabilir değişkenler:
- `{customer_name}`: Müşteri adı
- `{origin}`: Çıkış noktası
- `{destination}`: Varış noktası
- `{weight}`: Ağırlık
- `{volume}`: Hacim
- `{pieces}`: Parça sayısı
- `{price}`: Fiyat
- `{quote_number}`: Teklif numarası
- `{valid_until}`: Geçerlilik tarihi

### Fiyat Hesaplama

Fiyat hesaplama formülü:
```
Toplam Fiyat = Taban Fiyat + (Ağırlık × Kg Fiyatı) + (Mesafe × Km Fiyatı) + (Hacim × M³ Fiyatı)
```

Minimum fiyat kontrolü yapılır.

## 📁 Dosya Yapısı

```
nakliye-teklif-sistemi/
├── admin/                  # Admin panel
│   ├── index.php          # Dashboard
│   ├── login.php          # Giriş sayfası
│   ├── transport-modes.php # Taşıma modları yönetimi
│   └── logout.php         # Çıkış
├── api/                   # API endpoint'leri
│   └── submit-quote.php   # Teklif gönderme
├── assets/                # Statik dosyalar
│   ├── css/
│   │   └── style.css     # Ana stilleri
│   └── js/
│       └── script.js     # JavaScript dosyaları
├── config/                # Konfigürasyon
│   └── database.php      # Veritabanı bağlantısı
├── includes/              # Yardımcı dosyalar
│   └── functions.php     # Fonksiyonlar
├── setup/                 # Kurulum dosyaları
│   └── database.sql      # Veritabanı scripti
├── index.html            # Ana sayfa
├── view-quote.php        # Teklif görüntüleme
└── README.md             # Bu dosya
```

## 🔧 Konfigürasyon

### Veritabanı Ayarları
`config/database.php` dosyasında:
- Host, kullanıcı adı, şifre
- Veritabanı adı
- Karakter seti

### E-posta Ayarları
`includes/functions.php` dosyasında:
- SMTP bilgileri
- Gönderen adresi
- E-posta başlıkları

### Fiyat Ayarları
Admin panelinden:
- Taşıma modu fiyatları
- Minimum fiyatlar
- Hesaplama katsayıları

## 🛡️ Güvenlik

- SQL injection koruması (PDO prepared statements)
- XSS koruması (htmlspecialchars)
- Admin oturum kontrolü
- CSRF koruması (session token)
- Input validasyonu

## 🧪 Test

### Fonksiyonel Test
1. Teklif formu doldurma
2. E-posta gönderimi
3. Teklif görüntüleme
4. Admin panel işlemleri

### Tarayıcı Desteği
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 📱 Responsive Tasarım

- Mobile-first yaklaşım
- Bootstrap 5 grid sistemi
- Dokunmatik ekran desteği
- Farklı ekran boyutları için optimize

## 🔄 Güncellemeler

### v1.0.0 (İlk Sürüm)
- Temel teklif sistemi
- Admin panel
- E-posta bildirimleri
- Responsive tasarım

### Planlanan Özellikler
- SMS bildirimleri
- PDF teklif oluşturma
- Çoklu dil desteği
- API entegrasyonları
- Raporlama modülü

## 🤝 Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/AmazingFeature`)
3. Commit yapın (`git commit -m 'Add some AmazingFeature'`)
4. Push edin (`git push origin feature/AmazingFeature`)
5. Pull Request açın

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasını inceleyin.

## 🆘 Destek

Sorularınız veya sorunlarınız için:
- Issue açın
- E-posta: destek@nakliye.com
- Telefon: +90 (212) 555-0123

## 👥 Yazarlar

- **Geliştirici** - İlk çalışma - [GitHub](https://github.com/username)

## 🙏 Teşekkürler

- Bootstrap ekibine responsive framework için
- Font Awesome ekibine ikonlar için
- Chart.js ekibine grafikler için