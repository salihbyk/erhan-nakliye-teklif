# Changelog v2.8.0 (05.01.2026)

## Düzeltmeler
- **Birim m³ Fiyatı Gösterimi:** Boş bırakılan veya 0 olarak girilen birim m³ fiyatı artık teklif görünümünde görünmüyor
  - Müşteri görünümünde (view-quote.php) kontrol eklendi
  - PDF çıktısında (view-quote-pdf.php) kontrol eklendi
  - Admin panelinde (admin/view-quote.php) kontrol eklendi
  - Sadece 0'dan büyük değerler gösterilecek şekilde düzeltildi

## İyileştirmeler
- **Kullanıcı Deneyimi:** Gereksiz "0,00 EUR" gösterimi kaldırıldı
- **Daha Temiz Teklif Görünümü:** Sadece doldurulmuş alanlar gösteriliyor

## Güncellenen Dosyalar
- `view-quote.php` - Birim fiyat kontrolü eklendi
- `view-quote-pdf.php` - Birim fiyat kontrolü eklendi
- `admin/view-quote.php` - Birim fiyat kontrolü eklendi

## Teknik Detaylar
- PHP'de `empty()` kontrolüne ek olarak `> 0` koşulu eklendi
- Tüm teklif görüntüleme dosyalarında tutarlı kontrol sağlandı
