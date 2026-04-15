<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class HeroBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $title = $this->e($c['title'] ?? '');
        $sub   = $this->e($c['subtitle'] ?? '');
        $cta   = $this->e($c['cta_text'] ?? '');
        $ctaK  = $c['cta_link_key'] ?? '';
        $ctaH  = $cta
            ? '<a class="hero-cta" href="{{link:' . $this->e($ctaK) . '}}">' . $cta . '</a>'
            : '';

        $imgH = '';
        if (!empty($c['image_id'])) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [$c['image_id']]
            );
            if ($img) {
                $alt  = $this->e($c['image_alt'] ?? $c['title'] ?? '');
                $imgH = '<div class="hero-visual">'
                    . '<div class="hero-img-frame">'
                    . '<img src="data:' . $img['mime_type'] . ';base64,' . $img['data_base64']
                    . '" alt="' . $alt . '" class="hero-img" loading="eager">'
                    . '<div class="img-shine"></div>'
                    . '</div></div>';
            }
        }

        return '<section id="' . $id . '" class="block-hero" data-toc="' . $this->e($c['title'] ?? 'Введение') . '">'
            . '<div class="hero-orb hero-orb--1"></div>'
            . '<div class="hero-orb hero-orb--2"></div>'
            . '<div class="hero-orb hero-orb--3"></div>'
            . '<div class="hero-grid-bg"></div>'
            . '<div class="container hero-inner">'
            . '<div class="hero-text">'
            . '<h1>' . $title . '</h1>'
            . '<p class="hero-subtitle">' . $sub . '</p>'
            . $ctaH
            . '</div>'
            . $imgH
            . '</div>'
            . '</section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-hero { position:relative; overflow:hidden; padding:clamp(100px,16vw,160px) 0 clamp(60px,10vw,100px); text-align:left }'
            . "\n" . '.hero-orb { display:none }'
            . "\n" . '.hero-grid-bg { position:absolute; inset:0; background-image:linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px); background-size:60px 60px; mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); -webkit-mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); pointer-events:none }'
            . "\n" . '.hero-inner { position:relative; z-index:1; display:grid; grid-template-columns:2fr 1fr; gap:48px; align-items:center; max-width:1000px }'
            . "\n" . '.hero-text { animation:fadeUp .6s ease both }'
            . "\n" . '.hero-subtitle { font-size:clamp(1rem,2vw,1.25rem); font-weight:300; color:var(--slate); max-width:540px; line-height:1.65; margin-bottom:2em }'
            . "\n" . '.hero-cta { display:inline-block; background:var(--blue); color:#fff; font-family:var(--fb); font-size:1rem; font-weight:600; padding:18px 40px; border-radius:100px; transition:all .25s; box-shadow:0 8px 28px rgba(37,99,235,.35) }'
            . "\n" . '.hero-cta:hover { background:var(--blue-dark); transform:translateY(-2px); box-shadow:0 12px 36px rgba(37,99,235,.45); text-decoration:none }'
            . "\n" . '.hero-visual { animation:fadeUp .6s .15s ease both }'
            . "\n" . '.hero-img-frame { border-radius:20px; overflow:hidden; box-shadow:0 24px 80px rgba(15,23,42,.14); position:relative }'
            . "\n" . '.hero-img-frame img { display:block; width:100%; max-height:460px; object-fit:cover }'
            . "\n" . '@media(max-width:768px) {'
            .     '.hero-inner { grid-template-columns:1fr; gap:32px; text-align:center }'
            .     '.hero-subtitle { margin-left:auto; margin-right:auto }'
            .     '.hero-cta { margin:0 auto; display:inline-block }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .     '.hero-cta { padding:16px 28px; font-size:.95rem }'
            .     '.hero-img-frame img { max-height:280px }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Введение';
    }
}
