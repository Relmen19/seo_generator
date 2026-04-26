<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ExpertPanelBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $name   = $this->e($c['name'] ?? '');
        $creds  = $this->e($c['credentials'] ?? '');
        $exp    = $this->e($c['experience'] ?? '');
        $ph     = $this->e($c['photo_placeholder'] ?? '?');
        $text   = $this->e($c['text'] ?? '');
        $hl     = $this->e($c['highlight'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-expert reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($name !== '' ? 'Мнение: ' . $name : 'Мнение эксперта') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="ep-card">'
            . '<div class="ep-quote-mark">"</div>'
            . '<div class="ep-body">'
            . '<div class="ep-text">' . $text . '</div>'
            . ($hl ? '<div class="ep-highlight">' . $hl . '</div>' : '')
            . '</div>'
            . '<div class="ep-author">'
            . '<div class="ep-avatar">' . $ph . '</div>'
            . '<div class="ep-meta">'
            . '<div class="ep-name">' . $name . '</div>'
            . '<div class="ep-creds">' . $creds . '</div>'
            . ($exp ? '<div class="ep-exp">' . $exp . '</div>' : '')
            . '</div></div>'
            . '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.ep-card{border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(14px);border:1px solid var(--color-border);padding:32px 36px;position:relative;overflow:hidden}'
            . "\n" . '[data-theme="dark"] .ep-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.ep-quote-mark{position:absolute;top:16px;right:28px;font-family:Georgia,serif;font-size:6rem;color:rgba(37,99,235,.08);line-height:1;pointer-events:none}'
            . "\n" . '[data-theme="dark"] .ep-quote-mark{color:rgba(96,165,250,.12)}'
            . "\n" . '.ep-text{font-size:16px;color:var(--color-text-2);line-height:1.75;font-style:italic;position:relative;z-index:1}'
            . "\n" . '.ep-highlight{margin-top:16px;padding:14px 18px;border-radius:12px;background:var(--color-accent-soft);font-family:var(--type-font-heading);font-size:15px;font-weight:700;color:var(--color-text);font-style:normal;border-left:4px solid var(--color-accent)}'
            . "\n" . '.ep-author{display:flex;align-items:center;gap:14px;margin-top:24px;padding-top:20px;border-top:1px solid var(--color-border)}'
            . "\n" . '.ep-avatar{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--color-accent),var(--color-accent));color:#fff;font-family:var(--type-font-heading);font-size:18px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}'
            . "\n" . '.ep-name{font-family:var(--type-font-heading);font-size:15px;font-weight:700;color:var(--color-text)}'
            . "\n" . '.ep-creds{font-size:13px;color:var(--color-text-3)}'
            . "\n" . '.ep-exp{font-size:11.5px;color:var(--color-text-3);opacity:.7}'
            . "\n" . '@media(max-width:700px){.ep-card{padding:24px}}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return ($content['name'] ?? '') ? 'Мнение: ' . ($content['name'] ?? '') : 'Мнение эксперта';
    }
}
