<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class FeatureGridBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-features reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="Особенности">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="features-grid">';
        foreach (array_values($c['items'] ?? $c['features'] ?? []) as $i => $it) {
            $num = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
            $h .= '<div class="feature-card">'
                . '<div class="feature-icon">' . $this->e($it['icon'] ?? '📋') . '</div>'
                . '<div class="feature-num">' . $num . '</div>'
                . '<h4>' . $this->e($it['title'] ?? '') . '</h4>'
                . '<p>' . $this->e($it['description'] ?? $it['text'] ?? '') . '</p>'
                . '</div>';
        }
        return $h . '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-features { }'
            . "\n" . '.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:16px }'
            . "\n" . '.feature-card { background:rgba(255,255,255,.55); backdrop-filter:blur(10px); border:1px solid var(--border); border-radius:var(--r); padding:28px; transition:all .25s; position:relative; overflow:hidden }'
            . "\n" . '[data-theme="dark"] .feature-card { background:rgba(255,255,255,.04) }'
            . "\n" . '.feature-card::before { content:""; position:absolute; inset:0; background:linear-gradient(135deg,rgba(37,99,235,.06) 0%,transparent 60%); opacity:0; transition:opacity .25s }'
            . "\n" . '.feature-card:hover { border-color:rgba(37,99,235,.3); box-shadow:0 8px 28px rgba(37,99,235,.08); transform:translateY(-4px) }'
            . "\n" . '.feature-card:hover::before { opacity:1 }'
            . "\n" . '.feature-icon { font-size:2rem; margin-bottom:16px; display:block }'
            . "\n" . '.feature-num { font-family:var(--fh); font-size:22px; font-weight:900; color:var(--blue); opacity:.15; position:absolute; top:16px; right:20px; line-height:1 }'
            . "\n" . '.feature-card h4 { position:relative; color:var(--dark); margin:0 0 8px }'
            . "\n" . '.feature-card p { position:relative; font-size:13px; color:var(--muted); line-height:1.6; margin-bottom:0 }'
            . "\n" . '@media(max-width:768px) {'
            .     '.features-grid { grid-template-columns:1fr }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return 'Особенности';
    }
}
