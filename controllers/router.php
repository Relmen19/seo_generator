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

if ($route === '') {
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

    $controller = new $controllerClass();
    $controller->dispatch($method, $action, $id);
} catch (PDOException $e) {
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
