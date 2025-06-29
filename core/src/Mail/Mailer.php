<?php

declare(strict_types=1);

namespace Shopologic\Core\Mail;

class Mailer
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'from' => 'noreply@shopologic.com',
            'from_name' => 'Shopologic',
            'driver' => 'log',
        ], $config);
    }

    /**
     * Send an email
     */
    public function send(string $to, string $subject, string $body, array $headers = []): bool
    {
        $driver = $this->config['driver'];

        switch ($driver) {
            case 'mail':
                return $this->sendViaMail($to, $subject, $body, $headers);
            case 'smtp':
                return $this->sendViaSmtp($to, $subject, $body, $headers);
            case 'log':
            default:
                return $this->sendViaLog($to, $subject, $body, $headers);
        }
    }

    /**
     * Send via PHP mail function
     */
    protected function sendViaMail(string $to, string $subject, string $body, array $headers): bool
    {
        $headers = array_merge([
            'From' => $this->config['from_name'] . ' <' . $this->config['from'] . '>',
            'Reply-To' => $this->config['from'],
            'X-Mailer' => 'PHP/' . phpversion(),
        ], $headers);

        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }

        return mail($to, $subject, $body, $headerString);
    }

    /**
     * Send via SMTP (placeholder)
     */
    protected function sendViaSmtp(string $to, string $subject, string $body, array $headers): bool
    {
        // In a real implementation, this would use sockets to connect to SMTP server
        return $this->sendViaLog($to, $subject, $body, $headers);
    }

    /**
     * Log the email instead of sending
     */
    protected function sendViaLog(string $to, string $subject, string $body, array $headers): bool
    {
        $logFile = $this->config['log_file'] ?? 'storage/logs/mail.log';
        
        $log = sprintf(
            "[%s] To: %s\nSubject: %s\nHeaders: %s\nBody:\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            json_encode($headers),
            $body,
            str_repeat('-', 80)
        );

        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
        
        return true;
    }
}