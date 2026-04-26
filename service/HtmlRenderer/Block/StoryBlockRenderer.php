<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class StoryBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $variant = $c['variant'] ?? 'patient_story';
        $icon    = $this->e($c['icon'] ?? '💬');
        $accent  = $this->e($c['accent_color'] ?? '#8B5CF6');
        $lead    = $this->e($c['lead'] ?? '');
        $text    = $this->e($c['text'] ?? '');
        $hl      = $this->e($c['highlight'] ?? '');
        $fn      = $this->e($c['footnote'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-story reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($lead ?: 'История') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="sb-card sb-card--' . $this->e($variant) . '" style="--sb-c:' . $accent . '">'
            . '<div class="sb-accent"></div>'
            . '<div class="sb-body">'
            . '<div class="sb-icon">' . $icon . '</div>'
            . ($lead ? '<div class="sb-lead">' . $lead . '</div>' : '')
            . '<div class="sb-text">' . $text . '</div>'
            . ($hl ? '<div class="sb-highlight">' . $hl . '</div>' : '')
            . ($fn ? '<div class="sb-footnote">' . $fn . '</div>' : '')
            . '</div></div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.sb-card{border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:1px solid var(--color-border);overflow:hidden;display:flex}'
            . "\n" . '[data-theme="dark"] .sb-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.sb-accent{width:6px;flex-shrink:0;background:var(--sb-c,var(--purple))}'
            . "\n" . '.sb-body{padding:28px 32px;flex:1}'
            . "\n" . '.sb-icon{font-size:1.5rem;margin-bottom:8px}'
            . "\n" . '.sb-lead{font-family:var(--type-font-heading);font-size:13px;font-weight:700;color:var(--sb-c,var(--purple));text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}'
            . "\n" . '.sb-text{font-size:15px;color:var(--color-text-2);line-height:1.7;font-style:italic}'
            . "\n" . '.sb-highlight{margin-top:14px;padding:12px 16px;border-radius:10px;background:var(--color-accent-soft);font-family:var(--type-font-heading);font-size:14px;font-weight:700;color:var(--color-text)}'
            . "\n" . '.sb-footnote{margin-top:12px;font-size:11px;color:var(--color-text-3);font-style:italic}'
            . "\n" . '@media(max-width:700px){.sb-body{padding:20px}}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['lead'] ?? 'История';
    }
}
