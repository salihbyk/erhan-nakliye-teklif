-- Maliyet listesi tablosu oluşturma
CREATE TABLE IF NOT EXISTS cost_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    transport_mode_id INT,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transport_mode (transport_mode_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE SET NULL
);

-- Quotes tablosuna cost_list_id alanı ekle
ALTER TABLE quotes ADD COLUMN cost_list_id INT DEFAULT NULL;
ALTER TABLE quotes ADD INDEX idx_cost_list (cost_list_id);
ALTER TABLE quotes ADD FOREIGN KEY (cost_list_id) REFERENCES cost_lists(id) ON DELETE SET NULL;