<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class NumberedStepsBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Пошаговый план');
        $steps = $c['steps'] ?? [];
        $colors = ['var(--color-accent)', 'var(--color-accent)', 'var(--purple)', 'var(--color-success)', 'var(--orange)',
            'var(--pink)', 'var(--color-warn)', 'var(--color-danger)'];

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-nsteps reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="ns-track">';
        foreach ($steps as $i => $st) {
            $color = $colors[$i % count($colors)];
            $h .= '<div class="ns-step" style="--ns-c:' . $color . '">'
                . '<div class="ns-num">' . (int)($st['number'] ?? $i + 1) . '</div>'
                . '<div class="ns-card">'
                . '<div class="ns-title">' . $this->e($st['title'] ?? '') . '</div>'
                . '<div class="ns-text">' . $this->e($st['text'] ?? '') . '</div>'
                . (!empty($st['tip']) ? '<div class="ns-tip">💡 ' . $this->e($st['tip']) . '</div>' : '')
                . (!empty($st['duration']) ? '<div class="ns-duration">⏱ ' . $this->e($st['duration']) . '</div>' : '')
                . '</div></div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.ns-track{display:grid;gap:20px}'
            . "\n" . '.ns-step{display:grid;grid-template-columns:56px 1fr;gap:16px;align-items:start}'
            . "\n" . '.ns-num{width:56px;height:56px;border-radius:16px;background:var(--ns-c,var(--color-accent));color:#fff;font-family:var(--type-font-heading);font-size:1.5rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 16px rgba(37,99,235,.2)}'
            . "\n" . '.ns-card{padding:20px 24px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1px solid var(--color-border);transition:all .3s}'
            . "\n" . '[data-theme="dark"] .ns-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.ns-title{font-family:var(--type-font-heading);font-size:16px;font-weight:700;color:var(--color-text);margin-bottom:6px}'
            . "\n" . '.ns-text{font-size:14px;color:var(--color-text-2);line-height:1.6}'
            . "\n" . '.ns-tip{margin-top:10px;padding:10px 14px;border-radius:10px;background:var(--color-accent-soft);font-size:13px;color:var(--color-text-2);line-height:1.5}'
            . "\n" . '.ns-duration{margin-top:8px;font-family:var(--type-font-heading);font-size:12px;font-weight:600;color:var(--color-text-3)}'
            . "\n" . '@media(max-width:700px){.ns-step{grid-template-columns:44px 1fr;gap:10px}.ns-num{width:44px;height:44px;font-size:1.2rem}}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Пошаговый план';
    }
}
