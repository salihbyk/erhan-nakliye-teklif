# Changelog v2.5 (23.12.2025)

## Yeni Özellikler
- **Dış Sistem API'si:** Başka sistemlerin verileri (müşteriler, teklifler, onay ve ödeme durumları) okuyabilmesi için `api/external-data.php` uç noktası eklendi.
- **API Güvenliği:** API erişimi için `X-API-KEY` tabanlı kimlik doğrulama sistemi kuruldu.

## İyileştirmeler
- Sistem versiyonu v2.5'e yükseltildi.
- Güncelleme paketleme sistemi optimize edildi.

## Teknik Detaylar
- Yeni API dosyası: `api/external-data.php`
- Versiyon dosyası: `version.txt`
- Veritabanı: `system_settings` tablosundaki `system_version` güncellendi.
