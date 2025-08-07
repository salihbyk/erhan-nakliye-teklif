-- Telefon alanının uzunluğunu artır
ALTER TABLE customers MODIFY COLUMN phone VARCHAR(25) NOT NULL;

-- İndeksleri güncelle (gerekirse)
-- Bu komut hata verirse devam et, zaten var demektir
ALTER TABLE customers DROP INDEX idx_phone;
ALTER TABLE customers ADD INDEX idx_phone (phone);