-- Quotes tablosuna custom_fields sütunu ekleme
-- Bu sütun genel bilgiler alanına eklenen özel alanları JSON formatında saklayacak

ALTER TABLE quotes
ADD COLUMN custom_fields TEXT
COMMENT 'JSON formatında özel alanları saklar'
AFTER show_reference_images;

-- Varolan kayıtlar için varsayılan değer
UPDATE quotes SET custom_fields = '{}' WHERE custom_fields IS NULL;