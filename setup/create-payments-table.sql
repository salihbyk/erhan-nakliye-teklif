-- Ödeme işlemleri tablosu
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    payment_type ENUM('kaparo', 'ara_odeme', 'kalan_bakiye', 'toplam_bakiye') DEFAULT 'kaparo',
    amount DECIMAL(10,2) NOT NULL,
    currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
    payment_date DATE NOT NULL,
    payment_method VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_quote_id (quote_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;