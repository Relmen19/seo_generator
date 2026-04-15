<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class VerdictCardBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Мифы и факты');
        $items = $c['items'] ?? [];
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE);

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-verdict reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="vd-grid" data-verdict="' . $id . '">';
        foreach ($items as $i => $it) {
            $v = $it['verdict'] ?? 'myth';
            $vLabel = $v === 'truth' ? 'Правда' : ($v === 'partial' ? 'Полуправда' : 'Миф');
            $vClass = 'vd-verdict--' . $this->e($v);
            $h .= '<div class="vd-card" data-idx="' . $i . '">'
                . '<div class="vd-stamp ' . $vClass . '">' . $this->e($vLabel) . '</div>'
                . '<div class="vd-claim">' . $this->e($it['claim'] ?? '') . '</div>'
                . '<div class="vd-expand"><div>'
                . '<div class="vd-explanation">' . $this->e($it['explanation'] ?? '') . '</div>'
                . (!empty($it['source']) ? '<div class="vd-source">📎 ' . $this->e($it['source']) . '</div>' : '')
                . '</div></div>'
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
        return '.vd-grid{display:grid;gap:16px}'
            . "\n" . '.vd-card{padding:24px;border-radius:16px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);border:1px solid var(--border);cursor:pointer;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .vd-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.vd-card:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(15,23,42,.06)}'
            . "\n" . '.vd-stamp{display:inline-block;padding:4px 14px;border-radius:100px;font-family:var(--fh);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px}'
            . "\n" . '.vd-verdict--myth{background:rgba(239,68,68,.12);color:#EF4444}'
            . "\n" . '.vd-verdict--truth{background:rgba(16,185,129,.12);color:#10B981}'
            . "\n" . '.vd-verdict--partial{background:rgba(245,158,11,.12);color:#F59E0B}'
            . "\n" . '.vd-claim{font-family:var(--fh);font-size:16px;font-weight:700;color:var(--dark);line-height:1.4}'
            . "\n" . '.vd-expand{display:grid;grid-template-rows:0fr;transition:grid-template-rows .4s ease;overflow:hidden}'
            . "\n" . '.vd-card.is-open .vd-expand{grid-template-rows:1fr}'
            . "\n" . '.vd-expand>div{overflow:hidden}'
            . "\n" . '.vd-explanation{padding-top:14px;font-size:14px;color:var(--slate);line-height:1.65;border-top:1px solid var(--border);margin-top:14px}'
            . "\n" . '.vd-source{font-size:11.5px;color:var(--muted);margin-top:8px;font-style:italic}'
            ;
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll("[data-verdict]").forEach(function(grid){'
            . 'grid.querySelectorAll(".vd-card").forEach(function(card){'
            . 'card.addEventListener("click",function(){card.classList.toggle("is-open")})})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Мифы и факты';
    }
}
