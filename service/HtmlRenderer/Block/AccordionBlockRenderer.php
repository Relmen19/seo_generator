<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class AccordionBlockRenderer extends AbstractBlockRenderer
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

        $h = '<section id="' . $id . '" class="block-accordion reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($title !== '' ? $title : 'Подробности') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . ($title !== '' ? '<h2 class="sec-title">' . $title . '</h2>' : '');
        foreach ($c['items'] ?? [] as $i => $it) {
            $itContent = $it['content'] ?? $it['answer'] ?? $it['text'] ?? '';
            if (is_array($itContent)) {
                $parts = [];
                foreach ($itContent as $p) {
                    if (is_string($p)) $parts[] = $p;
                    elseif (is_array($p) && isset($p['text'])) $parts[] = (string)$p['text'];
                }
                $itContent = implode("\n\n", $parts);
            }
            $h .= '<details' . ($i === 0 ? ' open' : '') . '>'
                . '<summary>' . $this->e($it['title'] ?? $it['question'] ?? '') . '</summary>'
                . '<div class="acc-body">' . $this->e((string)$itContent) . '</div>'
                . '</details>';
        }
        return $h . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-accordion { }'
            . "\n" . 'details { border:1px solid var(--color-border); border-radius:var(--radius-md); margin-bottom:10px; overflow:hidden; background:var(--color-surface); transition:all .25s }'
            . "\n" . 'details[open],details:hover { box-shadow:0 4px 20px rgba(0,0,0,.04); border-color:var(--color-accent) }'
            . "\n" . 'summary { padding:18px 22px; font-family:var(--type-font-heading); font-weight:700; font-size:.95rem; cursor:pointer; background:transparent; list-style:none; display:flex; align-items:center; gap:10px; color:var(--color-text); transition:background .2s }'
            . "\n" . 'details[open] summary { background:var(--color-accent-soft) }'
            . "\n" . 'summary::-webkit-details-marker { display:none }'
            . "\n" . 'summary::before { content:""; flex-shrink:0; width:18px; height:18px; border-radius:50%; background:var(--color-accent); transition:transform .25s }'
            . "\n" . 'details[open] summary::before { transform:rotate(90deg) }'
            . "\n" . '.acc-body { padding:18px 22px; border-top:1px solid var(--color-border); color:var(--color-text-2); line-height:1.65; font-size:.95rem }';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Подробности';
    }
}
