<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../includes/simple-mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Geçersiz JSON verisi');
    }

    $quote_number = $input['quote_number'] ?? '';
    $customer_name = $input['customer_name'] ?? '';

    if (!$quote_number || !$customer_name) {
        throw new Exception('Teklif numarası ve müşteri adı gereklidir');
    }

    // Email settings
    $to = "info@europatrans.com.tr";
    $cc = "erhan@europatrans.com.tr"; // CC ekle
    $subject = "Müşteri Onayı - Teklif No: " . $quote_number;

    $message = "
    <html>
    <head>
        <title>Müşteri Onayı</title>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; border: 1px solid #ddd; }
            .highlight { background: #e8f5e8; padding: 15px; border-left: 4px solid #28a745; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🎉 Müşteri Onayı Alındı</h2>
            </div>
            <div class='content'>
                <h3>Teklif Onay Bildirimi</h3>

                <div class='highlight'>
                    <p><strong>Müşteri:</strong> " . htmlspecialchars($customer_name) . "</p>
                    <p><strong>Teklif No:</strong> " . htmlspecialchars($quote_number) . "</p>
                    <p><strong>Onay Tarihi:</strong> " . date('d.m.Y H:i') . "</p>
                </div>

                <p>Sayın Yetkililer,</p>
                <p>Yukarıda belirtilen teklif için müşteri onayı alınmıştır. İlgili işlemlere başlayabilirsiniz.</p>

                <p><strong>Müşteri Bilgileri:</strong></p>
                <ul>
                    <li>Ad Soyad: " . htmlspecialchars($customer_name) . "</li>
                    <li>Teklif Numarası: " . htmlspecialchars($quote_number) . "</li>
                    <li>Onay Zamanı: " . date('d.m.Y H:i:s') . "</li>
                </ul>

                <p>Bu onay müşteri tarafından web sitesi üzerinden verilmiştir.</p>

                <hr>
                <p><small>Bu e-posta otomatik olarak gönderilmiştir. Lütfen yanıtlamayınız.</small></p>
            </div>
        </div>
    </body>
    </html>
    ";



    // Log the approval in database first
    $database = new Database();
    $db = $database->getConnection();

    // Update quote status or add approval log
    $stmt = $db->prepare("
        UPDATE quotes
        SET status = 'accepted', updated_at = NOW()
        WHERE quote_number = ?
    ");
    $success = $stmt->execute([$quote_number]);

        if ($success) {
        // Email gönderimini dene
        $email_sent = false;
        $email_error = '';

                        try {
            // Ana adrese gönder
            $email_sent = sendEmailSMTP($to, $subject, $message, 'Europetrans');

            // CC adresine de gönder
            $cc_sent = sendEmailSMTP($cc, $subject, $message, 'Europetrans');

            if ($email_sent) {
                error_log("Onay maili başarıyla gönderildi: " . $to . " - Teklif: " . $quote_number);
                if ($cc_sent) {
                    error_log("CC onay maili başarıyla gönderildi: " . $cc . " - Teklif: " . $quote_number);
                }
            } else {
                $email_error = 'Email gönderim hatası';
            }

        } catch (Exception $mail_error) {
            $email_error = $mail_error->getMessage();
            error_log("Email sending failed: " . $email_error);
        }

        // Sonuç mesajı
        if ($email_sent) {
            $message_text = 'Onay kaydedildi ve mail gönderildi';
            if ($cc_sent) {
                $message_text .= ' (info ve erhan adreslerine)';
            } else {
                $message_text .= ' (sadece info adresine)';
            }
        } else {
            $message_text = 'Onay kaydedildi (mail gönderilemedi: ' . $email_error . ')';
        }

        echo json_encode([
            'success' => true,
            'message' => $message_text
        ]);
    } else {
        throw new Exception('Onay kaydedilemedi');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Hata: ' . $e->getMessage()
    ]);
}