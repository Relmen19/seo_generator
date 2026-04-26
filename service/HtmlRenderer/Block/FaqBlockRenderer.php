<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class FaqBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $items   = $c['items'] ?? [];
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $schema  = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
        $title   = trim((string)($c['title'] ?? ''));
        $h2Text  = $this->e($title !== '' ? $title : 'Часто задаваемые вопросы');
        $tocText = $this->e($title !== '' ? $title : 'FAQ');

        $h = '<section id="' . $id . '" class="block-faq reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $tocText . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $h2Text . '</h2>'
            . '<div class="faq-list">';

        foreach ($items as $it) {
            $answerRaw = $it['answer'] ?? $it['text'] ?? '';
            if (is_array($answerRaw)) {
                $parts = [];
                foreach ($answerRaw as $p) {
                    if (is_string($p)) $parts[] = $p;
                    elseif (is_array($p) && isset($p['text'])) $parts[] = (string)$p['text'];
                }
                $answerRaw = implode("\n\n", $parts);
            }
            $q = $this->e((string)($it['question'] ?? ''));
            $a = $this->e((string)$answerRaw);
            $h .= '<div class="faq-item">'
                . '<button class="faq-q">' . $q . '<span class="faq-arr">+</span></button>'
                . '<div class="faq-a"><div class="faq-a-in">' . $a . '</div></div>'
                . '</div>';
            $schema['mainEntity'][] = [
                '@type'          => 'Question',
                'name'           => (string)($it['question'] ?? ''),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string)$answerRaw],
            ];
        }
        $h .= '</div><div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        if (!empty($schema['mainEntity'])) {
            $h .= '<script type="application/ld+json">'
                . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . '</script>' . "\n";
        }
        return $h;
    }

    public function getCss(): string
    {
        return '.block-faq { }'
            . "\n" . '.faq-list { display:flex; flex-direction:column; gap:10px; max-width:780px }'
            . "\n" . '.faq-item { border:1px solid var(--color-border); border-radius:var(--radius-md); overflow:hidden; transition:border-color .25s; background:var(--color-surface) }'
            . "\n" . '.faq-item:hover,.faq-item.open { border-color:var(--color-accent) }'
            . "\n" . '.faq-q { width:100%; background:transparent; color:var(--color-text); font-family:var(--type-font-text); font-size:15px; font-weight:500; padding:18px 22px; text-align:left; border:none; cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:16px; transition:background .2s }'
            . "\n" . '.faq-q:hover { background:var(--color-accent-soft) }'
            . "\n" . '.faq-arr { font-size:20px; color:var(--color-accent); transition:transform .3s; flex-shrink:0; font-weight:300 }'
            . "\n" . '.faq-a { max-height:0; overflow:hidden; transition:max-height .4s ease }'
            . "\n" . '.faq-a-in { padding:18px 22px; border-top:1px solid var(--color-border); color:var(--color-text-2); line-height:1.65; font-size:.95rem }'
            . "\n" . '.faq-item.open .faq-arr { transform:rotate(45deg) }'
            . "\n" . '.faq-item.open .faq-a { max-height:600px }'
            . "\n" . '.faq-item.open .faq-q { background:var(--color-accent-soft) }';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'document.querySelectorAll(".faq-q").forEach(function(btn){'
            . 'btn.addEventListener("click",function(){'
            . 'var item=btn.closest(".faq-item"),open=item.classList.contains("open");'
            . 'document.querySelectorAll(".faq-item.open").forEach(function(i){i.classList.remove("open")});'
            . 'if(!open)item.classList.add("open")'
            . '})});'
            . '})();';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'FAQ';
    }
}
