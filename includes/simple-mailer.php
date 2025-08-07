<?php
// Basit SMTP E-posta Gönderme Sınıfı
class SimpleMailer {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;

    public function __construct($host, $port, $username, $password, $from_email, $from_name) {
        $this->smtp_host = $host;
        $this->smtp_port = $port;
        $this->smtp_username = $username;
        $this->smtp_password = $password;
        $this->from_email = $from_email;
        $this->from_name = $from_name;
    }

    public function sendEmail($to, $subject, $body, $to_name = '') {
        try {
            // SMTP bağlantısı
            $socket = fsockopen($this->smtp_host, $this->smtp_port, $errno, $errstr, 30);

            if (!$socket) {
                throw new Exception("SMTP bağlantı hatası: $errstr ($errno)");
            }

            // SMTP komutları
            $this->readResponse($socket);

            // EHLO
            $hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            fwrite($socket, "EHLO " . $hostname . "\r\n");
            $this->readResponse($socket);

            // STARTTLS
            fwrite($socket, "STARTTLS\r\n");
            $this->readResponse($socket);

            // TLS'yi etkinleştir
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            // Tekrar EHLO
            fwrite($socket, "EHLO " . $hostname . "\r\n");
            $this->readResponse($socket);

            // AUTH LOGIN
            fwrite($socket, "AUTH LOGIN\r\n");
            $this->readResponse($socket);

            // Kullanıcı adı
            fwrite($socket, base64_encode($this->smtp_username) . "\r\n");
            $this->readResponse($socket);

            // Şifre
            fwrite($socket, base64_encode($this->smtp_password) . "\r\n");
            $this->readResponse($socket);

            // MAIL FROM
            fwrite($socket, "MAIL FROM: <" . $this->from_email . ">\r\n");
            $this->readResponse($socket);

            // RCPT TO
            fwrite($socket, "RCPT TO: <$to>\r\n");
            $this->readResponse($socket);

            // DATA
            fwrite($socket, "DATA\r\n");
            $this->readResponse($socket);

            // E-posta içeriği
            $encoded_subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
            $encoded_from_name = "=?UTF-8?B?" . base64_encode($this->from_name) . "?=";

            $headers = "From: " . $encoded_from_name . " <" . $this->from_email . ">\r\n";
            $headers .= "To: $to_name <$to>\r\n";
            $headers .= "Subject: $encoded_subject\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            $headers .= "\r\n";

            $message = $headers . $body . "\r\n.\r\n";
            fwrite($socket, $message);
            $this->readResponse($socket);

            // QUIT
            fwrite($socket, "QUIT\r\n");
            $this->readResponse($socket);

            fclose($socket);

            return true;

        } catch (Exception $e) {
            error_log("SimpleMailer hatası: " . $e->getMessage());
            return false;
        }
    }

    private function readResponse($socket) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }

        $code = substr($response, 0, 3);

        // Hata kodlarını kontrol et
        if ($code >= 400) {
            throw new Exception("SMTP Hatası: $response");
        }

        return $response;
    }
}

// SMTP ile e-posta gönderme fonksiyonu
function sendEmailSMTP($to, $subject, $body, $to_name = '') {
    $mailer = new SimpleMailer(
        'smtp.yandex.com',
        587,
        'info@europatrans.com.tr',
        'erikdalieuropa123+',
        'info@europatrans.com.tr',
        'Europatrans Global Lojistik'
    );

    return $mailer->sendEmail($to, $subject, $body, $to_name);
}
?>