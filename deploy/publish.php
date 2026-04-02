<?php

require_once __DIR__ . "/../config.php";

$headers = getallheaders();
$secret = $headers['X-Publish-Secret'] ?? $_SERVER['HTTP_X_PUBLISH_SECRET'] ?? '';

if ($secret !== PUBLISH_SECRET) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid secret']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? 'publish';

if ($action === 'delete') {
    if (!isset($input['path'])) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'path is required for delete']);
        exit;
    }

    $path = $input['path'];
    $type = 'articles';
    if (strpos($path, 'pages/') === 0) {
        $type = 'pages';
        $path = substr($path, 6);
    } elseif (strpos($path, 'articles/') === 0) {
        $path = substr($path, 9);
    }

    $path = str_replace(['..', './', '\\'], '', $path);
    $path = ltrim($path, '/');

    $fullPath = UPLOADS_DIR . $type . '/' . $path;

    if (file_exists($fullPath)) {
        unlink($fullPath);

        $dir = dirname($fullPath);
        while ($dir !== UPLOADS_DIR . $type && is_dir($dir) && count(scandir($dir)) === 2) {
            rmdir($dir);
            $dir = dirname($dir);
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'deleted' => $path], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'File not found', 'path' => $path]);
    }
    exit;
}

if (!isset($input['path']) || !isset($input['content'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$path = $input['path'];
$content = $input['content'];

$type = 'articles';
if (strpos($path, 'pages/') === 0) {
    $type = 'pages';
    $path = substr($path, 6);
} elseif (strpos($path, 'articles/') === 0) {
    $path = substr($path, 9);
}


$path = str_replace(['..', './', '\\'], '', $path);
$path = ltrim($path, '/');

if (!preg_match('/\.html?$/i', $path)) {
    $path = $path . '.html';
}

$fullPath = UPLOADS_DIR . $type . '/' . $path;

$directory = dirname($fullPath);
if (!is_dir($directory)) {
    mkdir($directory, 0755, true);
}

if (file_put_contents($fullPath, $content) !== false) {
    chmod($fullPath, 0644);

    $url = rtrim(BASE_URL, '/') . '/uploads/' . $type . '/' . $path;

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'path' => $path,
        'type' => $type,
        'url' => $url
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Failed to save file']);
}