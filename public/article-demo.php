<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

use Seo\Database;
use Seo\Service\HtmlRenderer\AssetCollector;
use Seo\Service\HtmlRenderer\ThemeService;
use Seo\Service\HtmlRenderer\Block\HeroBlockRenderer;
use Seo\Service\HtmlRenderer\Block\StatsBlockRenderer;
use Seo\Service\HtmlRenderer\Block\FeatureGridBlockRenderer;
use Seo\Service\HtmlRenderer\Block\InfoCardsBlockRenderer;
use Seo\Service\HtmlRenderer\Block\TimelineBlockRenderer;
use Seo\Service\HtmlRenderer\Block\GaugeChartBlockRenderer;
use Seo\Service\HtmlRenderer\Block\FunnelBlockRenderer;
use Seo\Service\HtmlRenderer\Block\KeyTakeawaysBlockRenderer;
use Seo\Service\HtmlRenderer\Block\FaqBlockRenderer;
use Seo\Service\HtmlRenderer\Block\WarningBlockRenderer;

$themeCode = preg_replace('/[^a-z0-9_-]/i', '', $_GET['theme'] ?? 'default') ?: 'default';

$db = Database::getInstance();
$themeService = new ThemeService($db);
$resolved = $themeService->resolveForArticle(['theme_code' => $themeCode], null);
$themeVarsCss = $themeService->renderCssVars($resolved['tokens']);

$assets = new AssetCollector();

$blocks = [
    [new HeroBlockRenderer($db), [
        'title' => 'Демо темы: ' . $resolved['code'],
        'subtitle' => 'Все блоки используют CSS-токены, заданные темой',
        'cta_text' => 'Действие',
    ]],
    [new KeyTakeawaysBlockRenderer($db), [
        'title' => 'Ключевые тезисы',
        'items' => [
            ['text' => 'Цвета берутся из --color-* переменных'],
            ['text' => 'Шрифты — из --type-font-*'],
            ['text' => 'Радиусы — из --radius-md/lg'],
        ],
    ]],
    [new StatsBlockRenderer($db), [
        'title' => 'Цифры',
        'items' => [
            ['value' => '95', 'suffix' => '%', 'label' => 'удовлетворённость'],
            ['value' => '12k', 'label' => 'пользователей'],
            ['value' => '4.8', 'label' => 'рейтинг'],
            ['value' => '24/7', 'label' => 'поддержка'],
        ],
    ]],
    [new FeatureGridBlockRenderer($db), [
        'title' => 'Особенности',
        'items' => [
            ['icon' => '⚡', 'title' => 'Быстро', 'description' => 'Генерация занимает секунды'],
            ['icon' => '🎨', 'title' => 'Темы', 'description' => 'Меняй оформление через JSON'],
            ['icon' => '🔒', 'title' => 'Надёжно', 'description' => 'Tokens-only design system'],
        ],
    ]],
    [new InfoCardsBlockRenderer($db), [
        'title' => 'Факты',
        'layout' => 'grid-3',
        'items' => [
            ['icon' => '📊', 'title' => 'Аналитика', 'text' => 'Подробные метрики'],
            ['icon' => '🚀', 'title' => 'Скорость', 'text' => 'Оптимизация SEO'],
            ['icon' => '🛠️', 'title' => 'Гибкость', 'text' => 'Кастомизация всех элементов'],
        ],
    ]],
    [new TimelineBlockRenderer($db), [
        'title' => 'Этапы',
        'items' => [
            ['title' => 'Начало', 'summary' => 'Анализ требований', 'detail' => 'Полный аудит проекта', 'meta' => '1 день'],
            ['title' => 'Дизайн', 'summary' => 'Подбор темы и токенов', 'detail' => 'Прототип, превью', 'meta' => '2 дня'],
            ['title' => 'Реализация', 'summary' => 'Генерация контента', 'detail' => 'Сборка статьи', 'meta' => '3 дня'],
        ],
    ]],
    [new GaugeChartBlockRenderer($db), [
        'title' => 'Показатели',
        'items' => [
            ['name' => 'CPU', 'value' => 72, 'min' => 0, 'max' => 100, 'unit' => '%'],
            ['name' => 'RAM', 'value' => 48, 'min' => 0, 'max' => 100, 'unit' => '%'],
            ['name' => 'Disk', 'value' => 86, 'min' => 0, 'max' => 100, 'unit' => '%'],
        ],
    ]],
    [new FunnelBlockRenderer($db), [
        'title' => 'Воронка конверсии',
        'items' => [
            ['label' => 'Визиты', 'value' => 10000],
            ['label' => 'Просмотры', 'value' => 4500],
            ['label' => 'Заявки', 'value' => 850],
            ['label' => 'Покупки', 'value' => 210],
        ],
    ]],
    [new WarningBlockRenderer($db), [
        'variant' => 'caution',
        'title' => 'Внимание',
        'subtitle' => 'Демо-страница',
        'items' => [
            ['severity' => 'warning', 'text' => 'Изменения в БД не сохраняются'],
        ],
    ]],
    [new FaqBlockRenderer($db), [
        'title' => 'Вопросы',
        'items' => [
            ['question' => 'Как сменить тему?', 'answer' => 'В админке /admin_advanced/seo_themes_page.php'],
            ['question' => 'Можно ли создать свою тему?', 'answer' => 'Да, через JSON-редактор в админке'],
        ],
    ]],
];

$bodyHtml = '';
foreach ($blocks as $i => $pair) {
    [$renderer, $content] = $pair;
    $bodyHtml .= $renderer->renderHtml($content, 'demo-' . $i);
    $assets->addBlock($renderer);
}

$themeVarsTag = '<style id="theme-vars">' . $themeVarsCss . '</style>';

$h = '<!DOCTYPE html><html lang="ru"><head>'
    . '<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">'
    . '<title>Demo темы: ' . htmlspecialchars($resolved['code'], ENT_QUOTES) . '</title>'
    . '<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;700;900&family=Geologica:wght@400;500;700;900&family=Source+Serif+Pro:wght@400;700&family=Playfair+Display:wght@700&family=JetBrains+Mono&family=Space+Grotesk:wght@400;700&display=swap" rel="stylesheet">'
    . $themeVarsTag
    . $assets->buildStyleTag()
    . '<style>body{font-family:var(--type-font-text);background:var(--color-bg);color:var(--color-text);margin:0}.container{max-width:1100px;margin:0 auto;padding:0 24px}.demo-bar{background:var(--color-surface);border-bottom:1px solid var(--color-border);padding:14px 24px;display:flex;gap:12px;align-items:center;font-family:var(--type-font-heading)}.demo-bar a{color:var(--color-accent);text-decoration:none}</style>'
    . '</head><body>'
    . '<div class="demo-bar"><strong>Demo темы:</strong> ' . htmlspecialchars($resolved['code'], ENT_QUOTES) . ' <a href="/admin_advanced/seo_themes_page.php">→ редактор</a></div>'
    . '<main>' . $bodyHtml . '</main>'
    . $assets->buildScriptTag()
    . '</body></html>';

header('Content-Type: text/html; charset=utf-8');
echo $h;
