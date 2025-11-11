-- greeting_text kolonu ekle
ALTER TABLE quotes ADD COLUMN greeting_text TEXT DEFAULT NULL AFTER intro_text;

