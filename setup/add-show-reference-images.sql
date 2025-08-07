-- Quotes tablosuna show_reference_images alanı ekle
ALTER TABLE quotes
ADD COLUMN show_reference_images TINYINT(1) DEFAULT 0
COMMENT 'Müşteri görünümünde referans görsellerin gösterilip gösterilmeyeceği';