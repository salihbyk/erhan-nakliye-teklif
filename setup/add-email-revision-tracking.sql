-- Email ve revizyon takibi için alanlar ekle
ALTER TABLE quotes ADD COLUMN email_sent_at TIMESTAMP NULL;
ALTER TABLE quotes ADD COLUMN email_sent_count INT DEFAULT 0;
ALTER TABLE quotes ADD COLUMN revision_number INT DEFAULT 0;
ALTER TABLE quotes ADD COLUMN parent_quote_id INT NULL;
ALTER TABLE quotes ADD COLUMN is_active BOOLEAN DEFAULT 1;

-- Index'ler ekle
ALTER TABLE quotes ADD INDEX idx_email_sent (email_sent_at);
ALTER TABLE quotes ADD INDEX idx_revision (revision_number);
ALTER TABLE quotes ADD INDEX idx_parent_quote (parent_quote_id);
ALTER TABLE quotes ADD INDEX idx_active (is_active);

-- Parent quote için foreign key
ALTER TABLE quotes ADD FOREIGN KEY (parent_quote_id) REFERENCES quotes(id) ON DELETE SET NULL;