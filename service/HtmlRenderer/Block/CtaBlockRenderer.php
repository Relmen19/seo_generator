<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Block;

use Seo\Database;
use Seo\Service\HtmlRenderer\AbstractBlockRenderer;

class CtaBlockRenderer extends AbstractBlockRenderer
{
    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    public function renderHtml(array $content, string $id): string
    {
        $c = $content;
        $h = '<section id="' . $id . '" class="block-cta reveal">'
            . '<div class="cta-orb cta-orb--1"></div>'
            . '<div class="cta-orb cta-orb--2"></div>'
            . '<div class="cta-orb cta-orb--3"></div>'
            . '<div class="container" style="position:relative">';
        if (!empty($c['title'])) $h .= '<h2>' . $this->e($c['title']) . '</h2>';
        if (!empty($c['text']))  $h .= '<p>' . $this->e($c['text']) . '</p>';
        $h .= '<div class="cta-buttons">';
        if (!empty($c['primary_btn_text'])) {
            $h .= '<a class="btn-primary" href="{{link:' . $this->e($c['primary_btn_link_key'] ?? '') . '}}">'
                . $this->e($c['primary_btn_text']) . '</a>';
        }
        if (!empty($c['secondary_btn_text'])) {
            $h .= '<a class="btn-outline" href="{{link:' . $this->e($c['secondary_btn_link_key'] ?? '') . '}}">'
                . $this->e($c['secondary_btn_text']) . '</a>';
        }
        return $h . '</div></div></section>' . "\n";
    }

    public function getCss(): string
    {
        return '.block-cta { background:linear-gradient(135deg,rgba(37,99,235,.88) 0%,rgba(13,148,136,.88) 100%); backdrop-filter:blur(24px); color:#fff; text-align:center; position:relative; overflow:hidden; padding:100px 0 }'
            . "\n" . '[data-theme="dark"] .block-cta { background:linear-gradient(135deg,rgba(30,58,138,.92) 0%,rgba(19,78,74,.92) 100%) }'
            . "\n" . '.cta-orb { position:absolute; border-radius:50%; pointer-events:none; filter:blur(70px) }'
            . "\n" . '.cta-orb--1 { width:min(50vw,500px); height:min(50vw,500px); top:-120px; right:-100px; background:radial-gradient(circle,rgba(255,255,255,.22) 0%,rgba(255,255,255,.06) 45%,transparent 70%); animation:pDrift1 7s ease-in-out infinite }'
            . "\n" . '.cta-orb--2 { width:min(40vw,400px); height:min(40vw,400px); bottom:-100px; left:-80px;  background:radial-gradient(circle,rgba(255,255,255,.18) 0%,rgba(255,255,255,.05) 45%,transparent 70%); animation:pDrift2 9s ease-in-out infinite }'
            . "\n" . '.cta-orb--3 { width:min(30vw,300px); height:min(30vw,300px); top:20%; left:35%;          background:radial-gradient(circle,rgba(255,255,255,.12) 0%,rgba(255,255,255,.03) 45%,transparent 70%); animation:pDrift3 11s ease-in-out infinite }'
            . "\n" . '.block-cta h2 { color:#fff; margin-top:0; position:relative }'
            . "\n" . '.block-cta p { color:rgba(255,255,255,.85); margin-bottom:0; position:relative }'
            . "\n" . '.cta-buttons { display:flex; gap:16px; justify-content:center; margin-top:2em; flex-wrap:wrap; position:relative }'
            . "\n" . '.btn-primary { display:inline-block; background:#fff; color:var(--blue); font-family:var(--fb); font-weight:700; font-size:1rem; padding:18px 40px; border-radius:100px; transition:all .25s; text-decoration:none }'
            . "\n" . '.btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(0,0,0,.2); text-decoration:none }'
            . "\n" . '.btn-outline { display:inline-block; background:transparent; color:#fff; font-family:var(--fb); font-weight:600; font-size:1rem; border:2px solid rgba(255,255,255,.65); padding:16px 38px; border-radius:100px; transition:all .25s; text-decoration:none }'
            . "\n" . '.btn-outline:hover { background:rgba(255,255,255,.15); border-color:#fff; text-decoration:none }'
            . "\n" . '@media(max-width:414px) {'
            .     '.btn-primary,.btn-outline { padding:16px 24px; font-size:.95rem }'
            . '}';
    }

    public function getJs(): string
    {
        return '';
    }

    public function getTocLabel(array $content, array $meta): string
    {
        return $content['title'] ?? 'Действие';
    }
}
