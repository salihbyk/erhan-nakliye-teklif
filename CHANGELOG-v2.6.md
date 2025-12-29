# Changelog v2.6 (29.12.2025)

## Yeni Özellikler
- **Parsiyel Taşıma:** İhracat/İthalat seçildiğinde parsiyel taşıma seçeneği eklendi
  - iOS tarzı toggle switch ile kullanıcı dostu arayüz
  - Taşıma modu değiştiğinde otomatik temizlenme
  - Teklif görünümünde taşıma türüyle entegre gösterim (ör: "Denizyolu / Parsiyel Taşıma")
  
- **Birim m³ Fiyatı:** Teklif oluşturma ve görüntüleme sistemi genişletildi
  - Para birimi seçimi (EUR, USD, TL, GBP)
  - Müşteri ve admin görünümlerinde gösterim
  - PDF'de tam destek

## İyileştirmeler
- **Alan Düzeni:** Çıkış Noktası ve Varış Noktası sol kolona taşındı
- **PDF Düzeni:** "Taşımaya Dair Genel Bilgiler" alanı müşteri görünümüyle aynı şekilde düzenlendi
- **Admin Arayüzü:** Para birimi dropdown'ında ok simgesi kaldırılarak daha temiz görünüm sağlandı
- **Hacim Gösterimi:** Sağa hizalama düzeltmesi yapıldı

## Veritabanı Değişiklikleri
- `quotes` tablosuna `partial_transport` kolonu eklendi (TINYINT)
- `quotes` tablosuna `unit_price_currency` kolonu eklendi (ENUM: TL, USD, EUR, GBP)

## Migration Dosyaları
- `setup/add-partial-transport-column.sql`
- `setup/run-partial-transport-migration.php`
- `setup/add-unit-price-currency.sql`
- `setup/run-unit-price-currency-migration.php`

## Güncellenen Dosyalar
- `index.php` - Parsiyel taşıma toggle'ı eklendi
- `assets/js/script.js` - Parsiyel taşıma mantığı ve otomatik temizleme
- `api/submit-quote.php` - Parsiyel taşıma ve para birimi desteği
- `api/update-general-info.php` - Tüm alanlar için genel güncelleme sistemi
- `view-quote.php` - Müşteri görünümü düzenlemeleri
- `view-quote-pdf.php` - PDF layout iyileştirmeleri
- `admin/view-quote.php` - Admin inline düzenleme ve para birimi seçici

## Teknik Detaylar
- Parsiyel taşıma bilgisi artık ayrı satır yerine taşıma türünde gösteriliyor
- Tüm görünümlerde (müşteri, admin, PDF) tutarlı layout sağlandı
- API endpoint'leri genişletilerek daha fazla alan düzenlenebilir hale getirildi
