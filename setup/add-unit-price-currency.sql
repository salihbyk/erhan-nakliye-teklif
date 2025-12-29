-- Birim fiyat için para birimi alanı ekleme
ALTER TABLE quotes 
ADD COLUMN unit_price_currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'EUR' 
AFTER unit_price;
