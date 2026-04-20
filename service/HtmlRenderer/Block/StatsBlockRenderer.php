<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class StatsBlockRenderer extends AbstractBlockRenderer
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
        $title = $this->e($c['title'] ?? '');

        $h = '<section id="' . $id . '" class="block-stats reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($title !== '' ? $title : 'Показатели') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . ($title !== '' ? '<h2 class="sec-title">' . $title . '</h2>' : '')
            . '<div class="stats-grid">';
        foreach ($c['items'] ?? [] as $it) {
            $h .= '<div class="stat-card">'
                . '<div class="stat-value">'
                . $this->e((string)($it['value'] ?? ''))
                . $this->e($it['suffix'] ?? '')
                . '</div>'
                . '<div class="stat-label">' . $this->e($it['label'] ?? '') . '</div>'
                . '</div>';
        }
        return $h . '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-stats { padding:60px 0 }'
            . "\n" . '.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; text-align:center }'
            . "\n" . '.stat-card { padding:28px 16px; border-radius:var(--r); background:rgba(255,255,255,.6); backdrop-filter:blur(8px); border:1px solid var(--border); transition:all .25s }'
            . "\n" . '[data-theme="dark"] .stat-card { background:rgba(255,255,255,.04) }'
            . "\n" . '.stat-card:hover { box-shadow:0 8px 28px rgba(37,99,235,.1); transform:translateY(-4px); border-color:rgba(37,99,235,.3) }'
            . "\n" . '.stat-value { font-family:var(--fh); font-size:2.2rem; font-weight:900; color:var(--blue); letter-spacing:-1px; line-height:1 }'
            . "\n" . '.stat-label { font-size:.85rem; color:var(--muted); margin-top:8px }'
            . "\n" . '@media(max-width:768px) {'
            .     '.stats-grid { grid-template-columns:repeat(2,1fr) }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .     '.stat-value { font-size:1.8rem }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Показатели';
    }
}
