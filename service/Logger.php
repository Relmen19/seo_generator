<?php

declare(strict_types=1);

namespace Seo\Service;

use Throwable;

/**
 * Channel-based file logger.
 *
 * Files: logs/<channel>/YYYY-MM-DD.log
 * Format: [YYYY-MM-DD HH:MM:SS] [LEVEL] [channel] [pid:reqId] source — message {context_json}
 *
 * Channels: app (default), router, controller, db, gpt, publish, telegram,
 * cron, generator, image, editorial, keyword, qa.
 *
 * Usage:
 *   Logger::info('router', 'GET /articles', ['ip' => $ip]);
 *   Logger::error('gpt', 'API failed', ['err' => $msg]);
 *   Logger::channel('publish')->debug('payload built', $payload);
 */
class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO  = 'INFO';
    public const LEVEL_WARN  = 'WARN';
    public const LEVEL_ERROR = 'ERROR';

    public const CHANNEL_APP        = 'app';
    public const CHANNEL_ROUTER     = 'router';
    public const CHANNEL_CONTROLLER = 'controller';
    public const CHANNEL_DB         = 'db';
    public const CHANNEL_GPT        = 'gpt';
    public const CHANNEL_PUBLISH    = 'publish';
    public const CHANNEL_TELEGRAM   = 'telegram';
    public const CHANNEL_CRON       = 'cron';
    public const CHANNEL_GENERATOR  = 'generator';
    public const CHANNEL_IMAGE      = 'image';
    public const CHANNEL_EDITORIAL  = 'editorial';
    public const CHANNEL_KEYWORD    = 'keyword';
    public const CHANNEL_QA         = 'qa';

    private const LEVEL_PRIORITY = [
        self::LEVEL_DEBUG => 10,
        self::LEVEL_INFO  => 20,
        self::LEVEL_WARN  => 30,
        self::LEVEL_ERROR => 40,
    ];

    private static ?string $logDir   = null;
    private static ?string $minLevel = null;
    private static ?string $reqId    = null;
    private static array   $instances = [];

    private string $channel;

    public function __construct(string $channel = self::CHANNEL_APP)
    {
        $this->channel = $channel;
    }

    public static function channel(string $channel): self
    {
        return self::$instances[$channel] ??= new self($channel);
    }

    public static function debug(string $channel, string $message, array $context = []): void
    {
        self::channel($channel)->log(self::LEVEL_DEBUG, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::channel($channel)->log(self::LEVEL_INFO, $message, $context);
    }

    public static function warn(string $channel, string $message, array $context = []): void
    {
        self::channel($channel)->log(self::LEVEL_WARN, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::channel($channel)->log(self::LEVEL_ERROR, $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        try {
            if (!self::shouldLog($level)) return;

            $dir   = self::getLogDir() . '/' . $this->channel;
            $file  = $dir . '/' . date('Y-m-d') . '.log';
            $ts    = date('Y-m-d H:i:s');
            $pid   = getmypid();
            $rid   = self::getRequestId();
            $src   = self::resolveSource();

            $ctxStr = '';
            if (!empty($context)) {
                $ctxStr = ' ' . self::formatContext($context);
            }

            $line = sprintf(
                "[%s] [%s] [%s] [%d:%s] %s — %s%s\n",
                $ts, $level, $this->channel, $pid, $rid, $src, $message, $ctxStr
            );

            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Logging must never throw.
            @error_log('[Logger failure] ' . $e->getMessage());
        }
    }

    private static function shouldLog(string $level): bool
    {
        $min = self::$minLevel ?? self::resolveMinLevel();
        return (self::LEVEL_PRIORITY[$level] ?? 0) >= (self::LEVEL_PRIORITY[$min] ?? 10);
    }

    private static function resolveMinLevel(): string
    {
        $env = strtoupper((string)getenv('SEO_LOG_LEVEL'));
        if (isset(self::LEVEL_PRIORITY[$env])) {
            self::$minLevel = $env;
        } else {
            self::$minLevel = (defined('SEO_DEBUG') && SEO_DEBUG)
                ? self::LEVEL_DEBUG : self::LEVEL_INFO;
        }
        return self::$minLevel;
    }

    private static function getLogDir(): string
    {
        if (self::$logDir !== null) return self::$logDir;

        $envDir = getenv('SEO_LOG_DIR');
        if ($envDir && is_string($envDir) && $envDir !== '') {
            self::$logDir = rtrim($envDir, '/');
        } elseif (defined('SEO_ROOT')) {
            self::$logDir = SEO_ROOT . '/logs';
        } else {
            self::$logDir = __DIR__ . '/../logs';
        }
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0775, true);
        }
        return self::$logDir;
    }

    private static function getRequestId(): string
    {
        if (self::$reqId !== null) return self::$reqId;
        try {
            self::$reqId = substr(bin2hex(random_bytes(4)), 0, 8);
        } catch (Throwable $e) {
            self::$reqId = substr(md5((string)microtime(true) . (string)getmypid()), 0, 8);
        }
        return self::$reqId;
    }

    private static function resolveSource(): string
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);
        foreach ($bt as $frame) {
            $cls = $frame['class'] ?? '';
            $fn  = $frame['function'] ?? '';
            if ($cls === self::class) continue;
            if ($fn === 'seo_log' || $fn === 'logMessage') continue;
            $short = $cls !== '' ? self::shortClass($cls) . '::' . $fn : $fn;
            return $short !== '' ? $short : 'global';
        }
        return 'global';
    }

    private static function shortClass(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private static function formatContext(array $context): string
    {
        $json = json_encode(self::sanitize($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) return '{}';
        if (strlen($json) > 4000) {
            $json = substr($json, 0, 4000) . '…(truncated)';
        }
        return $json;
    }

    private static function sanitize($value, int $depth = 0)
    {
        if ($depth > 4) return '…';
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && preg_match('/(api[_-]?key|token|secret|password|authorization)/i', $k)) {
                    $out[$k] = '***';
                    continue;
                }
                $out[$k] = self::sanitize($v, $depth + 1);
            }
            return $out;
        }
        if (is_object($value)) {
            if ($value instanceof Throwable) {
                return [
                    'class'   => get_class($value),
                    'message' => $value->getMessage(),
                    'file'    => $value->getFile() . ':' . $value->getLine(),
                ];
            }
            return method_exists($value, '__toString') ? (string)$value : get_class($value);
        }
        if (is_string($value) && strlen($value) > 1000) {
            return substr($value, 0, 1000) . '…';
        }
        return $value;
    }
}
