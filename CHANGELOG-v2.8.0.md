# Changelog v2.8.0 (08.01.2026)

## Yeni Özellikler
- **🎯 Özel Alanlar Sıralama:** "Yeni Alan" bölümündeki alanlar artık sürükle-bırak ile sıralanabilir
  - Her alan için sola ve sağa taşıma butonları eklendi
  - Admin görünümünde yapılan sıralama müşteri görünümü ve PDF'te korunuyor
  - Sıralama otomatik olarak veritabanına kaydediliyor
  - `custom_fields_order` sütunu ile sıralama bilgisi saklanıyor

## Düzeltmeler
- **Birim m³ Fiyatı Gösterimi:** Boş bırakılan veya 0 olarak girilen birim m³ fiyatı artık teklif görünümünde görünmüyor
  - Müşteri görünümünde (view-quote.php) kontrol eklendi
  - PDF çıktısında (view-quote-pdf.php) kontrol eklendi
  - Admin panelinde (admin/view-quote.php) kontrol eklendi
  - Sadece 0'dan büyük değerler gösterilecek şekilde düzeltildi

## İyileştirmeler
- **Kullanıcı Deneyimi:** Gereksiz "0,00 EUR" gösterimi kaldırıldı
- **Daha Temiz Teklif Görünümü:** Sadece doldurulmuş alanlar gösteriliyor
- **Esnek Alan Yönetimi:** Özel alanlar istenen sırada görüntülenebiliyor

## Güncellenen Dosyalar
- `admin/view-quote.php` - Sürükle-bırak butonları ve sıralama fonksiyonları eklendi
- `view-quote.php` - Müşteri görünümünde sıralama desteği eklendi
- `view-quote-pdf.php` - PDF görünümünde sıralama desteği eklendi
- `api/update-general-info.php` - Sıralama kaydetme API endpoint'i eklendi
- `setup/add-custom-fields-order-column.php` - Veritabanı migration dosyası

## Veritabanı Değişiklikleri
- `quotes` tablosuna `custom_fields_order` TEXT sütunu eklendi
- Sıralama bilgisi JSON formatında saklanıyor

## Teknik Detaylar
- PHP'de `empty()` kontrolüne ek olarak `> 0` koşulu eklendi
- JavaScript ile drag-and-drop benzeri sıralama fonksiyonları eklendi
- Custom field'ların sırası veritabanında kalıcı olarak saklanıyor
- Tüm görünümlerde (admin, müşteri, PDF) aynı sıralama kullanılıyor
