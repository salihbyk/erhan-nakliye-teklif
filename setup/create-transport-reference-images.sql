-- Transport mode referans resimleri için tablo
CREATE TABLE IF NOT EXISTS transport_reference_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transport_mode_id INT NOT NULL,
    image_name VARCHAR(255) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_description TEXT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id) ON DELETE CASCADE,
    INDEX idx_transport_mode (transport_mode_id),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
);

-- Uploads klasörü için dizin yapısı
-- uploads/transport-images/{transport_mode_slug}/