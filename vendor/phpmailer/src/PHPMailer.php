<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const VERSION = "6.8.0";
    const CHARSET_ISO88591 = "iso-8859-1";
    const CHARSET_UTF8 = "utf-8";
    const CONTENT_TYPE_PLAINTEXT = "text/plain";
    const CONTENT_TYPE_TEXT_CALENDAR = "text/calendar";
    const CONTENT_TYPE_TEXT_HTML = "text/html";
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = "multipart/alternative";
    const CONTENT_TYPE_MULTIPART_MIXED = "multipart/mixed";
    const CONTENT_TYPE_MULTIPART_RELATED = "multipart/related";
    const ENCODING_7BIT = "7bit";
    const ENCODING_8BIT = "8bit";
    const ENCODING_BASE64 = "base64";
    const ENCODING_BINARY = "binary";
    const ENCODING_QUOTED_PRINTABLE = "quoted-printable";
    const ENCRYPTION_STARTTLS = "tls";
    const ENCRYPTION_SMTPS = "ssl";
    const ICAL_METHOD_REQUEST = "REQUEST";
    const ICAL_METHOD_PUBLISH = "PUBLISH";
    const ICAL_METHOD_REPLY = "REPLY";
    const ICAL_METHOD_ADD = "ADD";
    const ICAL_METHOD_CANCEL = "CANCEL";
    const ICAL_METHOD_REFRESH = "REFRESH";
    const ICAL_METHOD_COUNTER = "COUNTER";
    const ICAL_METHOD_DECLINECOUNTER = "DECLINECOUNTER";

    public $Priority;
    public $CharSet = self::CHARSET_ISO88591;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = "";
    public $From = "root@localhost";
    public $FromName = "Root User";
    public $Sender = "";
    public $Subject = "";
    public $Body = "";
    public $AltBody = "";
    public $Ical = "";
    public $WordWrap = 0;
    public $Mailer = "mail";
    public $Sendmail = "/usr/sbin/sendmail";
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = "";
    public $Hostname = "";
    public $MessageID = "";
    public $MessageDate = "";
    public $Host = "localhost";
    public $Port = 25;
    public $Helo = "";
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = "";
    public $Password = "";
    public $AuthType = "";
    public $Timeout = 300;
    public $dsn = "";
    public $SMTPDebug = 0;
    public $Debugoutput = "echo";
    public $SMTPKeepAlive = false;
    public $SMTPSecure = "";
    public $SMTPAutoTLS = true;
    public $SMTPAuthType = "";
    public $DKIM_selector = "";
    public $DKIM_identity = "";
    public $DKIM_passphrase = "";
    public $DKIM_domain = "";
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = "";
    public $DKIM_private_string = "";
    public $action_function = "";
    public $XMailer = "";

    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $recipients_failed = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = "";
    protected $message_type = "";
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = "";
    protected $sign_key_file = "";
    protected $sign_extracerts_file = "";
    protected $sign_key_pass = "";
    protected $exceptions = false;
    protected $uniqueid = "";

    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;

    public function __construct($exceptions = null)
    {
        if ($exceptions !== null) {
            $this->exceptions = (bool) $exceptions;
        }

        if (function_exists("hash_algos") && in_array("sha256", hash_algos()) && function_exists("hash_hmac")) {
            $this->DKIM_default_key_size = 2048;
        }

        $this->uniqueid = $this->generateId();
        $this->CharSet = self::CHARSET_UTF8;
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    public function addAddress($address, $name = "")
    {
        return $this->addOrEnqueueAnAddress("to", $address, $name);
    }

    public function addCC($address, $name = "")
    {
        return $this->addOrEnqueueAnAddress("cc", $address, $name);
    }

    public function addBCC($address, $name = "")
    {
        return $this->addOrEnqueueAnAddress("bcc", $address, $name);
    }

    public function addReplyTo($address, $name = "")
    {
        return $this->addOrEnqueueAnAddress("Reply-To", $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name = trim(preg_replace("/[\r\n]+/", "", $name));

        if (($pos = strrpos($address, "@")) === false) {
            $error_message = sprintf("%s (%s): %s", "Invalid address", "addOrEnqueueAnAddress", $address);
            $this->setError($error_message);

            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }

        if ($kind !== "Reply-To") {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;

                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];

                return true;
            }
        }

        return false;
    }

    public function setFrom($address, $name = "", $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace("/[\r\n]+/", "", $name));

        if (($pos = strrpos($address, "@")) === false) {
            $error_message = sprintf("%s (%s): %s", "Invalid address", "setFrom", $address);
            $this->setError($error_message);

            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }

        $this->From = $address;
        $this->FromName = $name;

        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }

        return true;
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = self::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = self::CONTENT_TYPE_PLAINTEXT;
        }
    }

    public function isSMTP()
    {
        $this->Mailer = "smtp";
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }

            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = "";
            $this->setError($exc->getMessage());

            if ($this->exceptions) {
                throw $exc;
            }

            return false;
        }
    }

    public function preSend()
    {
        if ($this->Mailer === "smtp") {
            $this->smtp = $this->getSMTPInstance();
        }

        if (empty($this->From)) {
            $this->setError("From address is empty");
            return false;
        }

        if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
            $this->setError("You must provide at least one recipient email address");
            return false;
        }

        $this->mailHeader = $this->createHeader();
        $this->MIMEBody = $this->createBody();

        if ($this->Mailer === "smtp") {
            return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
        }

        return true;
    }

    public function postSend()
    {
        return true;
    }

    public function getSMTPInstance()
    {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }

        return $this->smtp;
    }

    protected function smtpSend($header, $body)
    {
        $smtp = $this->getSMTPInstance();

        if (!$smtp->connect($this->Host, $this->Port, $this->Timeout, $this->SMTPOptions)) {
            throw new Exception("SMTP connect() failed: " . $smtp->getError()["error"]);
        }

        if (!empty($this->SMTPSecure)) {
            if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS) {
                if (!$smtp->startTLS()) {
                    throw new Exception("STARTTLS not accepted from server");
                }
            }
        }

        if ($this->SMTPAuth) {
            if (!$smtp->authenticate($this->Username, $this->Password, $this->AuthType)) {
                throw new Exception("SMTP authentication failed: " . $smtp->getError()["error"]);
            }
        }

        $callbacks = [];

        foreach ([$this->to, $this->cc, $this->bcc] as $togroup) {
            foreach ($togroup as $to) {
                if (!$smtp->sendCommand("MAIL FROM", "MAIL FROM:<" . $this->From . ">", 250)) {
                    throw new Exception("MAIL FROM command failed: " . $smtp->getError()["error"]);
                }

                if (!$smtp->sendCommand("RCPT TO", "RCPT TO:<" . $to[0] . ">", [250, 251])) {
                    continue;
                }

                if (!$smtp->sendCommand("DATA", "DATA", 354)) {
                    throw new Exception("DATA command failed: " . $smtp->getError()["error"]);
                }

                if (!$smtp->sendCommand("Message data", $header . $body . ".", 250)) {
                    throw new Exception("Message data failed: " . $smtp->getError()["error"]);
                }
            }
        }

        if ($this->SMTPKeepAlive) {
            $smtp->reset();
        } else {
            $smtp->quit();
            $smtp->close();
        }

        return true;
    }

    protected function createHeader()
    {
        $result = "";

        $result .= $this->headerLine("Date", $this->rfcDate());

        if ($this->MessageID !== "") {
            $this->lastMessageID = $this->MessageID;
        } else {
            $this->lastMessageID = sprintf("<%s@%s>", $this->uniqueid, $this->serverHostname());
        }
        $result .= $this->headerLine("Message-ID", $this->lastMessageID);

        if ($this->XMailer === "") {
            $result .= $this->headerLine("X-Mailer", "PHPMailer " . self::VERSION . " (https://github.com/PHPMailer/PHPMailer)");
        } else {
            $myXmailer = trim($this->XMailer);
            if ($myXmailer) {
                $result .= $this->headerLine("X-Mailer", $myXmailer);
            }
        }

        if ($this->ConfirmReadingTo !== "") {
            $result .= $this->headerLine("Disposition-Notification-To", "<" . $this->ConfirmReadingTo . ">");
        }

        $result .= $this->headerLine("From", $this->addrFormat([$this->From, $this->FromName]));

        if (count($this->to) > 0) {
            $result .= $this->addrAppend("To", $this->to);
        } elseif (count($this->cc) === 0) {
            $result .= $this->headerLine("To", "undisclosed-recipients:;");
        }

        if (count($this->cc) > 0) {
            $result .= $this->addrAppend("Cc", $this->cc);
        }

        $result .= $this->headerLine("Subject", $this->encodeHeader($this->secureHeader($this->Subject)));

        if ($this->MessageDate === "") {
            $result .= $this->headerLine("Date", $this->rfcDate());
        } else {
            $result .= $this->headerLine("Date", $this->MessageDate);
        }

        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(trim($header[0]), $this->encodeHeader(trim($header[1])));
        }

        if (!$this->sign_key_file) {
            $result .= $this->headerLine("MIME-Version", "1.0");
            $result .= $this->getMailMIME();
        }

        return $result;
    }

    protected function createBody()
    {
        $body = "";

        if ($this->sign_key_file) {
            $body .= $this->getMailMIME() . $this->LE;
        }

        $this->setWordWrap();

        $bodyEncoding = $this->Encoding;
        $bodyCharSet = $this->CharSet;

        if ($bodyEncoding === self::ENCODING_8BIT && !$this->has8bitChars($this->Body)) {
            $bodyEncoding = self::ENCODING_7BIT;
            $bodyCharSet = self::CHARSET_UTF8;
        }

        if ($this->ContentType === self::CONTENT_TYPE_TEXT_HTML) {
            $body .= $this->Body;
        } else {
            $body .= $this->normalizeBreaks($this->Body);
        }

        return $body;
    }

    public function headerLine($name, $value)
    {
        return $name . ": " . $value . static::$LE;
    }

    public function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return $this->secureHeader($addr[0]);
        } else {
            return $this->encodeHeader($this->secureHeader($addr[1]), "phrase") . " <" . $this->secureHeader($addr[0]) . ">";
        }
    }

    public function addrAppend($type, $addr)
    {
        $addresses = [];
        foreach ($addr as $address) {
            $addresses[] = $this->addrFormat($address);
        }

        return $this->headerLine($type, implode(", ", $addresses));
    }

    public function encodeHeader($str, $position = "text")
    {
        $x = 0;

        switch (strtolower($position)) {
            case "phrase":
                if (!preg_match("/[\200-\377]/", $str)) {
                    if (preg_match("/[^A-Za-z0-9!#$%&'*+\/=?^_`{|}~ -]/", $str)) {
                        return "\"" . addcslashes($str, "\0..\37\177\\\"") . "\"";
                    }
                }
                $x = preg_match_all("/[^\040\041\043-\133\135-\176]/", $str, $matches);
                break;
            case "comment":
                $x = preg_match_all("/[()\"]/", $str, $matches);
            case "text":
            default:
                $x += preg_match_all("/[\000-\010\013\014\016-\037\177-\377]/", $str, $matches);
                break;
        }

        if ($x === 0 && strlen($str) < 75) {
            return $str;
        }

        $maxlen = 75 - 7 - strlen($this->CharSet);

        if ($x > strlen($str) / 3) {
            $encoding = "B";
            if (function_exists("mb_strlen") && $this->hasMultiBytes($str)) {
                $encoded = $this->base64EncodeWrapMB($str, "\n");
            } else {
                $encoded = base64_encode($str);
                $maxlen -= $maxlen % 4;
                $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
            }
        } else {
            $encoding = "Q";
            $encoded = $this->encodeQPphp($str);
        }

        $encoded = preg_replace("/^(.*)$/m", " =?" . $this->CharSet . "?$encoding?\1?=", $encoded);
        $encoded = trim(str_replace("\n", static::$LE, $encoded));

        return $encoded;
    }

    public function hasMultiBytes($str)
    {
        if (function_exists("mb_strlen")) {
            return strlen($str) > mb_strlen($str, $this->CharSet);
        } else {
            return false;
        }
    }

    public function has8bitChars($text)
    {
        return (bool) preg_match("/[\x80-\xFF]/", $text);
    }

    public function base64EncodeWrapMB($str, $linebreak = null)
    {
        $start = "=?" . $this->CharSet . "?B?";
        $end = "?=";
        $encoded = "";

        if ($linebreak === null) {
            $linebreak = static::$LE;
        }

        $mb_length = mb_strlen($str, $this->CharSet);
        $length = 75 - strlen($start) - strlen($end);
        $ratio = $mb_length / strlen($str);
        $avgLength = floor($length * $ratio * .75);

        for ($i = 0; $i < $mb_length; $i += $offset) {
            $lookBack = 0;
            do {
                $offset = $avgLength - $lookBack;
                $chunk = mb_substr($str, $i, $offset, $this->CharSet);
                $chunk = base64_encode($chunk);
                ++$lookBack;
            } while (strlen($chunk) > $length);

            $encoded .= $chunk . $linebreak;
        }

        return substr($encoded, 0, -strlen($linebreak));
    }

    public function encodeQPphp($string)
    {
        return str_replace(["=", "_"], ["=3D", "=5F"], quoted_printable_encode($string));
    }

    public function getMailMIME()
    {
        $result = "";
        $ismultipart = true;

        switch ($this->message_type) {
            case "inline":
                $result .= $this->headerLine("Content-Type", $this->ContentType . "; charset=" . $this->CharSet);
                $result .= $this->headerLine("Content-Transfer-Encoding", $this->Encoding);
                $ismultipart = false;
                break;
            case "attach":
            case "inline_attach":
            case "alt_attach":
            case "alt_inline_attach":
                $result .= sprintf("Content-Type: %s;%s\ttype=\"text/html\";%s\tboundary=\"%s\"%s", "multipart/related", static::$LE, static::$LE, $this->boundary[1], static::$LE);
                break;
            case "alt":
            case "alt_inline":
                $result .= sprintf("Content-Type: %s;%s\tboundary=\"%s\"%s", "multipart/alternative", static::$LE, $this->boundary[1], static::$LE);
                break;
            default:
                $result .= $this->headerLine("Content-Type", $this->ContentType . "; charset=" . $this->CharSet);
                $result .= $this->headerLine("Content-Transfer-Encoding", $this->Encoding);
                $ismultipart = false;
                break;
        }

        if ($this->Mailer !== "mail") {
            $result .= static::$LE;
        }

        return $result;
    }

    public function setWordWrap()
    {
        if ($this->WordWrap < 1) {
            return;
        }

        switch ($this->message_type) {
            case "alt":
            case "alt_inline":
            case "alt_attach":
            case "alt_inline_attach":
                $this->AltBody = $this->wrapText($this->AltBody, $this->WordWrap);
                break;
            default:
                $this->Body = $this->wrapText($this->Body, $this->WordWrap);
                break;
        }
    }

    public function wrapText($message, $length, $qp_mode = false)
    {
        if ($qp_mode) {
            $soft_break = sprintf(" =%s", static::$LE);
        } else {
            $soft_break = static::$LE;
        }

        $is_html = ($this->ContentType === self::CONTENT_TYPE_TEXT_HTML);
        $lelen = strlen(static::$LE);
        $crlflen = strlen(static::CRLF);

        $message = $this->normalizeBreaks($message);

        if (substr($message, -$lelen) === static::$LE) {
            $message = substr($message, 0, -$lelen);
        }

        $lines = explode(static::$LE, $message);
        $message = "";
        foreach ($lines as $line) {
            $words = explode(" ", $line);
            $buf = "";
            $firstword = true;

            foreach ($words as $word) {
                if ($qp_mode && (strlen($word) > $length)) {
                    $space_left = $length - strlen($buf) - $crlflen;
                    if ($space_left > 20) {
                        $len = $space_left;
                        if (substr($word, $len - 1, 1) === "=") {
                            --$len;
                        } elseif (substr($word, $len - 2, 1) === "=") {
                            $len -= 2;
                        }
                        $part = substr($word, 0, $len);
                        $word = substr($word, $len);
                        $buf .= " " . $part;
                        $message .= $buf . sprintf("=%s", static::$LE);
                    } else {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }

                if ($firstword) {
                    $firstword = false;
                } else {
                    $buf .= " ";
                }
                $buf .= $word;

                if (strlen($buf) > $length && $buf !== "") {
                    if ($qp_mode) {
                        $encoded = $this->encodeQPphp($buf);
                        if (strlen($encoded) > $length && $encoded !== $buf) {
                            $message .= $buf . $soft_break;
                        } else {
                            $message .= $encoded . $soft_break;
                        }
                    } else {
                        $message .= $buf . $soft_break;
                    }
                    $buf = "";
                }
            }
            $message .= $buf . static::$LE;
        }

        return $message;
    }

    public function normalizeBreaks($text, $breaktype = null)
    {
        if ($breaktype === null) {
            $breaktype = static::$LE;
        }

        $text = preg_replace("/(\r\n|\r|\n)/", $breaktype, $text);

        return $text;
    }

    public function setError($msg)
    {
        ++$this->error_count;
        if ($this->Mailer === "smtp" && is_object($this->smtp)) {
            $lasterror = $this->smtp->getError();
            if (!empty($lasterror["error"])) {
                $msg .= $this->lang("smtp_error") . $lasterror["error"];
                if (!empty($lasterror["detail"])) {
                    $msg .= " Detail: " . $lasterror["detail"];
                }
            }
        }
        $this->ErrorInfo = $msg;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if ($patternselect === null) {
            $patternselect = static::$validator;
        }

        if (is_callable($patternselect)) {
            return $patternselect($address);
        }

        if (strpos($address, "\n") !== false || strpos($address, "\r") !== false) {
            return false;
        }

        switch ($patternselect) {
            case "auto":
                if (function_exists("filter_var")) {
                    return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
                }

                return (bool) preg_match("/^(?!(?>(?1)\"?(?>[^\r\n\"\\]++|\\.)*+\"?(?1))*+;)((?1)\"?(?>[^\r\n\"\\]++|\\.)*+\"?(?1))*+@(?>(?>(?1)(?>[a-z0-9]++|[a-z0-9]++(?>[a-z0-9-]*+[a-z0-9]++)*+)(?1))*+\.(?1)(?>[a-z0-9]++|[a-z0-9]++(?>[a-z0-9-]*+[a-z0-9]++)*+)(?1))*+$/isD", $address);
            case "pcre":
                return (bool) preg_match("/^(?!(?>(?1)\"?(?>[^\r\n\"\\]++|\\.)*+\"?(?1))*+;)((?1)\"?(?>[^\r\n\"\\]++|\\.)*+\"?(?1))*+@(?>(?>(?1)(?>[a-z0-9]++|[a-z0-9]++(?>[a-z0-9-]*+[a-z0-9]++)*+)(?1))*+\.(?1)(?>[a-z0-9]++|[a-z0-9]++(?>[a-z0-9-]*+[a-z0-9]++)*+)(?1))*+$/isD", $address);
            case "html5":
                return (bool) preg_match("/^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD", $address);
            case "noregex":
                return (strpos($address, "@") !== false);
            default:
                return false;
        }
    }

    public function lang($key)
    {
        if (count($this->language) < 1) {
            $this->setLanguage("en");
        }

        if (array_key_exists($key, $this->language)) {
            if ($key === "smtp_connect_failed") {
                return $this->language[$key] . " https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting";
            }

            return $this->language[$key];
        } else {
            return "Language string failed to load: " . $key;
        }
    }

    public function setLanguage($langcode = "en", $lang_path = "")
    {
        $renamed_langcodes = [
            "br" => "pt_br",
            "cz" => "cs",
            "dk" => "da",
            "no" => "nb",
            "se" => "sv",
            "rs" => "sr",
            "tg" => "tl"
        ];

        if (array_key_exists($langcode, $renamed_langcodes)) {
            $langcode = $renamed_langcodes[$langcode];
        }

        $PHPMAILER_LANG = [
            "authenticate" => "SMTP Error: Could not authenticate.",
            "connect_host" => "SMTP Error: Could not connect to SMTP host.",
            "data_not_accepted" => "SMTP Error: data not accepted.",
            "empty_message" => "Message body empty",
            "encoding" => "Unknown encoding: ",
            "execute" => "Could not execute: ",
            "file_access" => "Could not access file: ",
            "file_open" => "File Error: Could not open file: ",
            "from_failed" => "The following From address failed: ",
            "instantiate" => "Could not instantiate mail function.",
            "invalid_address" => "Invalid address: ",
            "mailer_not_supported" => " mailer is not supported.",
            "provide_address" => "You must provide at least one recipient email address.",
            "recipients_failed" => "SMTP Error: The following recipients failed: ",
            "signing" => "Signing Error: ",
            "smtp_connect_failed" => "SMTP connect() failed.",
            "smtp_error" => "SMTP server error: ",
            "variable_set" => "Cannot set or reset variable: ",
            "extension_missing" => "Extension missing: "
        ];

        $this->language = $PHPMAILER_LANG;

        return (count($this->language) > 1);
    }

    public function getTranslations()
    {
        return $this->language;
    }



    public function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n"], "", $str));
    }

    protected function rfcDate()
    {
        return date("r");
    }

    protected function serverHostname()
    {
        $result = "";
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER) && array_key_exists("SERVER_NAME", $_SERVER)) {
            $result = $_SERVER["SERVER_NAME"];
        } elseif (function_exists("gethostname") && gethostname() !== false) {
            $result = gethostname();
        } elseif (php_uname("n") !== false) {
            $result = php_uname("n");
        }
        if (!static::validateAddress("test@" . $result, "auto")) {
            return "localhost.localdomain";
        }

        return $result;
    }

    protected function generateId()
    {
        $len = 32;
        $bytes = "";
        if (function_exists("random_bytes")) {
            try {
                $bytes = random_bytes($len);
            } catch (\Exception $e) {
                $bytes = "";
            }
        }
        if (strlen($bytes) < $len) {
            $bytes = hash("sha256", uniqid((string) mt_rand(), true), true);
        }

        return str_replace(["=", "+", "/"], "", base64_encode(hash("sha256", $bytes, true)));
    }

    public function smtpClose()
    {
        if (is_a($this->smtp, "PHPMailer\PHPMailer\SMTP")) {
            if ($this->smtp->connected()) {
                $this->smtp->quit();
                $this->smtp->close();
            }
        }
    }

    public static $validator = "auto";
    public static $LE = "\r\n";
    protected $MIMEBody = "";
    protected $MIMEHeader = "";
    protected $mailHeader = "";
}
?>