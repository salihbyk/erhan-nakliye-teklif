-- Konteyner tipi sütunu ekleme
-- Denizyolu taşımacılığı için konteyner tipi bilgisi

-- Quotes tablosuna konteyner tipi sütunu ekle
ALTER TABLE quotes ADD COLUMN container_type VARCHAR(20) DEFAULT NULL AFTER transport_mode_id;

-- Konteyner tipi değerleri: '20FT', '40FT', '40FT_HC'
-- Sadece denizyolu taşımacılığında kullanılacak

-- Örnek veriler:
-- '20FT' = 20 Feet Container
-- '40FT' = 40 Feet Container
-- '40FT_HC' = 40 Feet High Cube Container

-- Konteyner tipi kontrolü için constraint (isteğe bağlı)
-- ALTER TABLE quotes ADD CONSTRAINT chk_container_type CHECK (container_type IN ('20FT', '40FT', '40FT_HC') OR container_type IS NULL);

-- Mevcut denizyolu tekliflerini kontrol et
SELECT
    q.id,
    q.quote_number,
    tm.name as transport_mode,
    q.container_type
FROM quotes q
LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
WHERE tm.name LIKE '%deniz%' OR tm.name LIKE '%sea%'
ORDER BY q.created_at DESC;