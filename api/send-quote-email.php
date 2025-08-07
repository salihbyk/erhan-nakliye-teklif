<?php
// Error reporting'i kapat - production için
error_reporting(0);
ini_set('display_errors', 0);

// Output buffer'ı temizle ve başlat
ob_start();

session_start();

// JSON header'ı en son ayarla
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Önceki çıktıları temizle
ob_clean();

require_once '../config/database.php';
require_once '../includes/functions.php';

// Oturum kontrolü
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkilendirme hatası']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // POST verisini al
    $input = json_decode(file_get_contents('php://input'), true);
    $quote_id = $input['quote_id'] ?? 0;

    if (!$quote_id) {
        echo json_encode(['success' => false, 'message' => 'Teklif ID gerekli']);
        exit;
    }

    // Teklif bilgilerini getir
    $stmt = $db->prepare("
        SELECT q.*, c.first_name, c.last_name, c.email, c.phone, c.company,
               tm.name as transport_mode_name
        FROM quotes q
        LEFT JOIN customers c ON q.customer_id = c.id
        LEFT JOIN transport_modes tm ON q.transport_mode_id = tm.id
        WHERE q.id = ? AND q.is_active = 1
    ");
    $stmt->execute([$quote_id]);
    $quote = $stmt->fetch();

    if (!$quote) {
        echo json_encode(['success' => false, 'message' => 'Teklif bulunamadı']);
        exit;
    }

    // Fiyat kontrolü
    if (empty($quote['final_price']) || $quote['final_price'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Önce teklif fiyatı belirlenmeli']);
        exit;
    }

    // Email template bilgilerini getir
    $stmt = $db->prepare("
        SELECT * FROM email_templates
        WHERE transport_mode_id = ? AND language = ? AND currency = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([
        $quote['transport_mode_id'],
        $quote['language'] ?? 'tr',
        $quote['currency'] ?? 'EUR'
    ]);
    $email_template = $stmt->fetch();

    if (!$email_template) {
        // Default email template oluştur
        $email_template = [
            'subject' => 'Nakliye Teklifi - {quote_number}',
            'email_content' => '<p>Sayın {customer_name},</p>
                <p>Talep etmiş olduğunuz nakliye hizmeti için teklif hazırlanmıştır.</p>
                <p>Teklif detaylarını aşağıda bulabilirsiniz:</p>',
            'quote_content' => '<h3>Teklif Detayları</h3>
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
                <p><em>Bu fiyat KDV dahildir.</em></p>'
        ];
    }

    // Teklif URL'i oluştur (revision varsa rev ekle) - dinamik domain
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    $base_path = dirname(dirname($_SERVER['SCRIPT_NAME'])); // api klasöründen çık
    $quote_url = $protocol . $domain . $base_path . '/view-quote.php?id=' . urlencode($quote['quote_number']);
    if ($quote['revision_number'] > 0) {
        $quote_url .= '&rev=' . $quote['revision_number'];
    }

    // Email content değişkenlerini değiştir
    $email_content = replaceEmailVariables($email_template['email_content'], $quote, $quote_url);
    $quote_content = replaceEmailVariables($email_template['quote_content'], $quote, $quote_url);
    $subject = replaceEmailVariables($email_template['subject'], $quote, $quote_url);

    // Gerçek email gönderimi - simple mailer kullan
    $full_email_body = generateEmailBodyForAPI($email_content, $quote_content, $quote, $quote_url);

    // Müşteriye email gönder
    $customer_email_sent = sendEmailSMTP($quote['email'], $subject, $full_email_body);

    // Firmaya da email gönder (kopya) - erhan@europatrans.com.tr'ye
    $company_email = 'erhan@europatrans.com.tr';
    $company_subject = '[KOPYA] ' . $subject;
    $company_email_sent = sendEmailSMTP($company_email, $company_subject, $full_email_body);

    if ($customer_email_sent) {
        // Email gönderim bilgilerini güncelle
        $stmt = $db->prepare("
            UPDATE quotes
            SET email_sent_at = NOW(),
                email_sent_count = email_sent_count + 1,
                status = 'sent'
            WHERE id = ?
        ");
        $stmt->execute([$quote_id]);

        $message = 'Email başarıyla gönderildi';
        if (!$company_email_sent) {
            $message .= ' (Firma kopyası gönderilemedi)';
        } else {
            $message .= ' (Müşteri ve firma kopyası gönderildi)';
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'quote_url' => $quote_url,
            'debug' => [
                'customer_email' => $quote['email'],
                'company_email' => $company_email,
                'customer_sent' => $customer_email_sent,
                'company_sent' => $company_email_sent,
                'subject' => $subject,
                'quote_number' => $quote['quote_number']
            ]
        ]);
        ob_end_flush();
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Müşteri emaili gönderilirken hata oluştu']);
        ob_end_flush();
        exit;
    }

} catch (Exception $e) {
    // Buffer'ı temizle
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    ob_end_flush();
    exit;
}

function generateEmailBodyForAPI($email_content, $quote_content, $quote, $quote_url) {
    // Dinamik domain belirleme
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $base_url = $protocol . $domain;

    // Quote URL zaten dinamik olarak oluşturuldu, ek işlem gerekmiyor

    $current_date = date('d.m.Y');
    $customer_name = $quote['first_name'] . ' ' . $quote['last_name'];

    // Para birimi belirleme
    $currency = $quote['currency'] ?? 'EUR';
    $currency_symbol = $currency === 'TL' ? '₺' : ($currency === 'USD' ? '$' : '€');
    $formatted_price = number_format($quote['final_price'], 2, ',', '.') . ' ' . $currency_symbol;

        return '
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nakliye Teklifi - Europatrans</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background-color: #f8f9fa;
                color: #333;
                line-height: 1.6;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 20px auto;
                background: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }

            /* Header */
            .email-header {
                background: #ffffff;
                padding: 30px 40px;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .logo-section {
                display: flex;
                align-items: center;
            }
            .logo {
                height: 50px;
                width: auto;
            }
            .social-section {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            .social-link {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 50%;
                color: #495057;
                text-decoration: none;
                font-size: 18px;
                transition: all 0.3s ease;
            }
            .social-link:hover {
                background: #e9ecef;
                transform: translateY(-2px);
            }
            .social-link.facebook:hover {
                background: #1877f2;
                color: white;
                border-color: #1877f2;
            }
            .social-link.instagram:hover {
                background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%);
                color: white;
                border-color: #bc1888;
            }
            .social-link.youtube:hover {
                background: #ff0000;
                color: white;
                border-color: #ff0000;
            }

            /* Body */
            .email-body {
                padding: 40px;
            }
            .greeting {
                font-size: 24px;
                font-weight: 600;
                color: #212529;
                margin-bottom: 30px;
            }
            .message {
                font-size: 16px;
                color: #495057;
                margin-bottom: 40px;
                line-height: 1.7;
            }

            /* CTA Section */
            .cta-section {
                text-align: center;
                margin: 40px 0;
                padding: 40px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            .cta-button {
                display: inline-block;
                background: #007bff;
                color: #ffffff !important;
                text-decoration: none;
                padding: 15px 40px;
                border-radius: 6px;
                font-weight: 600;
                font-size: 16px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 10px rgba(0,123,255,0.3);
            }
            .cta-button:hover {
                background: #0056b3;
                transform: translateY(-1px);
                box-shadow: 0 4px 15px rgba(0,123,255,0.4);
            }
            .cta-description {
                margin-top: 20px;
                color: #6c757d;
                font-size: 14px;
                line-height: 1.6;
            }

            /* Footer */
            .email-footer {
                background: #f8f9fa;
                padding: 30px 40px;
                border-top: 1px solid #e9ecef;
                text-align: center;
            }
            .footer-content {
                color: #6c757d;
                font-size: 14px;
                line-height: 1.6;
            }
            .company-name {
                font-weight: 600;
                color: #495057;
                margin-bottom: 10px;
            }
            .contact-info {
                margin: 15px 0;
            }
            .contact-info div {
                margin: 5px 0;
            }
            .copyright {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e9ecef;
                color: #868e96;
                font-size: 12px;
            }

            /* Responsive */
            @media only screen and (max-width: 600px) {
                .email-wrapper {
                    margin: 10px;
                }
                .email-header {
                    flex-direction: column;
                    gap: 20px;
                    text-align: center;
                }
                .social-section {
                    justify-content: center;
                }
                .email-body, .email-footer {
                    padding: 30px 20px;
                }
                .greeting {
                    font-size: 20px;
                }
                .cta-section {
                    padding: 30px 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <!-- Header -->
            <div class="email-header">
                <div class="logo-section">
                    <img src="https://www.europatrans.com.tr/themes/europatrans/img/europatrans-logo.png" alt="Europatrans Logo" class="logo">
                </div>
            </div>

            <!-- Body -->
            <div class="email-body">
                <div class="greeting">
                    Sayın ' . htmlspecialchars($customer_name) . ',
                </div>

                <div class="message">
                    Nakliye hizmetiniz için hazırladığımız teklif hazır! Detayları incelemek için aşağıdaki butona tıklayabilirsiniz.
                </div>

                <!-- CTA Section -->
                <div class="cta-section">
                    <a href="' . $quote_url . '" class="cta-button">
                        Teklifi İncele
                    </a>
                    <div class="cta-description">
                        Detaylı teklif sayfasında tüm bilgileri inceleyebilirsiniz.
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-content">
                    <div class="company-name">EUROPATRANS</div>
                    <div>Kurumsal Taşımacılık ve Depolama Çözümleri</div>

                    <div class="contact-info">
                        <div style="margin-bottom: 15px; text-align: center;">
                            <a href="https://www.facebook.com/europatransturkey/" style="text-decoration: none; margin-right: 15px; display: inline-block;">
                                <img src="https://cdn-icons-png.flaticon.com/512/124/124010.png" alt="Facebook" width="24" height="24" style="display: block;">
                            </a>
                            <a href="https://www.instagram.com/europatransnakliyat/" style="text-decoration: none; margin-right: 15px; display: inline-block;">
                                <img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png" alt="Instagram" width="24" height="24" style="display: block;">
                            </a>
                            <a href="https://www.youtube.com/@europatrans" style="text-decoration: none; display: inline-block;">
                                <img src="https://cdn-icons-png.flaticon.com/512/1384/1384028.png" alt="YouTube" width="24" height="24" style="display: block;">
                            </a>
                        </div>
                        <div><strong>Müşteri Hizmetleri:</strong> 444 6 995</div>
                        <div><strong>E-posta:</strong> info@europatrans.com.tr</div>
                        <div><strong>Web:</strong> www.europatrans.com.tr</div>
                    </div>

                    <div class="copyright">
                        Bu e-posta otomatik olarak gönderilmiştir.<br>
                        © 2024 Europatrans Kurumsal Taşımacılık ve Depolama Çözümleri. Tüm hakları saklıdır.
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
}

function replaceEmailVariables($content, $quote, $quote_url) {
    $variables = [
        '{customer_name}' => $quote['first_name'] . ' ' . $quote['last_name'],
        '{quote_number}' => $quote['quote_number'],
        '{origin}' => $quote['origin'],
        '{destination}' => $quote['destination'],
        '{weight}' => $quote['weight'] ? $quote['weight'] . ' kg' : '-',
        '{volume}' => $quote['volume'] ? $quote['volume'] . ' m³' : '-',
        '{pieces}' => $quote['pieces'] ?: '-',
        '{cargo_type}' => $quote['cargo_type'] ?: '-',
        '{trade_type}' => $quote['trade_type'],
        '{price}' => formatPrice($quote['final_price'], $quote['currency'] ?? 'EUR'),
        '{start_date}' => $quote['start_date'] ? formatDate($quote['start_date']) : '-',
        '{delivery_date}' => $quote['delivery_date'] ? formatDate($quote['delivery_date']) : '-',
        '{valid_until}' => formatDate($quote['valid_until']),
        '{quote_url}' => $quote_url,
        '{quote_content}' => '' // Bu email template'de kullanılacak
    ];

    return str_replace(array_keys($variables), array_values($variables), $content);
}

// PHPMailer fonksiyonu kaldırıldı - simple mailer kullanıyoruz
?>