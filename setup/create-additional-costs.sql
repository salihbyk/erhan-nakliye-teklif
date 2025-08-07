-- Ek maliyetler tablosu
CREATE TABLE IF NOT EXISTS additional_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency ENUM('TL', 'USD', 'EUR') DEFAULT 'TL',
    is_additional BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index'ler
CREATE INDEX idx_additional_costs_quote_id ON additional_costs(quote_id);