-- Referans görselleri varsayılan değerini 0 (Hayır) olarak güncelle
-- Önce mevcut kolon varsayılan değerini değiştir
ALTER TABLE quotes
MODIFY COLUMN show_reference_images TINYINT(1) DEFAULT 0
COMMENT 'Müşteri görünümünde referans görsellerin gösterilip gösterilmeyeceği';

-- Varolan kayıtlarda NULL olanları 0 yap (varsayılan Hayır)
UPDATE quotes
SET show_reference_images = 0
WHERE show_reference_images IS NULL;
