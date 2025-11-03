-- GBP para birimini ekle

-- additional_costs tablosuna GBP ekle
ALTER TABLE additional_costs MODIFY COLUMN currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'TL';

-- payments tablosuna GBP ekle
ALTER TABLE payments MODIFY COLUMN currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'TL';

-- quote_templates tablosuna GBP ekle
ALTER TABLE quote_templates MODIFY COLUMN currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'TL';

-- email_templates tablosuna GBP ekle (varsa)
ALTER TABLE email_templates MODIFY COLUMN currency ENUM('TL', 'USD', 'EUR', 'GBP') DEFAULT 'TL';

