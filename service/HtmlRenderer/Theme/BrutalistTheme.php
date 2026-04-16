<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Theme;

/**
 * Brutalist / Neo-Industrial theme.
 *
 * Geometric sans + monospace (Space Grotesk + JetBrains Mono),
 * electric orange accent, 0px radius, thick borders, offset shadows.
 * High contrast, raw energy, award-winning agency aesthetic.
 */
class BrutalistTheme extends DefaultTheme
{
    public function getCssVariables(): string
    {
        return ':root {'
            . '--blue:#FF5722;--blue-dark:#E64A19;--blue-light:#FFF3E0;'
            . '--teal:#FF5722;--green:#2E7D32;--green-light:#E8F5E9;'
            . '--warn:#FF9800;--red:#F44336;'
            . '--dark:#000000;--dark2:#111111;--slate:#333333;--muted:#666666;'
            . '--border:rgba(0,0,0,0.12);--bg:#FFFFFF;--white:#FFFFFF;'
            . '--r:0px;--fh:"Space Grotesk",sans-serif;--fb:"JetBrains Mono","Fira Code",monospace'
            . '}'

            . "\n" . '[data-theme="dark"] {'
            . '--bg:#0A0A0A;--white:rgba(255,255,255,0.04);'
            . '--dark:#F5F5F0;--dark2:#050505;'
            . '--slate:#BBBBBB;--muted:#888888;'
            . '--border:rgba(255,255,255,0.12);--blue-light:rgba(255,87,34,0.12)'
            . '}';
    }

    public function getBaseCss(): string
    {
        return parent::getBaseCss()

            /* ── Typography — bold, uppercase, tight ── */
            . "\n" . 'body.theme-brutalist { letter-spacing:-.2px }'
            . "\n" . 'body.theme-brutalist h1 { letter-spacing:-2px; text-transform:uppercase; line-height:1.0 }'
            . "\n" . 'body.theme-brutalist h2 { text-transform:uppercase; letter-spacing:-1px; font-weight:900 }'
            . "\n" . 'body.theme-brutalist h3 { text-transform:uppercase; letter-spacing:-.3px }'
            . "\n" . 'body.theme-brutalist p { font-size:14px; line-height:1.8 }'
            . "\n" . 'body.theme-brutalist a { text-decoration:underline; text-decoration-thickness:2px; text-underline-offset:2px }'
            . "\n" . 'body.theme-brutalist a:hover { text-decoration-color:var(--blue) }'
            . "\n" . 'body.theme-brutalist .sec-title { text-transform:uppercase; letter-spacing:-1px }'

            /* ── Blockquote — stark, no italics ── */
            . "\n" . 'body.theme-brutalist blockquote { border-left:4px solid var(--blue); border-radius:0; font-style:normal; font-weight:500 }'

            /* ── Buttons — square, offset shadow ── */
            . "\n" . 'body.theme-brutalist .btn-primary { border-radius:0; text-transform:uppercase; letter-spacing:1px; font-weight:700; border:2px solid var(--blue); box-shadow:3px 3px 0 var(--dark) }'
            . "\n" . 'body.theme-brutalist .btn-primary:hover { box-shadow:5px 5px 0 var(--dark); transform:translate(-2px,-2px) }'

            /* ── Frames — squared, bordered ── */
            . "\n" . 'body.theme-brutalist .img-frame { border-radius:0; border:2px solid var(--border) }'
            . "\n" . 'body.theme-brutalist .mac-window { border-radius:0; border:2px solid var(--border) }'
            . "\n" . 'body.theme-brutalist .mac-bar { border-radius:0 }'
            . "\n" . 'body.theme-brutalist .highlight { border-radius:0; border-left-width:4px }'
            . "\n" . 'body.theme-brutalist .bar-track { border-radius:0 }'
            . "\n" . 'body.theme-brutalist .bar-fill { border-radius:0 }'
            . "\n" . 'body.theme-brutalist .blk-img .img-frame { border-radius:0; border:2px solid var(--border) }'
            . "\n" . 'body.theme-brutalist .img-card .img-frame { border-radius:0; border:2px solid var(--border) }'

            /* ── Section divider — thick ── */
            . "\n" . 'body.theme-brutalist .section-divider-line { height:2px }'

            /* ── Navbar — thick bottom border, uppercase, raw ── */
            . "\n" . 'body.theme-brutalist .navbar { border-bottom:3px solid var(--dark); border-top:none; background:rgba(255,255,255,.92) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .navbar { background:rgba(10,10,10,.92); border-bottom-color:var(--dark) }'
            . "\n" . 'body.theme-brutalist .nav-logo { text-transform:uppercase; letter-spacing:3px; font-size:17px; font-weight:700 }'
            . "\n" . 'body.theme-brutalist .nav-accent { color:var(--blue) }'
            . "\n" . 'body.theme-brutalist .nav-links a { text-transform:uppercase; letter-spacing:1.5px; font-family:var(--fb); font-size:11px; font-weight:700 }'
            . "\n" . 'body.theme-brutalist .theme-toggle { border-radius:0; border-width:2px }'
            . "\n" . 'body.theme-brutalist .theme-toggle:hover { border-radius:0 }'

            /* ── TOC — monospace, numbered, thick marker ── */
            . "\n" . 'body.theme-brutalist .toc-inner { border-radius:0; border:2px solid var(--border); background:rgba(255,255,255,.92); counter-reset:toc-num }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .toc-inner { background:rgba(10,10,10,.92) }'
            . "\n" . 'body.theme-brutalist .toc-link { font-family:var(--fb); font-size:10px; border-radius:0; border-left:3px solid transparent }'
            . "\n" . 'body.theme-brutalist .toc-link::before { counter-increment:toc-num; content:counter(toc-num,decimal-leading-zero); font-size:9px; color:var(--muted); margin-right:6px; font-weight:700 }'
            . "\n" . 'body.theme-brutalist .toc-link.active { border-left-color:var(--blue); background:var(--blue-light); border-radius:0 }'
            . "\n" . 'body.theme-brutalist .toc-link:hover { border-radius:0 }'
            . "\n" . 'body.theme-brutalist .toc-label { font-family:var(--fb); letter-spacing:3px; font-size:8px }'

            /* ── Parallax orbs — monochrome + orange pop ── */
            . "\n" . 'body.theme-brutalist .p-orb--1  { background:radial-gradient(circle,rgba(255,87,34,.18)  0%,rgba(255,87,34,.04)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--2  { background:radial-gradient(circle,rgba(0,0,0,.06)      0%,rgba(0,0,0,.02)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--3  { background:radial-gradient(circle,rgba(255,87,34,.12)  0%,rgba(255,87,34,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--4  { background:radial-gradient(circle,rgba(0,0,0,.05)      0%,rgba(0,0,0,.01)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--5  { background:radial-gradient(circle,rgba(255,87,34,.14)  0%,rgba(255,87,34,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--6  { background:radial-gradient(circle,rgba(0,0,0,.04)      0%,rgba(0,0,0,.01)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--7  { background:radial-gradient(circle,rgba(255,87,34,.10)  0%,rgba(255,87,34,.02)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--8  { background:radial-gradient(circle,rgba(0,0,0,.06)      0%,rgba(0,0,0,.02)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--9  { background:radial-gradient(circle,rgba(255,87,34,.08)  0%,rgba(255,87,34,.02)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--10 { background:radial-gradient(circle,rgba(0,0,0,.05)      0%,rgba(0,0,0,.01)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--11 { background:radial-gradient(circle,rgba(255,87,34,.10)  0%,rgba(255,87,34,.02)  90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--12 { background:radial-gradient(circle,rgba(0,0,0,.04)      0%,rgba(0,0,0,.01)      90%,transparent 72%) }'
            . "\n" . 'body.theme-brutalist .p-orb--13 { background:radial-gradient(circle,rgba(255,87,34,.12)  0%,rgba(255,87,34,.03)  90%,transparent 72%) }'

            /* Dark mode orbs — bright orange + subtle white */
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--1  { background:radial-gradient(circle,rgba(255,120,70,.40)  0%,rgba(255,87,34,.12)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--2  { background:radial-gradient(circle,rgba(255,255,255,.06) 0%,rgba(255,255,255,.02) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--3  { background:radial-gradient(circle,rgba(255,120,70,.30)  0%,rgba(255,87,34,.08)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--4  { background:radial-gradient(circle,rgba(255,255,255,.05) 0%,rgba(255,255,255,.02) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--5  { background:radial-gradient(circle,rgba(255,120,70,.35)  0%,rgba(255,87,34,.10)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--6  { background:radial-gradient(circle,rgba(255,255,255,.04) 0%,rgba(255,255,255,.01) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--7  { background:radial-gradient(circle,rgba(255,120,70,.25)  0%,rgba(255,87,34,.07)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--8  { background:radial-gradient(circle,rgba(255,255,255,.05) 0%,rgba(255,255,255,.02) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--9  { background:radial-gradient(circle,rgba(255,120,70,.22)  0%,rgba(255,87,34,.06)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--10 { background:radial-gradient(circle,rgba(255,255,255,.04) 0%,rgba(255,255,255,.01) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--11 { background:radial-gradient(circle,rgba(255,120,70,.28)  0%,rgba(255,87,34,.08)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--12 { background:radial-gradient(circle,rgba(255,255,255,.04) 0%,rgba(255,255,255,.01) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-brutalist .p-orb--13 { background:radial-gradient(circle,rgba(255,120,70,.30)  0%,rgba(255,87,34,.08)   50%,transparent 72%) }'

            /* ── Related articles — square, offset shadow hover ── */
            . "\n" . 'body.theme-brutalist .ra-card { border-radius:0; border-width:2px }'
            . "\n" . 'body.theme-brutalist .ra-card:hover { box-shadow:4px 4px 0 var(--blue); transform:translate(-2px,-2px) }'
            . "\n" . 'body.theme-brutalist .ra-skeleton { border-radius:0 }';
    }

    public function getFontLinks(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=JetBrains+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">';
    }

    public function getBodyClass(): string
    {
        return 'theme-brutalist';
    }
}
