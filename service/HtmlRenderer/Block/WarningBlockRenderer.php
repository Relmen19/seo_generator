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
        return '.wb-card{border-radius:20px;padding:28px;border:2px solid var(--border)}'
            . "\n" . '.wb--red{border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.04)}'
            . "\n" . '.wb--caution{border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.04)}'
            . "\n" . '.wb--good{border-color:rgba(16,185,129,.3);background:rgba(16,185,129,.04)}'
            . "\n" . '[data-theme="dark"] .wb--red{background:rgba(239,68,68,.06)}'
            . "\n" . '[data-theme="dark"] .wb--caution{background:rgba(245,158,11,.06)}'
            . "\n" . '[data-theme="dark"] .wb--good{background:rgba(16,185,129,.06)}'
            . "\n" . '.wb-header{display:flex;align-items:center;gap:14px;margin-bottom:18px}'
            . "\n" . '.wb-icon{font-size:2rem}'
            . "\n" . '.wb-title{font-family:var(--fh);font-size:18px;font-weight:900;color:var(--dark)}'
            . "\n" . '.wb-subtitle{font-size:13px;color:var(--muted);margin-top:2px}'
            . "\n" . '.wb-items{display:grid;gap:8px}'
            . "\n" . '.wb-item{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:12px;background:rgba(255,255,255,.5);border:1px solid var(--border)}'
            . "\n" . '[data-theme="dark"] .wb-item{background:rgba(255,255,255,.03)}'
            . "\n" . '.wb-item--emergency{border-color:rgba(239,68,68,.3);animation:pulseRing 2s infinite;--pulse-c:rgba(239,68,68,.2)}'
            . "\n" . '.wb-item-icon{font-size:1.2em;flex-shrink:0}'
            . "\n" . '.wb-item-text{font-size:14px;color:var(--slate);line-height:1.4}'
            . "\n" . '.wb-footer{margin-top:16px;padding-top:14px;border-top:1px solid var(--border);font-size:13px;font-weight:700;color:var(--red);text-align:center}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Внимание';
    }
}
