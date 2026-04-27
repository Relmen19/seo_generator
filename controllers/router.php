<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

requireAuthApi();

use Seo\Controller\CatalogController;
use Seo\Controller\IntentController;
use Seo\Controller\KeywordController;
use Seo\Controller\PublishController;
use Seo\Controller\TemplateController;
use Seo\Controller\ArticleController;
use Seo\Controller\ImageController;
use Seo\Controller\IllustrationController;
use Seo\Controller\LinkConstantController;
use Seo\Controller\StatsController;
use Seo\Controller\AuditLogController;
use Seo\Controller\PublishTargetController;
use Seo\Controller\GenerationController;
use Seo\Controller\BlockTypeController;
use Seo\Controller\SiteProfileController;
use Seo\Controller\TelegramController;
use Seo\Controller\ThemeController;
use Seo\Controller\ArticleQaController;
use Seo\Service\Logger;

$_seo_t0 = microtime(true);

$routes = [
    'catalogs'        => CatalogController::class,
    'templates'       => TemplateController::class,
    'articles'        => ArticleController::class,
    'images'          => ImageController::class,
    'illustrations'   => IllustrationController::class,
    'links'           => LinkConstantController::class,
    'stats'           => StatsController::class,
    'audit-log'       => AuditLogController::class,
    'publish-targets' => PublishTargetController::class,
    'generate'        => GenerationController::class,
    'publish'         => PublishController::class,
    'keywords'        => KeywordController::class,
    'intents'         => IntentController::class,
    'block-types'     => BlockTypeController::class,
    'profiles'        => SiteProfileController::class,
    'telegram'        => TelegramController::class,
    'themes'          => ThemeController::class,
    'qa'              => ArticleQaController::class,
];


$method = strtoupper($_SERVER['REQUEST_METHOD']);
$route  = trim($_GET['r'] ?? '', '/');


if (strpos($route, '?') !== false) {
    $parts = explode('?', $route, 2);
    $route = $parts[0];

    if (isset($parts[1])) {
        parse_str($parts[1], $additionalParams);
        $_GET = array_merge($_GET, $additionalParams);
    }
}
$_GET['r'] = $route;

Logger::info(Logger::CHANNEL_ROUTER, "{$method} /{$route}", [
    'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
    'query' => $_GET,
]);

if ($route === '') {
    Logger::warn(Logger::CHANNEL_ROUTER, 'Empty route', ['method' => $method]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'code' => 404,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$segments = explode('/', $route);
$resource = $segments[0] ?? '';

$id = null;
$action = null;

if (isset($segments[1])) {
    if (ctype_digit($segments[1])) {
        $id = (int)$segments[1];
        $action = $segments[2] ?? null;
    } else {
        $action = $segments[1];
        if (isset($segments[2]) && ctype_digit($segments[2])) {
            $id = (int)$segments[2];
        }
    }
}

if (!isset($routes[$resource])) {
    Logger::warn(Logger::CHANNEL_ROUTER, "Unknown resource '{$resource}'", [
        'available' => array_keys($routes),
    ]);
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => "Ресурс '{$resource}' не найден",
        'available' => array_keys($routes),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $controllerClass = $routes[$resource];

    Logger::debug(Logger::CHANNEL_ROUTER, 'Dispatch', [
        'controller' => $controllerClass,
        'action'     => $action,
        'id'         => $id,
    ]);

    $controller = new $controllerClass();
    $controller->dispatch($method, $action, $id);

    Logger::info(Logger::CHANNEL_ROUTER, "Done {$method} /{$route}", [
        'ms' => (int)((microtime(true) - $_seo_t0) * 1000),
    ]);
} catch (PDOException $e) {
    Logger::error(Logger::CHANNEL_DB, 'PDOException in router', [
        'route'   => $route,
        'message' => $e->getMessage(),
        'code'    => $e->getCode(),
        'file'    => $e->getFile() . ':' . $e->getLine(),
    ]);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'error' => 'Ошибка базы данных'];
    if (SEO_DEBUG) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    Logger::error(Logger::CHANNEL_ROUTER, 'Unhandled exception', [
        'route'   => $route,
        'class'   => get_class($e),
        'message' => $e->getMessage(),
        'file'    => $e->getFile() . ':' . $e->getLine(),
        'trace'   => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'error' => 'Внутренняя ошибка сервера'];
    if (SEO_DEBUG) {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
