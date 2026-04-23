<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

if (!isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

header('Location: /admin_simple/articles.php');
