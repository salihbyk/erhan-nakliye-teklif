<?php
// Basit SMTP mailer'ı dahil et
require_once __DIR__ . '/simple-mailer.php';

// E-posta gönderme fonksiyonu
function sendQuoteEmail($db, $quote_id, $customer, $transport_mode, $cargo, $price) {
    try {
        // Teklif bilgilerini al
        $stmt = $db->prepare("SELECT * FROM quotes WHERE id = ? LIMIT 1");
        $stmt->execute([$quote_id]);
        $quote = $stmt->fetch();

        if (!$quote) {
            throw new Exception('Teklif bulunamadı');
        }

        // Transport mode bilgilerini al (email_template dahil)
        $stmt = $db->prepare("SELECT * FROM transport_modes WHERE id = ? LIMIT 1");
        $stmt->execute([$quote['transport_mode_id']]);
        $transport_data = $stmt->fetch();

        if (!$transport_data) {
            throw new Exception('Taşıma modu bulunamadı');
        }

        // E-posta şablonunu hazırla (email_template kullan, yoksa template kullan)
        $email_template = $transport_data['email_template'] ?: $transport_data['template'];
        $customer_name = $customer['firstName'] . ' ' . $customer['lastName'];

        // Şablon değişkenlerini değiştir (email için basit değişkenler)
        $replacements = [
            '{customer_name}' => $customer_name,
            '{origin}' => $cargo['origin'],
            '{destination}' => $cargo['destination'],
            '{quote_number}' => $quote['quote_number'],
            '{quote_date}' => date('d/m/Y'),
            '{valid_until}' => date('d.m.Y', strtotime($quote['valid_until']))
        ];

        $email_body = str_replace(array_keys($replacements), array_values($replacements), $email_template);

        // E-posta içeriğini tamamla
        $email_subject = 'Nakliye Teklifi - ' . $quote['quote_number'];
        $full_email_body = generateEmailTemplate($email_body, $quote['quote_number'], $customer_name);

        // E-posta gönder
        $email_sent = sendEmail($customer['email'], $email_subject, $full_email_body);

        // E-posta logunu kaydet
        logEmail($db, $quote_id, $customer['email'], $email_subject, $full_email_body, $email_sent);

        // CC olarak erhan@europatrans.com.tr'ye de gönder
        if ($email_sent) {
            $cc_email = 'erhan@europatrans.com.tr';
            $cc_subject = '[KOPYA] ' . $email_subject;
            $cc_sent = sendEmail($cc_email, $cc_subject, $full_email_body);

            // CC email logunu da kaydet
            logEmail($db, $quote_id, $cc_email, $cc_subject, $full_email_body, $cc_sent);
        }

        return $email_sent;

    } catch (Exception $e) {
        error_log('E-posta gönderme hatası: ' . $e->getMessage());
        return false;
    }
}

// E-posta şablonu oluştur
function generateEmailTemplate($content, $quote_number, $customer_name) {
    // Dinamik base URL oluştur
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $domain = $_SERVER['HTTP_HOST'];
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $base_url = $protocol . $domain . $script_path;
    $quote_url = $base_url . '/view-quote.php?id=' . $quote_number;

    return '
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nakliye Teklifi - Europatrans</title>
        <style>
            body {
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f8f9fa;
                line-height: 1.6;
            }
            .container {
                max-width: 650px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                border: 1px solid #e9ecef;
            }
            .header {
                background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                color: white;
                padding: 40px 30px;
                text-align: center;
                position: relative;
            }
            .logo {
                max-width: 200px;
                height: auto;
                margin-bottom: 20px;
                filter: brightness(0) invert(1);
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 300;
                letter-spacing: 1px;
            }
            .header p {
                margin: 10px 0 0 0;
                font-size: 18px;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
                background: #ffffff;
            }
            .quote-info {
                background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
                border-left: 4px solid #2196f3;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                text-align: center;
            }
            .quote-number {
                font-size: 24px;
                font-weight: bold;
                color: #1565c0;
                margin-bottom: 5px;
            }
            .quote-label {
                color: #666;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .btn {
                display: inline-block;
                background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
                color: white;
                padding: 15px 35px;
                text-decoration: none;
                border-radius: 25px;
                margin: 30px 0;
                font-weight: 600;
                font-size: 16px;
                box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
            }
            .steps {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .steps h4 {
                color: #2c3e50;
                margin-top: 0;
                font-size: 18px;
            }
            .steps ol {
                margin: 15px 0;
                padding-left: 20px;
            }
            .steps li {
                margin: 8px 0;
                color: #555;
            }
            .contact-info {
                background: #fff;
                border: 1px solid #e9ecef;
                padding: 25px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .contact-info h4 {
                color: #2c3e50;
                margin-top: 0;
                font-size: 18px;
                border-bottom: 2px solid #2196f3;
                padding-bottom: 10px;
            }
            .contact-item {
                margin: 12px 0;
                color: #555;
            }
            .contact-item strong {
                color: #2c3e50;
                display: inline-block;
                width: 80px;
            }
            .footer {
                background: #2c3e50;
                color: #bdc3c7;
                padding: 25px 30px;
                text-align: center;
                font-size: 13px;
            }
            .footer p {
                margin: 8px 0;
            }
            .divider {
                height: 1px;
                background: linear-gradient(to right, transparent, #ddd, transparent);
                margin: 30px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <img src="https://www.europatrans.com.tr/themes/europatrans/img/europatrans-logo.png" alt="Europatrans Logo" class="logo">
                <h1>Nakliye Teklifi</h1>
                <p>Sayın ' . htmlspecialchars($customer_name) . '</p>
            </div>
            <div class="content">
                <div class="quote-info">
                    <div class="quote-label">Teklif Numaranız</div>
                    <div class="quote-number">' . htmlspecialchars($quote_number) . '</div>
                </div>

                ' . $content . '

                <div style="text-align: center; margin: 40px 0;">
                    <a href="' . $quote_url . '" class="btn">📋 Detaylı Teklifi Görüntüle</a>
                </div>

                <div class="divider"></div>

                <div class="steps">
                    <h4>📋 Sonraki Adımlar</h4>
                    <ol>
                        <li>Yukarıdaki butona tıklayarak detaylı teklifi inceleyin</li>
                        <li>Sorularınız için bizimle iletişime geçin</li>
                        <li>Teklifi onayladığınızda taşıma sürecini başlatırız</li>
                        <li>Kargo takip bilgilerinizi e-posta ile alacaksınız</li>
                    </ol>
                </div>

                <div class="contact-info">
                    <h4>📞 İletişim Bilgileri</h4>
                    <div class="contact-item">
                        <strong>Telefon:</strong> +90 (212) 555-0123
                    </div>
                    <div class="contact-item">
                        <strong>E-posta:</strong> info@europatrans.com.tr
                    </div>
                    <div class="contact-item">
                        <strong>Web:</strong> www.europatrans.com.tr
                    </div>
                    <div class="contact-item">
                        <strong>Adres:</strong> İstanbul, Türkiye
                    </div>
                </div>
            </div>
            <div class="footer">
                <p>Bu e-posta otomatik olarak gönderilmiştir. Lütfen bu e-postaya yanıt vermeyin.</p>
                <p>© 2024 Europatrans Global Lojistik. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </body>
    </html>';
}

// E-posta gönderme (PHPMailer SMTP kullanarak)
function sendEmail($to, $subject, $body) {
    try {
        // PHPMailer SMTP ile gönder
        return sendEmailSMTP($to, $subject, $body);
    } catch (Exception $e) {
        error_log('E-posta gönderme hatası: ' . $e->getMessage());
        return false;
    }
}

// E-posta log kaydet
function logEmail($db, $quote_id, $recipient, $subject, $body, $success) {
    try {
        $stmt = $db->prepare("
            INSERT INTO email_logs (quote_id, recipient_email, subject, body, status, sent_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $status = $success ? 'sent' : 'failed';
        $sent_at = $success ? date('Y-m-d H:i:s') : null;

        $stmt->execute([$quote_id, $recipient, $subject, $body, $status, $sent_at]);

    } catch (Exception $e) {
        error_log('E-posta log hatası: ' . $e->getMessage());
    }
}

// Mevcut base URL'i al
function getCurrentBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . $host . $script;
}

// Güvenli string temizleme
function sanitizeString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

// Türkçe karakter URL dostu hale getir
function slugify($text) {
    $turkish = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
    $english = ['i', 'g', 'u', 's', 'o', 'c', 'I', 'G', 'U', 'S', 'O', 'C'];

    $text = str_replace($turkish, $english, $text);
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    return strtolower(trim($text, '-'));
}

// Fiyat formatla
function formatPrice($price, $currency = 'TL') {
    $formatted_price = number_format($price, 0, ',', '.');
    switch($currency) {
        case 'USD':
            return '$' . $formatted_price;
        case 'EUR':
            return '€' . $formatted_price;
        case 'TL':
        default:
            return $formatted_price . ' TL';
    }
}

// Tarih formatla
function formatDate($date) {
    if (empty($date) || $date === '0000-00-00' || $date === null) {
        return '';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date; // Eğer tarih parse edilemezse orijinal değeri döndür
    }

    return date('d.m.Y', $timestamp);
}

// Tarih ve saat formatla
function formatDateTime($datetime) {
    return date('d.m.Y H:i', strtotime($datetime));
}

// Admin oturum kontrolü
function checkAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || !isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Session temizle
        session_unset();
        session_destroy();
        session_start();

        // Dinamik yönlendirme - mevcut dizine göre
        $current_dir = dirname($_SERVER['SCRIPT_NAME']);
        if (strpos($current_dir, '/admin') !== false) {
            // Zaten admin dizinindeyiz
            header('Location: login.php');
        } else {
            // Ana dizindeyiz, admin'e git
            header('Location: admin/login.php');
        }
        exit;
    }
}

// Admin rolü kontrolü
function checkAdminRole($required_role = 'operator') {
    if (!isset($_SESSION['admin_role'])) {
        return false;
    }

    $roles = ['operator' => 1, 'manager' => 2, 'admin' => 3];
    $user_level = $roles[$_SESSION['admin_role']] ?? 0;
    $required_level = $roles[$required_role] ?? 0;

    return $user_level >= $required_level;
}

// Başarı mesajı
function setSuccessMessage($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['success_message'] = $message;
}

// Hata mesajı
function setErrorMessage($message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['error_message'] = $message;
}

// Mesajları al ve temizle
function getMessages() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $messages = [];

    if (isset($_SESSION['success_message'])) {
        $messages['success'] = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        $messages['error'] = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }

    return $messages;
}

// Sayfalama hesapla
function calculatePagination($total_records, $records_per_page, $current_page) {
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;

    return [
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'prev_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

// Müşteri ödeme durumunu güncelle
function updateCustomerPaymentStatus($db, $quote_id, $payment_amount, $payment_status = 'partial') {
    try {
        $stmt = $db->prepare("
            UPDATE quotes
            SET payment_status = ?, payment_amount = ?, payment_date = CURDATE()
            WHERE id = ?
        ");

        $result = $stmt->execute([$payment_status, $payment_amount, $quote_id]);

        if ($result) {
            error_log("Ödeme durumu güncellendi - Quote ID: $quote_id, Tutar: $payment_amount, Durum: $payment_status");
        }

        return $result;
    } catch (Exception $e) {
        error_log('Ödeme durumu güncelleme hatası: ' . $e->getMessage());
        return false;
    }
}

// Müşteri ödeme geçmişini kontrol et
function checkCustomerPaymentHistory($db, $customer_id) {
    try {
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_quotes,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_quotes,
                SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_quotes,
                SUM(CASE WHEN payment_status IS NULL OR payment_status = '' THEN 1 ELSE 0 END) as unpaid_quotes
            FROM quotes
            WHERE customer_id = ?
        ");

        $stmt->execute([$customer_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Ödeme geçmişi kontrol hatası: ' . $e->getMessage());
        return [
            'total_quotes' => 0,
            'paid_quotes' => 0,
            'partial_quotes' => 0,
            'unpaid_quotes' => 0
        ];
    }
}

// generateQuoteNumber fonksiyonu api/submit-quote.php dosyasında tanımlanmıştır
?>