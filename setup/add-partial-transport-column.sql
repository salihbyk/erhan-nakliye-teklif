-- Parsiyel taşıma alanını ekle
ALTER TABLE quotes
ADD COLUMN partial_transport TINYINT(1) DEFAULT 0 AFTER trade_type;

-- Index ekle (opsiyonel - sorgulama performansı için)
ALTER TABLE quotes
ADD INDEX idx_partial_transport (partial_transport);
