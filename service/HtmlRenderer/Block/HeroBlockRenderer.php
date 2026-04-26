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

        // Resolve hero image: prefer per-article illustration (kind=hero), fall back to legacy image_id.
        $imageId = null;
        $articleId = $c['article_id'] ?? null;
        if ($articleId) {
            $row = $this->db->fetchOne(
                "SELECT image_id FROM seo_article_illustrations
                 WHERE article_id = ? AND kind = 'hero' AND status = 'ready' AND image_id IS NOT NULL",
                [(int)$articleId]
            );
            if ($row && !empty($row['image_id'])) {
                $imageId = (int)$row['image_id'];
            }
        }
        if ($imageId === null && !empty($c['image_id'])) {
            $imageId = (int)$c['image_id'];
        }

        $imgH = '';
        if ($imageId) {
            $img = $this->db->fetchOne(
                "SELECT mime_type, data_base64 FROM seo_images WHERE id = ?",
                [$imageId]
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

        $hasImg = $imgH !== '';
        $sectionCls = 'block-hero' . ($hasImg ? ' block-hero--with-img' : ' block-hero--plain');
        $orbs = $hasImg
            ? ''
            : '<div class="hero-orb hero-orb--1"></div><div class="hero-orb hero-orb--2"></div><div class="hero-orb hero-orb--3"></div>';

        return '<section id="' . $id . '" class="' . $sectionCls . '" data-toc="' . $this->e($c['title'] ?? 'Введение') . '">'
            . $orbs
            . '<div class="hero-grid-bg"></div>'
            . '<div class="container hero-inner">'
            . $imgH
            . '<div class="hero-text">'
            . '<h1>' . $title . '</h1>'
            . '<p class="hero-subtitle">' . $sub . '</p>'
            . $ctaH
            . '</div>'
            . '</div>'
            . '</section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-hero { position:relative; overflow:hidden; text-align:left }'
            . "\n" . '.block-hero--with-img { padding:clamp(60px,10vw,100px) 0 clamp(40px,6vw,72px) }'
            . "\n" . '.block-hero--plain { padding:clamp(100px,16vw,160px) 0 clamp(60px,10vw,100px) }'
            . "\n" . '.hero-grid-bg { position:absolute; inset:0; background-image:linear-gradient(rgba(37,99,235,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(37,99,235,.04) 1px,transparent 1px); background-size:60px 60px; mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); -webkit-mask-image:radial-gradient(ellipse at 50% 40%,rgba(0,0,0,.3) 0%,transparent 70%); pointer-events:none }'
            . "\n" . '.hero-orb { position:absolute; border-radius:50%; filter:blur(80px); opacity:.45; pointer-events:none; z-index:0 }'
            . "\n" . '.hero-orb--1 { width:480px; height:480px; background:radial-gradient(circle,rgba(37,99,235,.55),transparent 70%); top:-120px; left:-120px }'
            . "\n" . '.hero-orb--2 { width:380px; height:380px; background:radial-gradient(circle,rgba(34,197,94,.45),transparent 70%); bottom:-100px; right:-80px }'
            . "\n" . '.hero-orb--3 { width:280px; height:280px; background:radial-gradient(circle,rgba(168,85,247,.4),transparent 70%); top:40%; right:30%; animation:fadeUp .8s ease both }'
            . "\n" . '.hero-inner { position:relative; z-index:1; display:flex; flex-direction:column; gap:clamp(28px,4vw,48px); max-width:1100px }'
            . "\n" . '.hero-text { animation:fadeUp .6s .1s ease both; max-width:820px }'
            . "\n" . '.hero-subtitle { font-size:clamp(1rem,2vw,1.25rem); font-weight:300; color:var(--slate); max-width:720px; line-height:1.65; margin-bottom:1.5em }'
            . "\n" . '.hero-cta { display:inline-block; background:var(--blue); color:#fff; font-family:var(--fb); font-size:1rem; font-weight:600; padding:18px 40px; border-radius:100px; transition:all .25s; box-shadow:0 8px 28px rgba(37,99,235,.35) }'
            . "\n" . '.hero-cta:hover { background:var(--blue-dark); transform:translateY(-2px); box-shadow:0 12px 36px rgba(37,99,235,.45); text-decoration:none }'
            . "\n" . '.hero-visual { animation:fadeUp .6s ease both; width:100% }'
            . "\n" . '.hero-img-frame { border-radius:24px; overflow:hidden; box-shadow:0 24px 80px rgba(15,23,42,.14); position:relative; aspect-ratio:16/9 }'
            . "\n" . '.hero-img-frame img { display:block; width:100%; height:100%; object-fit:cover }'
            . "\n" . '@media(max-width:414px) {'
            .     '.hero-cta { padding:16px 28px; font-size:.95rem }'
            .     '.hero-img-frame { border-radius:16px }'
            .     '.hero-orb--1 { width:320px; height:320px }'
            .     '.hero-orb--2 { width:260px; height:260px }'
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
