-- Nakliye Teklif Sistemi Veritabanı

CREATE DATABASE IF NOT EXISTS nakliye_teklif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nakliye_teklif;

-- Müşteriler tablosu
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(25) NOT NULL,
    company VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

-- Taşıma modları tablosu
CREATE TABLE transport_modes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(100),
    base_price DECIMAL(10,2) DEFAULT 0.00,
    price_per_kg DECIMAL(10,4) DEFAULT 0.00,
    price_per_km DECIMAL(10,4) DEFAULT 0.00,
    price_per_m3 DECIMAL(10,4) DEFAULT 0.00,
    min_price DECIMAL(10,2) DEFAULT 0.00,
    template TEXT,
    email_template TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Teklifler tablosu
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    transport_mode_id INT NOT NULL,
    origin VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    weight DECIMAL(10,2) NOT NULL,
    volume DECIMAL(10,3),
    pieces INT,
    cargo_type ENUM('genel', 'hassas', 'soguk', 'tehlikeli'),
    description TEXT,
    calculated_price DECIMAL(10,2),
    final_price DECIMAL(10,2),
    status ENUM('pending', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
    valid_until DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id),
    INDEX idx_quote_number (quote_number),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Admin kullanıcıları tablosu
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'operator') DEFAULT 'operator',
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Mail logları tablosu
CREATE TABLE email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    body TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Varsayılan taşıma modlarını ekle
INSERT INTO transport_modes (name, slug, icon, base_price, price_per_kg, price_per_km, min_price, template) VALUES
('Karayolu', 'karayolu', 'fas fa-truck', 100.00, 2.50, 1.20, 150.00,
'<h4>Karayolu Taşımacılık Teklifi</h4>
<p><strong>Müşteri:</strong> {customer_name}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Yük Detayları:</strong></p>
<ul>
<li>Ağırlık: {weight} kg</li>
<li>Hacim: {volume} m³</li>
<li>Parça Sayısı: {pieces}</li>
</ul>
<p><strong>Tahmini Süre:</strong> 2-5 iş günü</p>
<p><strong>Toplam Fiyat:</strong> {price} TL (KDV Hariç)</p>'),

('Havayolu', 'havayolu', 'fas fa-plane', 250.00, 8.50, 0.00, 300.00,
'<h4>Havayolu Kargo Teklifi</h4>
<p><strong>Müşteri:</strong> {customer_name}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Yük Detayları:</strong></p>
<ul>
<li>Ağırlık: {weight} kg</li>
<li>Hacim: {volume} m³</li>
<li>Parça Sayısı: {pieces}</li>
</ul>
<p><strong>Tahmini Süre:</strong> 1-2 iş günü</p>
<p><strong>Toplam Fiyat:</strong> {price} TL (KDV Hariç)</p>'),

('Deniz Yolu', 'denizyolu', 'fas fa-ship', 150.00, 1.80, 0.50, 200.00,
'<h4>Deniz Yolu Taşımacılık Teklifi</h4>
<p><strong>Müşteri:</strong> {customer_name}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Yük Detayları:</strong></p>
<ul>
<li>Ağırlık: {weight} kg</li>
<li>Hacim: {volume} m³</li>
<li>Parça Sayısı: {pieces}</li>
</ul>
<p><strong>Tahmini Süre:</strong> 7-15 iş günü</p>
<p><strong>Toplam Fiyat:</strong> {price} TL (KDV Hariç)</p>'),

('Konteyner', 'konteyner', 'fas fa-box', 800.00, 0.00, 2.00, 800.00,
'<h4>Konteyner Taşımacılık Teklifi</h4>
<p><strong>Müşteri:</strong> {customer_name}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Yük Detayları:</strong></p>
<ul>
<li>Ağırlık: {weight} kg</li>
<li>Hacim: {volume} m³</li>
<li>Parça Sayısı: {pieces}</li>
</ul>
<p><strong>Konteyner Tipi:</strong> 20ft / 40ft</p>
<p><strong>Tahmini Süre:</strong> 10-20 iş günü</p>
<p><strong>Toplam Fiyat:</strong> {price} TL (KDV Hariç)</p>');

-- Varsayılan admin kullanıcısı (şifre: admin123)
INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES
('admin', 'admin@nakliye.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sistem Yöneticisi', 'admin');

-- Mevcut transport modları için basit email şablonları ekle
UPDATE transport_modes SET email_template = '
<div style="text-align: center; padding: 20px;">
    <h3 style="color: #2c3e50;">🚛 Nakliye Teklifiniz Hazır!</h3>
    <p style="font-size: 16px; color: #555;">Sayın {customer_name},</p>
    <p style="color: #666;">
        <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için nakliye teklifiniz hazırlanmıştır.
    </p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0; color: #666;">
            <strong>Teklif Numarası:</strong> {quote_number}<br>
            <strong>Geçerlilik:</strong> {valid_until} tarihine kadar
        </p>
    </div>
    <p style="color: #666;">
        Detaylı teklifi görüntülemek ve fiyat bilgilerini öğrenmek için aşağıdaki butona tıklayın.
    </p>
</div>
' WHERE slug = 'karayolu';

UPDATE transport_modes SET email_template = '
<div style="text-align: center; padding: 20px;">
    <h3 style="color: #2c3e50;">✈️ Hava Kargo Teklifiniz Hazır!</h3>
    <p style="font-size: 16px; color: #555;">Sayın {customer_name},</p>
    <p style="color: #666;">
        <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için hava kargo teklifiniz hazırlanmıştır.
    </p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0; color: #666;">
            <strong>Teklif Numarası:</strong> {quote_number}<br>
            <strong>Geçerlilik:</strong> {valid_until} tarihine kadar
        </p>
    </div>
    <p style="color: #666;">
        Detaylı teklifi görüntülemek ve fiyat bilgilerini öğrenmek için aşağıdaki butona tıklayın.
    </p>
</div>
' WHERE slug = 'havayolu';

UPDATE transport_modes SET email_template = '
<div style="text-align: center; padding: 20px;">
    <h3 style="color: #2c3e50;">🚢 Deniz Kargo Teklifiniz Hazır!</h3>
    <p style="font-size: 16px; color: #555;">Sayın {customer_name},</p>
    <p style="color: #666;">
        <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için deniz kargo teklifiniz hazırlanmıştır.
    </p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0; color: #666;">
            <strong>Teklif Numarası:</strong> {quote_number}<br>
            <strong>Geçerlilik:</strong> {valid_until} tarihine kadar
        </p>
    </div>
    <p style="color: #666;">
        Detaylı teklifi görüntülemek ve fiyat bilgilerini öğrenmek için aşağıdaki butona tıklayın.
    </p>
</div>
' WHERE slug = 'denizyolu';

UPDATE transport_modes SET email_template = '
<div style="text-align: center; padding: 20px;">
    <h3 style="color: #2c3e50;">📦 Konteyner Teklifiniz Hazır!</h3>
    <p style="font-size: 16px; color: #555;">Sayın {customer_name},</p>
    <p style="color: #666;">
        <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için konteyner teklifiniz hazırlanmıştır.
    </p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0; color: #666;">
            <strong>Teklif Numarası:</strong> {quote_number}<br>
            <strong>Geçerlilik:</strong> {valid_until} tarihine kadar
        </p>
    </div>
    <p style="color: #666;">
        Detaylı teklifi görüntülemek ve fiyat bilgilerini öğrenmek için aşağıdaki butona tıklayın.
    </p>
</div>
' WHERE slug = 'konteyner';