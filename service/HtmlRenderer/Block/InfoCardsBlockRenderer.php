<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class InfoCardsBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title  = $this->e($c['title'] ?? '');
        $layout_type = $c['layout'] ?? 'grid-3';
        $items  = $c['items'] ?? [];
        $cols   = $layout_type === 'grid-2' ? 'ic-grid--2' : 'ic-grid--3';

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '"' : '';

        $h = '<section id="' . $id . '" class="block-icards reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($title ?: 'Факты') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . ($title ? '<h2 class="sec-title">' . $title . '</h2>' : '')
            . '<div class="ic-grid ' . $cols . '">';
        foreach ($items as $i => $it) {
            $color = $this->e($it['color'] ?? 'var(--color-accent)');
            $icon  = $this->e($it['icon'] ?? '📋');
            $h .= '<div class="ic-card" style="--ic-c:' . $color . '">'
                . '<div class="ic-icon">' . $icon . '</div>'
                . '<div class="ic-title">' . $this->e($it['title'] ?? '') . '</div>'
                . '<div class="ic-text">' . $this->e($it['text'] ?? '') . '</div>'
                . '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.ic-grid{display:grid;gap:16px}'
            . "\n" . '.ic-grid--3{grid-template-columns:repeat(3,1fr)}'
            . "\n" . '.ic-grid--2{grid-template-columns:repeat(2,1fr)}'
            . "\n" . '.ic-card{padding:24px 20px;border-radius:var(--radius-lg);background:var(--color-surface);border:1px solid var(--color-border);border-left:4px solid var(--ic-c,var(--color-accent));transition:all .3s;cursor:default}'
            . "\n" . '.ic-card:hover{transform:translateY(-4px);box-shadow:0 12px 40px rgba(0,0,0,.06);border-color:var(--color-accent)}'
            . "\n" . '.ic-icon{font-size:1.8rem;margin-bottom:10px}'
            . "\n" . '.ic-title{font-family:var(--type-font-heading);font-size:15px;font-weight:700;color:var(--color-text);margin-bottom:6px}'
            . "\n" . '.ic-text{font-size:13px;color:var(--color-text-2);line-height:1.55}'
            . "\n" . '@media(max-width:700px){.ic-grid--3{grid-template-columns:1fr 1fr}.ic-grid--2{grid-template-columns:1fr}}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Факты';
    }
}
