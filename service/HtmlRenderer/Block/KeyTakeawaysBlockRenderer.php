<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class KeyTakeawaysBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Главное');
        $items = $c['items'] ?? [];
        $style = $c['style'] ?? 'numbered';

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-takeaways reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="kt-card">'
            . '<div class="kt-header"><span class="kt-icon">⚡</span><span class="kt-title">' . $title . '</span></div>'
            . '<div class="kt-items kt-items--' . $this->e($style) . '">';
        foreach ($items as $i => $it) {
            $text = is_string($it) ? $it : ($it['text'] ?? '');
            $h .= '<div class="kt-item">'
                . ($style === 'numbered' ? '<span class="kt-num">' . ($i + 1) . '</span>' : '<span class="kt-bullet"></span>')
                . '<span class="kt-text">' . $this->e($text) . '</span>'
                . '</div>';
        }
        $h .= '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.kt-card{border-radius:var(--radius-lg);background:var(--color-accent-soft);border:1px solid var(--color-border);padding:28px 32px}'
            . "\n" . '.kt-header{display:flex;align-items:center;gap:10px;margin-bottom:18px}'
            . "\n" . '.kt-icon{font-size:1.5rem}'
            . "\n" . '.kt-title{font-family:var(--type-font-heading);font-size:18px;font-weight:900;color:var(--color-text)}'
            . "\n" . '.kt-items{display:grid;gap:10px}'
            . "\n" . '.kt-item{display:flex;align-items:flex-start;gap:12px;font-size:14px;color:var(--color-text-2);line-height:1.6}'
            . "\n" . '.kt-num{width:28px;height:28px;border-radius:var(--radius-sm);background:var(--color-accent);color:#fff;font-family:var(--type-font-heading);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}'
            . "\n" . '.kt-bullet{width:8px;height:8px;border-radius:50%;background:var(--color-accent);flex-shrink:0;margin-top:7px}'
            . "\n" . '.kt-text{flex:1}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Главное';
    }
}
