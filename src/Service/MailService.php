<?php
declare(strict_types=1);

namespace Daybreak\Service;

use Daybreak\Config;
use RuntimeException;

/**
 * Send transactional email.
 * Uses SMTP when SMTP_HOST is configured; falls back to PHP mail() otherwise.
 * All connections use TLS with peer verification (no verify=false).
 */
final class MailService
{
    public function send(string $to, string $subject, string $body): void
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException("Invalid recipient address: {$to}");
        }

        // Strip CR/LF from header fields. FILTER_VALIDATE_EMAIL already rejects them,
        // but explicit sanitisation makes the intent clear and guards against any future
        // change in PHP's email validator behaviour.
        $to      = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);

        if (Config::get('SMTP_HOST') !== null && Config::get('SMTP_HOST') !== '') {
            $this->sendViaSmtp($to, $subject, $body);
        } else {
            $this->sendViaMail($to, $subject, $body);
        }
    }

    // ── PHP mail() fallback ─────────────────────────────────────────────────

    private function sendViaMail(string $to, string $subject, string $body): void
    {
        $from     = Config::get('SMTP_FROM', 'noreply@daybreak.silverday.de');
        $fromName = Config::get('SMTP_FROM_NAME', 'Daybreak');
        $headers  = implode("\r\n", [
            'From: ' . $fromName . ' <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);
        mail($to, $subject, $body, $headers);
    }

    // ── SMTP client ─────────────────────────────────────────────────────────

    private function sendViaSmtp(string $to, string $subject, string $body): void
    {
        $host     = (string)  Config::get('SMTP_HOST');
        $port     = (int)    (Config::get('SMTP_PORT', '587'));
        $user     = Config::get('SMTP_USER', '');
        $pass     = Config::get('SMTP_PASS', '');
        $from     = Config::get('SMTP_FROM', 'noreply@daybreak.silverday.de');
        $fromName = Config::get('SMTP_FROM_NAME', 'Daybreak');

        $scheme = $port === 465 ? 'ssl' : 'tcp';
        $ctx    = stream_context_create(['ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ]]);

        $sock = @stream_socket_client(
            "{$scheme}://{$host}:{$port}", $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );
        if ($sock === false) {
            throw new RuntimeException("SMTP connect to {$host}:{$port} failed ({$errno}): {$errstr}");
        }
        stream_set_timeout($sock, 15);

        try {
            $this->smtpExpect($sock, 220);

            $helo = gethostname() ?: 'localhost';
            $this->smtpCmd($sock, "EHLO {$helo}");

            if ($port !== 465) {
                $this->smtpCmd($sock, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                $this->smtpCmd($sock, "EHLO {$helo}");
            }

            if ($user !== '') {
                $this->smtpCmd($sock, 'AUTH LOGIN', 334);
                $this->smtpCmd($sock, base64_encode($user), 334);
                $this->smtpCmd($sock, base64_encode($pass), 235);
            }

            $domain  = substr($from, (int) strrpos($from, '@') + 1) ?: 'daybreak.silverday.de';
            $msgId   = '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
            $encFrom = mb_encode_mimeheader($fromName, 'UTF-8', 'B', "\r\n") . " <{$from}>";
            $encSubj = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");

            $this->smtpCmd($sock, "MAIL FROM:<{$from}>");
            $this->smtpCmd($sock, "RCPT TO:<{$to}>");
            $this->smtpCmd($sock, 'DATA', 354);

            $message  = "Date: " . date('r') . "\r\n";
            $message .= "Message-ID: {$msgId}\r\n";
            $message .= "From: {$encFrom}\r\n";
            $message .= "To: {$to}\r\n";
            $message .= "Subject: {$encSubj}\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n";
            $message .= "\r\n";
            $message .= $body;

            // RFC 5321 dot-stuffing: any line starting with '.' gets an extra '.'.
            $lines   = explode("\n", str_replace("\r\n", "\n", $message));
            $stuffed = implode("\r\n", array_map(
                static fn(string $l) => str_starts_with($l, '.') ? '.' . $l : $l,
                $lines
            ));
            if (!str_ends_with($stuffed, "\r\n")) {
                $stuffed .= "\r\n";
            }

            fwrite($sock, $stuffed . ".\r\n");
            $this->smtpExpect($sock, 250);
            $this->smtpCmd($sock, 'QUIT', 221);
        } finally {
            fclose($sock);
        }
    }

    /** @param resource $sock */
    private function smtpExpect($sock, int $expected): string
    {
        $response = '';
        while (!feof($sock)) {
            $line = fgets($sock, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Multi-line responses use 'NNN-...' for continuation; 'NNN ...' terminates.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if ($code !== $expected) {
            throw new RuntimeException(
                "SMTP expected {$expected}, got {$code}: " . trim($response)
            );
        }
        return $response;
    }

    /** @param resource $sock */
    private function smtpCmd($sock, string $cmd, int $expect = 250): string
    {
        fwrite($sock, $cmd . "\r\n");
        return $this->smtpExpect($sock, $expect);
    }
}
