-- Şablon tablosuna dinamik alanlar için sütunlar ekle
ALTER TABLE quote_templates
ADD COLUMN services_title VARCHAR(255) DEFAULT 'Hizmetlerimiz' AFTER terms_content,
ADD COLUMN transport_process_title VARCHAR(255) DEFAULT 'Taşıma Süreci' AFTER services_title,
ADD COLUMN terms_title VARCHAR(255) DEFAULT 'Şartlar ve Koşullar' AFTER transport_process_title,
ADD COLUMN dynamic_sections JSON DEFAULT NULL AFTER terms_title;

-- Mevcut şablonlar için varsayılan başlıkları güncelle
UPDATE quote_templates
SET
    services_title = 'Hizmetlerimiz',
    transport_process_title = 'Taşıma Süreci',
    terms_title = 'Şartlar ve Koşullar'
WHERE services_title IS NULL OR transport_process_title IS NULL OR terms_title IS NULL;
