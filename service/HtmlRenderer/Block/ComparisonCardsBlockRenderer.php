<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class ComparisonCardsBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $c, string $id): string
    {
        $title = $this->e($c['title'] ?? 'Сравнение');

        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $h = '<section id="' . $id . '" class="block-ccards reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>'
            . '<div class="cc-grid">';
        foreach (['card_a', 'card_b'] as $side) {
            $card = $c[$side] ?? [];
            $color = $this->e($card['color'] ?? 'var(--blue)');
            $name  = $this->e($card['name'] ?? '');
            $badge = $this->e($card['badge'] ?? '');
            $price = $this->e($card['price'] ?? '');
            $verdict = $this->e($card['verdict'] ?? '');

            $h .= '<div class="cc-card" style="--cc-c:' . $color . '">'
                . ($badge ? '<div class="cc-badge">' . $badge . '</div>' : '')
                . '<div class="cc-name">' . $name . '</div>';
            if (!empty($card['pros'])) {
                $h .= '<div class="cc-list cc-pros">';
                foreach ($card['pros'] as $p) $h .= '<div class="cc-li"><span class="cc-li-icon cc-ok">✓</span>' . $this->e($p) . '</div>';
                $h .= '</div>';
            }
            if (!empty($card['cons'])) {
                $h .= '<div class="cc-list cc-cons">';
                foreach ($card['cons'] as $cn) $h .= '<div class="cc-li"><span class="cc-li-icon cc-no">✗</span>' . $this->e($cn) . '</div>';
                $h .= '</div>';
            }
            if ($price)   $h .= '<div class="cc-price">' . $price . '</div>';
            if ($verdict) $h .= '<div class="cc-verdict">' . $verdict . '</div>';
            $h .= '</div>';
        }
        $h .= '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    public function getCss(): string
    {
        return '.cc-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}'
            . "\n" . '@media(max-width:700px){.cc-grid{grid-template-columns:1fr}}'
            . "\n" . '.cc-card{padding:28px 24px;border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:2px solid var(--border);position:relative;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .cc-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.cc-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(15,23,42,.08);border-color:var(--cc-c,var(--blue))}'
            . "\n" . '.cc-badge{position:absolute;top:-1px;right:20px;padding:4px 14px;border-radius:0 0 10px 10px;font-family:var(--fh);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;background:var(--cc-c,var(--blue));color:#fff}'
            . "\n" . '.cc-name{font-family:var(--fh);font-size:1.3rem;font-weight:900;color:var(--dark);margin-bottom:16px;padding-right:60px}'
            . "\n" . '.cc-list{margin-bottom:14px}'
            . "\n" . '.cc-li{display:flex;align-items:flex-start;gap:8px;padding:6px 0;font-size:14px;color:var(--slate);line-height:1.5}'
            . "\n" . '.cc-li-icon{flex-shrink:0;font-size:14px;font-weight:700;margin-top:2px}'
            . "\n" . '.cc-ok{color:var(--green)}'
            . "\n" . '.cc-no{color:var(--red)}'
            . "\n" . '.cc-price{font-family:var(--fh);font-size:1.1rem;font-weight:700;color:var(--dark);padding:12px 0;border-top:1px solid var(--border)}'
            . "\n" . '.cc-verdict{font-size:13px;color:var(--muted);font-style:italic;line-height:1.5}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Сравнение';
    }
}
