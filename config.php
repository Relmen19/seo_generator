<?php

declare(strict_types=1);

const SEO_ROOT  = __DIR__;
const SEO_DEBUG = false;

// ── Parent app integration (optional) ────────────────────────────────────────
// When deployed inside a parent admin app, pull shared config/database.
// In standalone Docker mode these files don't exist — we define everything below.
$_conf = is_dir(__DIR__ . '/../../config') ? __DIR__ . '/../../config' : __DIR__ . '/../../../config';
$_hasParent = is_file($_conf . '/config.php');

if ($_hasParent) {
    require_once $_conf . '/config.php';
    require_once $_conf . '/database.php';
}
unset($_conf, $_hasParent);

// ── Constants that the parent app normally provides ──────────────────────────
if (!defined('APP_URL')) {
    define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'));
}
if (!defined('AI_REQUEST_TIMEOUT')) {
    define('AI_REQUEST_TIMEOUT', (int)(getenv('AI_REQUEST_TIMEOUT') ?: 120));
}

// ── GPT / AI ──────────────────────────────────────────────────────────────────
define('GPT_API_KEY',       getenv('GPT_API_KEY')       ?: getenv('OPENAI_API_KEY') ?: '');
define('GPT_DEFAULT_MODEL', getenv('GPT_DEFAULT_MODEL') ?: 'gpt-4o');
define('GOOGLE_API_KEY',    getenv('GOOGLE_API_KEY')    ?: '');
const GPT_TIMEOUT = AI_REQUEST_TIMEOUT;

// SEO-specific AI tuning (differs from main AI_TEMPERATURE / MAX_TOKENS)
const SEO_TEMPERATURE_CREATIVE = 0.8;
const SEO_TEMPERATURE_PRECISE = 0.2;
const SEO_TEMPERATURE_IMAGE = 0.7;
const SEO_MAX_TOKENS_LARGE = 8000;
const SEO_MAX_TOKENS_SMALL = 3000;
const SEO_MAX_TOKENS_IMG_PROMPT = 300;
const SEO_PUBLISH_TIMEOUT = 30;
const SEO_PUBLISH_CONNECT_TIMEOUT = 10;
define('SEO_IMAGE_PROMPT_MODEL', getenv('SEO_IMAGE_PROMPT_MODEL') ?: 'gpt-4o-mini');

// ── Puppeteer (block screenshot service) ──────────────────────────────────────
define('PUPPETEER_SERVICE_URL', getenv('PUPPETEER_SERVICE_URL') ?: 'http://puppeteer:3000');

// ── Publishing ────────────────────────────────────────────────────────────────
define('PUBLISH_SECRET', getenv('PUBLISH_SECRET') ?: '');

// ── URLs / paths ──────────────────────────────────────────────────────────────
const BASE_URL = APP_URL;
define('UPLOADS_DIR', rtrim(getenv('APP_ROOT') ?: __DIR__, '/') . '/' . ltrim(getenv('UPLOADS_DIR') ?: 'uploads', '/') . '/');

define('SEO_BASE_ART_URL', APP_URL . '/' . ltrim(getenv('SEO_ARTICLES_PATH') ?: 'articles', '/') . '/');
define('SEO_SEARCH_SCRIPT', SEO_BASE_ART_URL . 'search.php');
const SEO_TRACK_SCRIPT = APP_URL . '/admin/seo_generator/controllers/track.php';
define('SEO_DEFAULT_LOGO_URL', getenv('SEO_LOGO_URL') ?: (APP_URL . '/api/logo.png'));

// ── CORS (HTTP only) ─────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    $_allowedOrigins = array_filter([
        getenv('APP_URL') ?: 'http://localhost:8080',
        getenv('CORS_EXTRA_ORIGIN') ?: '',
    ]);

    $_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($_origin, $_allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $_origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    unset($_allowedOrigins, $_origin);

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Autoloader (Seo\* namespace) ──────────────────────────────────────────────
spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'Seo\\') !== 0) {
        return;
    }

    $relative = substr($class, 4);
    $parts    = explode('\\', $relative);

    $map = [
        'Entity'     => SEO_ROOT . '/entity',
        'Controller' => SEO_ROOT . '/controllers',
        'Service'    => SEO_ROOT . '/service',
        'Enum'       => SEO_ROOT . '/enum',
    ];

    $firstPart = $parts[0] ?? '';

    if (isset($map[$firstPart])) {
        $dir = $map[$firstPart];
        array_shift($parts);
        $file = $dir . '/' . implode('/', $parts) . '.php';
    } else {
        $file = SEO_ROOT . '/' . implode('/', $parts) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once SEO_ROOT . '/Database.php';

function logMessage(string $message, string $level = 'ERROR'): void {
    $logFile   = SEO_ROOT . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] [{$level}] {$message}\n", FILE_APPEND | LOCK_EX);
}
