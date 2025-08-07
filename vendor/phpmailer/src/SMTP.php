<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = "6.8.0";
    const CRLF = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;

    public $Version = self::VERSION;
    public $SMTP_PORT = self::DEFAULT_PORT;
    public $CRLF = self::CRLF;
    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = "echo";
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;

    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    protected $smtp_conn;
    protected $error;
    protected $helo_rply;
    protected $server_caps;
    protected $last_reply;

    public function __construct()
    {
        $this->smtp_conn = false;
        $this->error = [];
        $this->helo_rply = null;
        $this->server_caps = null;
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        if (is_null($port)) {
            $port = self::DEFAULT_PORT;
        }

        $this->smtp_conn = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (!$this->smtp_conn) {
            $this->setError("Failed to connect to server", "", $errno, $errstr);
            return false;
        }

        stream_set_timeout($this->smtp_conn, $timeout, 0);

        $announce = $this->get_lines();

        return true;
    }

    public function startTLS()
    {
        if (!$this->sendCommand("STARTTLS", "STARTTLS", 220)) {
            return false;
        }

        if (!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return false;
        }

        return true;
    }

    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if (!$this->sendCommand("AUTH LOGIN", "AUTH LOGIN", 334)) {
            return false;
        }

        if (!$this->sendCommand("Username", base64_encode($username), 334)) {
            return false;
        }

        if (!$this->sendCommand("Password", base64_encode($password), 235)) {
            return false;
        }

        return true;
    }

    public function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");
            return false;
        }

        $this->client_send($commandstring . self::CRLF, $command);

        $this->last_reply = $this->get_lines();
        $matches = [];

        if (preg_match("/^([0-9]{3})[ -](?:([0-9]\.[0-9]\.[0-9]) )?/", $this->last_reply, $matches)) {
            $code = (int) $matches[1];

            if ($code === $expect) {
                return true;
            }

            $this->setError("$command command failed", $this->last_reply, $code);
            return false;
        }

        $this->setError("$command command failed", $this->last_reply);
        return false;
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status["eof"]) {
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError("");
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = false;
        }
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return "";
        }

        $data = "";
        $endtime = time() + $this->Timeout;
        stream_set_timeout($this->smtp_conn, $this->Timeout, 0);

        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;

            if ((isset($str[3]) && $str[3] == " ")) {
                break;
            }

            $info = stream_get_meta_data($this->smtp_conn);
            if ($info["timed_out"]) {
                break;
            }

            if (time() > $endtime) {
                break;
            }
        }

        return $data;
    }

    protected function client_send($data, $command = "")
    {
        if (!is_resource($this->smtp_conn)) {
            return 0;
        }

        return fwrite($this->smtp_conn, $data);
    }

    protected function setError($message, $detail = "", $smtp_code = "", $smtp_code_ex = "")
    {
        $this->error = [
            "error" => $message,
            "detail" => $detail,
            "smtp_code" => $smtp_code,
            "smtp_code_ex" => $smtp_code_ex
        ];
    }

    public function getError()
    {
        return $this->error;
    }
}
?>