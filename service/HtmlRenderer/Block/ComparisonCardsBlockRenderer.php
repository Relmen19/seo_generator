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

        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';

        $items = $this->normalizeComparisons($c);

        $h = '<section id="' . $id . '" class="block-ccards reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . $title . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . '<h2 class="sec-title">' . $title . '</h2>';

        if (count($items) <= 1) {
            $entry = $items[0] ?? ['card_a' => [], 'card_b' => []];
            $h .= $this->renderGrid($entry);
        } else {
            $h .= '<div class="cc-accordion">';
            foreach ($items as $i => $entry) {
                $label = $this->e($entry['label'] ?? $this->autoLabel($entry, $i));
                $desc  = $this->e($entry['description'] ?? $this->autoDescription($entry));
                $open  = $i === 0 ? ' open' : '';
                $h .= '<details class="cc-acc-item"' . $open . '>'
                    . '<summary class="cc-acc-sum">'
                    . '<div class="cc-acc-head">'
                    . '<div class="cc-acc-title">' . $label . '</div>'
                    . ($desc ? '<div class="cc-acc-desc">' . $desc . '</div>' : '')
                    . '</div>'
                    . '<div class="cc-acc-caret">▾</div>'
                    . '</summary>'
                    . '<div class="cc-acc-body">'
                    . $this->renderGrid($entry)
                    . '</div>'
                    . '</details>';
            }
            $h .= '</div>';
        }

        $h .= '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
        return $h;
    }

    private function normalizeComparisons(array $c): array
    {
        if (isset($c['comparisons']) && is_array($c['comparisons']) && $c['comparisons']) {
            $out = [];
            foreach ($c['comparisons'] as $entry) {
                if (is_array($entry) && (isset($entry['card_a']) || isset($entry['card_b']))) {
                    $out[] = $entry;
                }
            }
            if ($out) return $out;
        }
        if (isset($c['card_a']) || isset($c['card_b'])) {
            return [['card_a' => $c['card_a'] ?? [], 'card_b' => $c['card_b'] ?? []]];
        }
        return [];
    }

    private function autoLabel(array $entry, int $i): string
    {
        $a = $entry['card_a']['name'] ?? '';
        $b = $entry['card_b']['name'] ?? '';
        if ($a && $b) return $a . ' vs ' . $b;
        if ($a || $b) return $a ?: $b;
        return 'Сравнение ' . ($i + 1);
    }

    private function autoDescription(array $entry): string
    {
        $va = $entry['card_a']['verdict'] ?? '';
        $vb = $entry['card_b']['verdict'] ?? '';
        if ($va && $vb) return mb_substr($va . ' / ' . $vb, 0, 160);
        return $va ?: $vb ?: '';
    }

    private function renderGrid(array $entry): string
    {
        $h = '<div class="cc-grid">';
        foreach (['card_a', 'card_b'] as $side) {
            $card = $entry[$side] ?? [];
            if (!is_array($card)) $card = [];
            $color = $this->e($card['color'] ?? 'var(--color-accent)');
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
        $h .= '</div>';
        return $h;
    }

    public function getCss(): string
    {
        return '.cc-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}'
            . "\n" . '@media(max-width:700px){.cc-grid{grid-template-columns:1fr}}'
            . "\n" . '.cc-card{padding:28px 24px;border-radius:20px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:2px solid var(--color-border);position:relative;transition:all .3s}'
            . "\n" . '[data-theme="dark"] .cc-card{background:rgba(255,255,255,.03)}'
            . "\n" . '.cc-card:hover{transform:translateY(-4px);box-shadow:0 16px 48px rgba(15,23,42,.08);border-color:var(--cc-c,var(--color-accent))}'
            . "\n" . '.cc-badge{position:absolute;top:-1px;right:20px;padding:4px 14px;border-radius:0 0 10px 10px;font-family:var(--type-font-heading);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;background:var(--cc-c,var(--color-accent));color:#fff}'
            . "\n" . '.cc-name{font-family:var(--type-font-heading);font-size:1.3rem;font-weight:900;color:var(--color-text);margin-bottom:16px;padding-right:60px}'
            . "\n" . '.cc-list{margin-bottom:14px}'
            . "\n" . '.cc-li{display:flex;align-items:flex-start;gap:8px;padding:6px 0;font-size:14px;color:var(--color-text-2);line-height:1.5}'
            . "\n" . '.cc-li-icon{flex-shrink:0;font-size:14px;font-weight:700;margin-top:2px}'
            . "\n" . '.cc-ok{color:var(--color-success)}'
            . "\n" . '.cc-no{color:var(--color-danger)}'
            . "\n" . '.cc-price{font-family:var(--type-font-heading);font-size:1.1rem;font-weight:700;color:var(--color-text);padding:12px 0;border-top:1px solid var(--color-border)}'
            . "\n" . '.cc-verdict{font-size:13px;color:var(--color-text-3);font-style:italic;line-height:1.5}'
            . "\n" . '.cc-accordion{display:flex;flex-direction:column;gap:12px}'
            . "\n" . '.cc-acc-item{border:1px solid var(--color-border);border-radius:16px;background:rgba(255,255,255,.5);overflow:hidden;transition:border-color .2s}'
            . "\n" . '[data-theme="dark"] .cc-acc-item{background:rgba(255,255,255,.03)}'
            . "\n" . '.cc-acc-item[open]{border-color:var(--color-accent)}'
            . "\n" . '.cc-acc-sum{list-style:none;cursor:pointer;padding:16px 20px;display:flex;align-items:center;gap:16px;user-select:none}'
            . "\n" . '.cc-acc-sum::-webkit-details-marker{display:none}'
            . "\n" . '.cc-acc-head{flex:1;min-width:0}'
            . "\n" . '.cc-acc-title{font-family:var(--type-font-heading);font-weight:800;font-size:1.05rem;color:var(--color-text);margin-bottom:2px}'
            . "\n" . '.cc-acc-desc{font-size:13px;color:var(--color-text-3);line-height:1.4}'
            . "\n" . '.cc-acc-caret{font-size:18px;color:var(--color-text-3);transition:transform .2s;flex-shrink:0}'
            . "\n" . '.cc-acc-item[open] .cc-acc-caret{transform:rotate(180deg)}'
            . "\n" . '.cc-acc-body{padding:8px 20px 20px}'
            ;
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Сравнение';
    }
}
