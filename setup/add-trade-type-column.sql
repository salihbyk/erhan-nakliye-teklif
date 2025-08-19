-- Trade type kolonu ekleme
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS trade_type VARCHAR(20) DEFAULT 'export' AFTER transport_mode_id;

-- Mevcut kayıtları güncelle
UPDATE email_templates SET trade_type = 'export' WHERE trade_type IS NULL OR trade_type = '';
