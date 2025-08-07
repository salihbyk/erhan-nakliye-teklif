<?php
// PHPMailer SMTP Konfigürasyonu
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// PHPMailer dosyalarını dahil et
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

function createMailer() {
    $mail = new PHPMailer(true);

    try {
        // SMTP ayarları
        $mail->isSMTP();
        $mail->Host       = 'smtp.yandex.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info@europatrans.com.tr';
        $mail->Password   = 'erikdalieuropa123+';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = 0; // Debug için 2 yapabilirsiniz

        // Gönderen bilgileri
        $mail->setFrom('info@europatrans.com.tr', 'Europatrans Global Lojistik');
        $mail->addReplyTo('info@europatrans.com.tr', 'Europatrans Global Lojistik');

        // HTML formatı
        $mail->isHTML(true);

        return $mail;

    } catch (Exception $e) {
        error_log("PHPMailer konfigürasyon hatası: {$mail->ErrorInfo}");
        return false;
    }
}

// SMTP ile e-posta gönderme fonksiyonu
function sendEmailSMTP($to, $subject, $body, $recipientName = '') {
    $mail = createMailer();

    if (!$mail) {
        return false;
    }

    try {
        // Alıcı
        $mail->addAddress($to, $recipientName);

        // İçerik
        $mail->Subject = $subject;
        $mail->Body    = $body;

        // Gönder
        $result = $mail->send();

        if ($result) {
            error_log("E-posta başarıyla gönderildi: " . $to);
        }

        return $result;

    } catch (Exception $e) {
        error_log("E-posta gönderme hatası: {$mail->ErrorInfo}");
        return false;
    }
}
?>