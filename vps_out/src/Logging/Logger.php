<?php

declare(strict_types=1);

namespace MangaNexus\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;

class Logger
{
    private static ?MonologLogger $logger = null;

    /**
     * Get the Monolog logger instance.
     */
    public static function get(): MonologLogger
    {
        if (self::$logger === null) {
            $logDir = defined('BASE_PATH') ? BASE_PATH . '/logs' : dirname(dirname(__DIR__)) . '/data/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $logger = new MonologLogger('manganexus');

            // Format: [2026-05-31 12:00:00] [CHANNEL] [LEVEL] MESSAGE {CONTEXT}
            $outputFormat = "[%datetime%] [%channel%] [%level_name%]: %message% %context% %extra%\n";
            $formatter = new LineFormatter($outputFormat, "Y-m-d H:i:s", true, true);

            // Daily log rotation, keeping 14 days of history
            $handler = new RotatingFileHandler(
                $logDir . '/manganexus.log',
                14,
                MonologLogger::DEBUG
            );
            $handler->setFormatter($formatter);

            $logger->pushHandler($handler);

            // Isolate performance profiling processors (GitProcessor, HostnameProcessor) to local staging/testing environments only
            $is_local = (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) || str_contains($_SERVER['HTTP_HOST'] ?? '', 'localhost') || getenv('APP_ENV') === 'local' || getenv('APP_ENV') === 'testing');
            if ($is_local) {
                $logger->pushProcessor(new \Monolog\Processor\GitProcessor());
                $logger->pushProcessor(new \Monolog\Processor\HostnameProcessor());
            }

            self::$logger = $logger;
        }

        return self::$logger;
    }

    /**
     * Log info messages.
     */
    public static function info(string $message, array $context = []): void
    {
        self::get()->info($message, $context);
    }

    /**
     * Log warning messages.
     */
    public static function warning(string $message, array $context = []): void
    {
        self::get()->warning($message, $context);
    }

    /**
     * Log error messages.
     */
    public static function error(string $message, array $context = []): void
    {
        self::get()->error($message, $context);
    }

    /**
     * Log debug messages.
     */
    public static function debug(string $message, array $context = []): void
    {
        self::get()->debug($message, $context);
    }
}
