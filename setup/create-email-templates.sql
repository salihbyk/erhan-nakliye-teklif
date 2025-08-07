-- Email şablonları tablosu
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transport_mode_id INT NOT NULL,
    language VARCHAR(2) DEFAULT 'tr',
    currency VARCHAR(3) DEFAULT 'EUR',
    subject VARCHAR(255) NOT NULL,
    email_content TEXT NOT NULL,
    quote_content TEXT NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transport_mode_id) REFERENCES transport_modes(id)
);

-- Karayolu için varsayılan şablon (Türkçe)
INSERT INTO email_templates (transport_mode_id, language, currency, subject, email_content, quote_content) VALUES
(1, 'tr', 'EUR', 'Nakliye Teklifi - {quote_number}',
'<p>Sayın {customer_name},</p>
<p>Talep etmiş olduğunuz nakliye hizmeti için teklif hazırlanmıştır.</p>
<p>Teklif detaylarını aşağıda bulabilirsiniz:</p>',
'<h3>Teklif Detayları</h3>
<p><strong>Teklif No:</strong> {quote_number}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Kargo Türü:</strong> {cargo_type}</p>
<p><strong>İşlem Türü:</strong> {trade_type}</p>
<p><strong>Hacim:</strong> {volume}</p>
<p><strong>Başlangıç Tarihi:</strong> {start_date}</p>
<p><strong>Teslim Tarihi:</strong> {delivery_date}</p>
<p><strong>Geçerlilik Tarihi:</strong> {valid_until}</p>
<hr>
<h3 style="color: #2c5aa0;">Toplam Fiyat: {price}</h3>
<p><em>Bu fiyat KDV dahildir.</em></p>');

-- Havayolu için varsayılan şablon (Türkçe)
INSERT INTO email_templates (transport_mode_id, language, currency, subject, email_content, quote_content) VALUES
(2, 'tr', 'EUR', 'Havayolu Nakliye Teklifi - {quote_number}',
'<p>Sayın {customer_name},</p>
<p>Havayolu nakliye hizmeti için teklif hazırlanmıştır.</p>
<p>Teklif detaylarını aşağıda bulabilirsiniz:</p>',
'<h3>Havayolu Teklif Detayları</h3>
<p><strong>Teklif No:</strong> {quote_number}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Ağırlık:</strong> {weight}</p>
<p><strong>Parça Sayısı:</strong> {pieces}</p>
<p><strong>Hacim:</strong> {volume}</p>
<p><strong>Kargo Türü:</strong> {cargo_type}</p>
<p><strong>İşlem Türü:</strong> {trade_type}</p>
<p><strong>Başlangıç Tarihi:</strong> {start_date}</p>
<p><strong>Teslim Tarihi:</strong> {delivery_date}</p>
<p><strong>Geçerlilik Tarihi:</strong> {valid_until}</p>
<hr>
<h3 style="color: #2c5aa0;">Toplam Fiyat: {price}</h3>
<p><em>Bu fiyat KDV dahildir.</em></p>');

-- Deniz yolu için varsayılan şablon (Türkçe)
INSERT INTO email_templates (transport_mode_id, language, currency, subject, email_content, quote_content) VALUES
(3, 'tr', 'EUR', 'Deniz Yolu Nakliye Teklifi - {quote_number}',
'<p>Sayın {customer_name},</p>
<p>Deniz yolu nakliye hizmeti için teklif hazırlanmıştır.</p>
<p>Teklif detaylarını aşağıda bulabilirsiniz:</p>',
'<h3>Deniz Yolu Teklif Detayları</h3>
<p><strong>Teklif No:</strong> {quote_number}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Hacim:</strong> {volume}</p>
<p><strong>Kargo Türü:</strong> {cargo_type}</p>
<p><strong>İşlem Türü:</strong> {trade_type}</p>
<p><strong>Başlangıç Tarihi:</strong> {start_date}</p>
<p><strong>Teslim Tarihi:</strong> {delivery_date}</p>
<p><strong>Geçerlilik Tarihi:</strong> {valid_until}</p>
<hr>
<h3 style="color: #2c5aa0;">Toplam Fiyat: {price}</h3>
<p><em>Bu fiyat KDV dahildir.</em></p>');

-- Konteyner için varsayılan şablon (Türkçe)
INSERT INTO email_templates (transport_mode_id, language, currency, subject, email_content, quote_content) VALUES
(4, 'tr', 'EUR', 'Konteyner Nakliye Teklifi - {quote_number}',
'<p>Sayın {customer_name},</p>
<p>Konteyner nakliye hizmeti için teklif hazırlanmıştır.</p>
<p>Teklif detaylarını aşağıda bulabilirsiniz:</p>',
'<h3>Konteyner Teklif Detayları</h3>
<p><strong>Teklif No:</strong> {quote_number}</p>
<p><strong>Güzergah:</strong> {origin} → {destination}</p>
<p><strong>Hacim:</strong> {volume}</p>
<p><strong>Kargo Türü:</strong> {cargo_type}</p>
<p><strong>İşlem Türü:</strong> {trade_type}</p>
<p><strong>Başlangıç Tarihi:</strong> {start_date}</p>
<p><strong>Teslim Tarihi:</strong> {delivery_date}</p>
<p><strong>Geçerlilik Tarihi:</strong> {valid_until}</p>
<hr>
<h3 style="color: #2c5aa0;">Toplam Fiyat: {price}</h3>
<p><em>Bu fiyat KDV dahildir.</em></p>');