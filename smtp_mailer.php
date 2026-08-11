<?php
require_once __DIR__ . "/smtp_config.php";

/**
 * Sends a plain-text OTP email through Gmail SMTP over SSL.
 * Returns true on success and false on failure.
 */
function sendOtpEmail(string $toEmail, string $otp): bool
{
    $host = "ssl://" . SMTP_HOST;
    $errno = 0;
    $errstr = "";

    $socket = @fsockopen($host, SMTP_PORT, $errno, $errstr, 20);

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!smtpExpect($socket, [220])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "EHLO localhost", [250])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "AUTH LOGIN", [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode(SMTP_USERNAME), [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode(SMTP_PASSWORD), [235])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "MAIL FROM:<" . SMTP_FROM_EMAIL . ">", [250])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "RCPT TO:<" . $toEmail . ">", [250, 251])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, "DATA", [354])) {
        fclose($socket);
        return false;
    }

    $subject = "Your H.A Coaching Registration OTP";
    $body = "Hello,\r\n\r\n"
          . "Your OTP for H.A Coaching student registration is: " . $otp . "\r\n\r\n"
          . "This OTP is valid for 10 minutes.\r\n"
          . "If you did not request this registration, please ignore this email.\r\n\r\n"
          . "Regards,\r\n"
          . SMTP_FROM_NAME;

    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n"
             . "To: <" . $toEmail . ">\r\n"
             . "Subject: " . $subject . "\r\n"
             . "MIME-Version: 1.0\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    $message = $headers . "\r\n" . $body . "\r\n.\r\n";

    fwrite($socket, $message);

    if (!smtpExpect($socket, [250])) {
        fclose($socket);
        return false;
    }

    smtpCommand($socket, "QUIT", [221]);
    fclose($socket);

    return true;
}

function smtpCommand($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

function smtpExpect($socket, array $expectedCodes): bool
{
    $response = "";

    while (($line = fgets($socket, 515)) !== false) {
        $response = $line;

        // SMTP multiline response ends with "XYZ " (space).
        if (strlen($line) >= 4 && $line[3] === " ") {
            break;
        }
    }

    if ($response === "") {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return in_array($code, $expectedCodes, true);
}
?>
