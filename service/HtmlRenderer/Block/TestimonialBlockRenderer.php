<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class TestimonialBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        /* Image support */
        [$imgTop, $imgH, $imgBot, $bgStyle] = $this->resolveBlockImages($c, 'right');
        $bgAttr = $bgStyle ? ' style="' . $bgStyle . '" ' : '';
        $title = $this->e($c['title'] ?? '');

        $h = '<section id="' . $id . '" class="block-reviews reveal' . ($bgStyle ? ' has-bg-img' : '') . '"' . $bgAttr . ' data-toc="' . ($title !== '' ? $title : 'Отзывы') . '">'
            . '<div class="container">'
            . $imgTop
            . $imgH
            . ($title !== '' ? '<h2 class="sec-title" style="color:#fff">' . $title . '</h2>' : '')
            . '<div class="reviews-grid">';
        foreach ($c['items'] ?? $c['testimonials'] ?? [] as $it) {
            $text   = $this->e($it['text'] ?? $it['content'] ?? '');
            $author = $this->e($it['author'] ?? $it['name'] ?? '');
            $role   = $this->e($it['role'] ?? '');
            $words  = explode(' ', strip_tags($it['author'] ?? $it['name'] ?? '?'));
            $init   = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))));

            $h .= '<div class="review-card">'
                . '<div class="review-stars">★★★★★</div>'
                . '<div class="review-text">«' . $text . '»</div>'
                . '<div class="review-who">'
                . '<div class="review-av">' . $this->e($init) . '</div>'
                . '<div><div class="review-name">' . $author . '</div>'
                . ($role ? '<div class="review-role">' . $role . '</div>' : '')
                . '</div></div></div>';
        }
        return $h . '</div>'
            . '<div class="clearfix"></div>'
            . $imgBot
            . '</div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-reviews { background:rgba(15,23,42,.9); backdrop-filter:blur(20px); padding:80px 0 }'
            . "\n" . '[data-theme="dark"] .block-reviews { background:rgba(2,8,16,.92) }'
            . "\n" . '.reviews-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px }'
            . "\n" . '.review-card { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:var(--r); padding:24px; transition:all .25s }'
            . "\n" . '.review-card:hover { transform:translateY(-3px); box-shadow:0 8px 28px rgba(0,0,0,.2); border-color:rgba(255,255,255,.15) }'
            . "\n" . '.review-stars { color:#FBBF24; font-size:14px; letter-spacing:2px; margin-bottom:12px }'
            . "\n" . '.review-text { font-size:14px; color:rgba(255,255,255,.75); line-height:1.65; margin-bottom:18px; font-style:italic }'
            . "\n" . '.review-who { display:flex; align-items:center; gap:10px }'
            . "\n" . '.review-av { width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,var(--blue),var(--teal)); display:flex; align-items:center; justify-content:center; font-family:var(--fh); font-size:13px; font-weight:700; color:#fff; flex-shrink:0 }'
            . "\n" . '.review-name { font-size:14px; font-weight:500; color:#fff }'
            . "\n" . '.review-role { font-size:12px; color:rgba(255,255,255,.4); margin-top:2px }';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Отзывы';
    }
}
