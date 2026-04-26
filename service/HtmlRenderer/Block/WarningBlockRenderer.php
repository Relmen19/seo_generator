<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class WarningBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $variant = $c['variant'] ?? 'red_flags';
        $title   = $this->e($c['title'] ?? 'Внимание');
        $sub     = $this->e($c['subtitle'] ?? '');
        $items   = $c['items'] ?? [];
        $footer  = $this->e($c['footer'] ?? '');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        switch ($variant) {
            case 'caution': $varClass = 'wb--caution'; break;
            case 'good_signs': $varClass = 'wb--good'; break;
            default: $varClass = 'wb--red';
        };

        $h = '<section id="' . $id . '" class="block-warning reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<div class="wb-card ' . $varClass . '">'
            . '<div class="wb-header">'
            . '<div class="wb-icon">' . ($variant === 'good_signs' ? '✅' : '⚠️') . '</div>'
            . '<div><div class="wb-title">' . $title . '</div>'
            . ($sub ? '<div class="wb-subtitle">' . $sub . '</div>' : '')
            . '</div></div>'
            . '<div class="wb-items">';
        foreach ($items as $it) {
            $sev = $it['severity'] ?? 'warning';
            switch ($sev) {
                case 'emergency': $sevIcon = '🚑'; break;
                case 'urgent': $sevIcon = '🚨'; break;
                default: $sevIcon = '⚠️';
            };
            $h .= '<div class="wb-item wb-item--' . $this->e($sev) . '">'
                . '<span class="wb-item-icon">' . $sevIcon . '</span>'
                . '<span class="wb-item-text">' . $this->e($it['text'] ?? '') . '</span>'
                . '</div>';
        }
        $h .= '</div>';
        if ($footer) {
            $h .= '<div class="wb-footer">' . $footer . '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.wb-card{border-radius:var(--radius-lg);padding:28px;border:2px solid var(--color-border)}'
            . "\n" . '.wb--red{border-color:var(--color-danger);background:color-mix(in srgb,var(--color-danger) 6%,transparent)}'
            . "\n" . '.wb--caution{border-color:var(--color-warn);background:color-mix(in srgb,var(--color-warn) 6%,transparent)}'
            . "\n" . '.wb--good{border-color:var(--color-success);background:color-mix(in srgb,var(--color-success) 6%,transparent)}'
            . "\n" . '.wb-header{display:flex;align-items:center;gap:14px;margin-bottom:18px}'
            . "\n" . '.wb-icon{font-size:2rem}'
            . "\n" . '.wb-title{font-family:var(--type-font-heading);font-size:18px;font-weight:900;color:var(--color-text)}'
            . "\n" . '.wb-subtitle{font-size:13px;color:var(--color-text-3);margin-top:2px}'
            . "\n" . '.wb-items{display:grid;gap:8px}'
            . "\n" . '.wb-item{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:var(--radius-md);background:var(--color-surface);border:1px solid var(--color-border)}'
            . "\n" . '.wb-item--emergency{border-color:var(--color-danger);animation:pulseRing 2s infinite;--pulse-c:color-mix(in srgb,var(--color-danger) 30%,transparent)}'
            . "\n" . '.wb-item-icon{font-size:1.2em;flex-shrink:0}'
            . "\n" . '.wb-item-text{font-size:14px;color:var(--color-text-2);line-height:1.4}'
            . "\n" . '.wb-footer{margin-top:16px;padding-top:14px;border-top:1px solid var(--color-border);font-size:13px;font-weight:700;color:var(--color-danger);text-align:center}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Внимание';
    }
}
