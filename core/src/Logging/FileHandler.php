<?php

declare(strict_types=1);

namespace Shopologic\Core\Logging;

class FileHandler implements HandlerInterface
{
    private string $logPath;

    public function __construct(string $logPath)
    {
        $this->logPath = rtrim($logPath, '/');
        
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function handle(array $record): void
    {
        $filename = $this->logPath . '/' . date('Y-m-d') . '.log';
        
        $formatted = $this->format($record);
        
        file_put_contents($filename, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    protected function format(array $record): string
    {
        $datetime = $record['datetime']->format('Y-m-d H:i:s');
        $level = strtoupper($record['level']);
        $message = $record['message'];
        
        $context = '';
        if (!empty($record['context'])) {
            $context = ' ' . json_encode($record['context']);
        }

        return "[{$datetime}] {$level}: {$message}{$context}";
    }
}