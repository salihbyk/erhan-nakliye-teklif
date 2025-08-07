<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    echo "<h2>Örnek E-posta Şablonları Oluşturuluyor...</h2>";

    // Karayolu transport mode ID'sini al
    $stmt = $db->prepare("SELECT id FROM transport_modes WHERE slug = 'karayolu' LIMIT 1");
    $stmt->execute();
    $karayolu = $stmt->fetch();

    if (!$karayolu) {
        echo "<p style='color: red;'>❌ Karayolu taşıma modu bulunamadı!</p>";
        exit;
    }

    $transport_mode_id = $karayolu['id'];

    // Örnek şablonlar
    $templates = [
        [
            'transport_mode_id' => $transport_mode_id,
            'language' => 'tr',
            'currency' => 'TL',
            'template_name' => 'Karayolu Türkçe TL',
            'subject' => 'Nakliye Teklifiniz Hazır - {quote_number}',
            'email_content' => '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; background: #2c5aa0; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">🚛 Nakliye Teklifiniz Hazır!</h2>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px; color: #333;">Sayın <strong>{customer_name}</strong>,</p>

        <p style="color: #666; line-height: 1.6;">
            <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için karayolu nakliye teklifiniz hazırlanmıştır.
        </p>

        <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5aa0;">
            <p style="margin: 0; color: #333;">
                <strong>📋 Teklif Numarası:</strong> {quote_number}<br>
                <strong>📅 Geçerlilik:</strong> {valid_until} tarihine kadar<br>
                <strong>🚛 Taşıma Türü:</strong> {transport_name}
            </p>
        </div>

        <p style="color: #666; line-height: 1.6;">
            Detaylı teklifi görüntülemek ve fiyat bilgilerini öğrenmek için lütfen bizimle iletişime geçin.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <p style="color: #2c5aa0; font-weight: bold; font-size: 18px;">
                📞 444 6 995
            </p>
            <p style="color: #666; font-size: 14px;">
                info@europatrans.com.tr
            </p>
        </div>
    </div>
</div>',
            'quote_content' => '
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="color: #2c5aa0; margin-bottom: 15px;">🚛 Karayolu Nakliye Teklifi</h3>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Müşteri Bilgileri</h4>
        <p><strong>Ad Soyad:</strong> {customer_name}</p>
        <p><strong>Teklif No:</strong> {quote_number}</p>
        <p><strong>Geçerlilik:</strong> {valid_until} tarihine kadar</p>
    </div>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Taşıma Detayları</h4>
        <p><strong>Güzergah:</strong> {origin} → {destination}</p>
        <p><strong>Ağırlık:</strong> {weight} kg</p>
        <p><strong>Hacim:</strong> {volume} m³</p>
        <p><strong>Parça Sayısı:</strong> {pieces}</p>
        <p><strong>Tahmini Süre:</strong> 2-5 iş günü</p>
    </div>

    <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">💰 Toplam Fiyat</h4>
        <p style="font-size: 24px; font-weight: bold; color: #2e7d32; margin: 0;">{price}</p>
        <p style="color: #666; font-size: 12px; margin: 5px 0 0 0;">(KDV Hariç)</p>
    </div>
</div>'
        ],
        [
            'transport_mode_id' => $transport_mode_id,
            'language' => 'en',
            'currency' => 'EUR',
            'template_name' => 'Karayolu English EUR',
            'subject' => 'Your Transport Quote is Ready - {quote_number}',
            'email_content' => '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; background: #2c5aa0; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">🚛 Your Transport Quote is Ready!</h2>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px; color: #333;">Dear <strong>{customer_name}</strong>,</p>

        <p style="color: #666; line-height: 1.6;">
            Your road transport quote for <strong>{origin}</strong> → <strong>{destination}</strong> route has been prepared.
        </p>

        <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5aa0;">
            <p style="margin: 0; color: #333;">
                <strong>📋 Quote Number:</strong> {quote_number}<br>
                <strong>📅 Valid Until:</strong> {valid_until}<br>
                <strong>🚛 Transport Type:</strong> {transport_name}
            </p>
        </div>

        <p style="color: #666; line-height: 1.6;">
            Please contact us to view the detailed quote and pricing information.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <p style="color: #2c5aa0; font-weight: bold; font-size: 18px;">
                📞 +90 444 6 995
            </p>
            <p style="color: #666; font-size: 14px;">
                info@europatrans.com.tr
            </p>
        </div>
    </div>
</div>',
            'quote_content' => '
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="color: #2c5aa0; margin-bottom: 15px;">🚛 Road Transport Quote</h3>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Customer Information</h4>
        <p><strong>Name:</strong> {customer_name}</p>
        <p><strong>Quote No:</strong> {quote_number}</p>
        <p><strong>Valid Until:</strong> {valid_until}</p>
    </div>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Transport Details</h4>
        <p><strong>Route:</strong> {origin} → {destination}</p>
        <p><strong>Weight:</strong> {weight} kg</p>
        <p><strong>Volume:</strong> {volume} m³</p>
        <p><strong>Pieces:</strong> {pieces}</p>
        <p><strong>Estimated Time:</strong> 2-5 business days</p>
    </div>

    <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">💰 Total Price</h4>
        <p style="font-size: 24px; font-weight: bold; color: #2e7d32; margin: 0;">{price}</p>
        <p style="color: #666; font-size: 12px; margin: 5px 0 0 0;">(VAT Excluded)</p>
    </div>
</div>'
        ],
        [
            'transport_mode_id' => $transport_mode_id,
            'language' => 'tr',
            'currency' => 'EUR',
            'template_name' => 'Karayolu Türkçe EUR',
            'subject' => 'Nakliye Teklifiniz Hazır - {quote_number}',
            'email_content' => '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; background: #2c5aa0; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">🚛 Nakliye Teklifiniz Hazır!</h2>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px; color: #333;">Sayın <strong>{customer_name}</strong>,</p>

        <p style="color: #666; line-height: 1.6;">
            <strong>{origin}</strong> → <strong>{destination}</strong> güzergahı için karayolu nakliye teklifiniz hazırlanmıştır.
        </p>

        <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5aa0;">
            <p style="margin: 0; color: #333;">
                <strong>📋 Teklif Numarası:</strong> {quote_number}<br>
                <strong>📅 Geçerlilik:</strong> {valid_until} tarihine kadar<br>
                <strong>🚛 Taşıma Türü:</strong> {transport_name}<br>
                <strong>💶 Para Birimi:</strong> Euro (EUR)
            </p>
        </div>

        <p style="color: #666; line-height: 1.6;">
            Detaylı teklifi görüntülemek ve Euro cinsinden fiyat bilgilerini öğrenmek için lütfen bizimle iletişime geçin.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <p style="color: #2c5aa0; font-weight: bold; font-size: 18px;">
                📞 444 6 995
            </p>
            <p style="color: #666; font-size: 14px;">
                info@europatrans.com.tr
            </p>
        </div>
    </div>
</div>',
            'quote_content' => '
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="color: #2c5aa0; margin-bottom: 15px;">🚛 Karayolu Nakliye Teklifi (EUR)</h3>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Müşteri Bilgileri</h4>
        <p><strong>Ad Soyad:</strong> {customer_name}</p>
        <p><strong>Teklif No:</strong> {quote_number}</p>
        <p><strong>Geçerlilik:</strong> {valid_until} tarihine kadar</p>
        <p><strong>Para Birimi:</strong> Euro (EUR)</p>
    </div>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Taşıma Detayları</h4>
        <p><strong>Güzergah:</strong> {origin} → {destination}</p>
        <p><strong>Ağırlık:</strong> {weight} kg</p>
        <p><strong>Hacim:</strong> {volume} m³</p>
        <p><strong>Parça Sayısı:</strong> {pieces}</p>
        <p><strong>Tahmini Süre:</strong> 2-5 iş günü</p>
    </div>

    <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">💰 Toplam Fiyat</h4>
        <p style="font-size: 24px; font-weight: bold; color: #2e7d32; margin: 0;">€{price}</p>
        <p style="color: #666; font-size: 12px; margin: 5px 0 0 0;">(KDV Hariç)</p>
    </div>
</div>'
        ],
        [
            'transport_mode_id' => $transport_mode_id,
            'language' => 'en',
            'currency' => 'USD',
            'template_name' => 'Karayolu English USD',
            'subject' => 'Your Transport Quote is Ready - {quote_number}',
            'email_content' => '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="text-align: center; background: #2c5aa0; color: white; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0;">🚛 Your Transport Quote is Ready!</h2>
    </div>

    <div style="background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px;">
        <p style="font-size: 16px; color: #333;">Dear <strong>{customer_name}</strong>,</p>

        <p style="color: #666; line-height: 1.6;">
            Your road transport quote for <strong>{origin}</strong> → <strong>{destination}</strong> route has been prepared.
        </p>

        <div style="background: white; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5aa0;">
            <p style="margin: 0; color: #333;">
                <strong>📋 Quote Number:</strong> {quote_number}<br>
                <strong>📅 Valid Until:</strong> {valid_until}<br>
                <strong>🚛 Transport Type:</strong> {transport_name}<br>
                <strong>💵 Currency:</strong> US Dollar (USD)
            </p>
        </div>

        <p style="color: #666; line-height: 1.6;">
            Please contact us to view the detailed quote and USD pricing information.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <p style="color: #2c5aa0; font-weight: bold; font-size: 18px;">
                📞 +90 444 6 995
            </p>
            <p style="color: #666; font-size: 14px;">
                info@europatrans.com.tr
            </p>
        </div>
    </div>
</div>',
            'quote_content' => '
<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
    <h3 style="color: #2c5aa0; margin-bottom: 15px;">🚛 Road Transport Quote (USD)</h3>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Customer Information</h4>
        <p><strong>Name:</strong> {customer_name}</p>
        <p><strong>Quote No:</strong> {quote_number}</p>
        <p><strong>Valid Until:</strong> {valid_until}</p>
        <p><strong>Currency:</strong> US Dollar (USD)</p>
    </div>

    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
        <h4 style="color: #333; margin-bottom: 10px;">Transport Details</h4>
        <p><strong>Route:</strong> {origin} → {destination}</p>
        <p><strong>Weight:</strong> {weight} kg</p>
        <p><strong>Volume:</strong> {volume} m³</p>
        <p><strong>Pieces:</strong> {pieces}</p>
        <p><strong>Estimated Time:</strong> 2-5 business days</p>
    </div>

    <div style="background: #e8f5e8; padding: 15px; border-radius: 6px; text-align: center;">
        <h4 style="color: #2e7d32; margin-bottom: 10px;">💰 Total Price</h4>
        <p style="font-size: 24px; font-weight: bold; color: #2e7d32; margin: 0;">$${price}</p>
        <p style="color: #666; font-size: 12px; margin: 5px 0 0 0;">(VAT Excluded)</p>
    </div>
</div>'
        ]
    ];

    // Şablonları ekle
    foreach ($templates as $template) {
        try {
            $stmt = $db->prepare("
                INSERT INTO email_templates (transport_mode_id, language, currency, template_name, subject, email_content, quote_content, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                template_name = VALUES(template_name),
                subject = VALUES(subject),
                email_content = VALUES(email_content),
                quote_content = VALUES(quote_content)
            ");

            $stmt->execute([
                $template['transport_mode_id'],
                $template['language'],
                $template['currency'],
                $template['template_name'],
                $template['subject'],
                $template['email_content'],
                $template['quote_content']
            ]);

            echo "<p>✅ " . htmlspecialchars($template['template_name']) . " şablonu eklendi/güncellendi</p>";

        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ " . htmlspecialchars($template['template_name']) . " şablonu eklenirken hata: " . $e->getMessage() . "</p>";
        }
    }

    echo "<hr><p><strong>✅ Örnek şablonlar başarıyla oluşturuldu!</strong></p>";
    echo "<p><a href='../admin/email-templates.php'>E-posta Şablonları Sayfasına Git</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Hata: " . $e->getMessage() . "</p>";
}
?>