<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Theme;

/**
 * Editorial / Magazine theme.
 *
 * Serif typography (Playfair Display + Source Serif 4), warm cream palette,
 * crimson accent, sharp corners, thin decorative lines.
 * Think NYT / Monocle / high-end publication.
 */
class EditorialTheme extends DefaultTheme
{
    public function getCssVariables(): string
    {
        return ':root {'
            . '--blue:#B7312C;--blue-dark:#8B1A1A;--blue-light:#FDF2F2;'
            . '--teal:#C8915A;--green:#2E7D32;--green-light:#E8F5E9;'
            . '--warn:#E65100;--red:#C62828;'
            . '--dark:#1A1A2E;--dark2:#2D2D44;--slate:#444466;--muted:#7A7A99;'
            . '--border:rgba(26,26,46,0.08);--bg:#FBF9F6;--white:#FFFFFF;'
            . '--r:2px;--fh:"Playfair Display",Georgia,serif;--fb:"Source Serif 4",Georgia,serif'
            . '}'

            . "\n" . '[data-theme="dark"] {'
            . '--bg:#141422;--white:rgba(255,255,255,0.04);'
            . '--dark:#F0ECE2;--dark2:#0D0D1A;'
            . '--slate:#B0A8C0;--muted:#7A7090;'
            . '--border:rgba(240,236,226,0.08);--blue-light:rgba(183,49,44,0.12)'
            . '}';
    }

    public function getBaseCss(): string
    {
        return parent::getBaseCss()

            /* ── Typography refinements ── */
            . "\n" . 'body.theme-editorial h1 { letter-spacing:-.5px; font-weight:700 }'
            . "\n" . 'body.theme-editorial h2 { font-weight:700 }'
            . "\n" . 'body.theme-editorial p { line-height:1.85 }'
            . "\n" . 'body.theme-editorial a:hover { text-underline-offset:3px }'

            /* ── Blockquote — decorative opening quote ── */
            . "\n" . 'body.theme-editorial blockquote { border-left-width:3px; padding:1.2em 1.5em 1.2em 2.2em; position:relative }'
            . "\n" . 'body.theme-editorial blockquote::before { content:"\\201C"; font-family:var(--fh); font-size:3.5em; position:absolute; top:-5px; left:8px; color:var(--blue); opacity:.15; line-height:1 }'

            /* ── Buttons — refined, uppercase ── */
            . "\n" . 'body.theme-editorial .btn-primary { border-radius:2px; padding:12px 32px; font-size:13px; letter-spacing:.8px; text-transform:uppercase }'

            /* ── Frames — sharp corners ── */
            . "\n" . 'body.theme-editorial .img-frame { border-radius:4px }'
            . "\n" . 'body.theme-editorial .mac-window { border-radius:6px }'
            . "\n" . 'body.theme-editorial .mac-bar { border-radius:6px 6px 0 0 }'
            . "\n" . 'body.theme-editorial .highlight { border-radius:0 2px 2px 0 }'
            . "\n" . 'body.theme-editorial .blk-img .img-frame { border-radius:4px }'
            . "\n" . 'body.theme-editorial .img-card .img-frame { border-radius:4px }'

            /* ── Section divider — gradient fade ── */
            . "\n" . 'body.theme-editorial .section-divider-line { background:linear-gradient(90deg,transparent,var(--border),var(--border),transparent) }'

            /* ── Navbar — top accent stripe, serif links ── */
            . "\n" . 'body.theme-editorial .navbar { border-bottom:none; border-top:3px solid var(--blue); background:rgba(251,249,246,.88) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .navbar { background:rgba(20,20,34,.88) }'
            . "\n" . 'body.theme-editorial .nav-logo { letter-spacing:1.5px; font-weight:700; font-size:19px }'
            . "\n" . 'body.theme-editorial .nav-links a { text-transform:uppercase; letter-spacing:2px; font-size:11px; font-family:var(--fh); font-weight:500 }'
            . "\n" . 'body.theme-editorial .theme-toggle { border-radius:4px }'

            /* ── TOC — italic active, underline instead of left border ── */
            . "\n" . 'body.theme-editorial .toc-inner { border-radius:4px; background:rgba(251,249,246,.9) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .toc-inner { background:rgba(20,20,34,.9) }'
            . "\n" . 'body.theme-editorial .toc-link { font-family:var(--fb); border-left:none; position:relative; padding-bottom:6px; border-radius:2px }'
            . "\n" . 'body.theme-editorial .toc-link:hover { border-radius:2px }'
            . "\n" . 'body.theme-editorial .toc-link.active { background:none; border-left-color:transparent; font-style:italic; color:var(--blue); border-radius:2px }'
            . "\n" . 'body.theme-editorial .toc-link.active::after { content:""; position:absolute; bottom:2px; left:8px; right:8px; height:1px; background:var(--blue) }'
            . "\n" . 'body.theme-editorial .toc-label { font-family:var(--fh); letter-spacing:3px }'

            /* ── Parallax orbs — warm editorial palette ── */
            . "\n" . 'body.theme-editorial .p-orb--1  { background:radial-gradient(circle,rgba(183,49,44,.22)  0%,rgba(183,49,44,.05)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--2  { background:radial-gradient(circle,rgba(200,145,90,.20) 0%,rgba(200,145,90,.04) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--3  { background:radial-gradient(circle,rgba(156,39,76,.18)  0%,rgba(156,39,76,.04)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--4  { background:radial-gradient(circle,rgba(106,60,140,.18) 0%,rgba(106,60,140,.04) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--5  { background:radial-gradient(circle,rgba(190,100,40,.20) 0%,rgba(190,100,40,.04) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--6  { background:radial-gradient(circle,rgba(183,49,44,.16)  0%,rgba(183,49,44,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--7  { background:radial-gradient(circle,rgba(200,145,90,.16) 0%,rgba(200,145,90,.03) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--8  { background:radial-gradient(circle,rgba(156,39,76,.14)  0%,rgba(156,39,76,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--9  { background:radial-gradient(circle,rgba(85,110,60,.16)  0%,rgba(85,110,60,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--10 { background:radial-gradient(circle,rgba(183,49,44,.14)  0%,rgba(183,49,44,.03)  90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--11 { background:radial-gradient(circle,rgba(106,60,140,.12) 0%,rgba(106,60,140,.03) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--12 { background:radial-gradient(circle,rgba(190,100,40,.14) 0%,rgba(190,100,40,.03) 90%,transparent 72%) }'
            . "\n" . 'body.theme-editorial .p-orb--13 { background:radial-gradient(circle,rgba(200,145,90,.14) 0%,rgba(200,145,90,.03) 90%,transparent 72%) }'

            /* Dark mode orbs — brighter warm glow */
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--1  { background:radial-gradient(circle,rgba(220,80,70,.45)   0%,rgba(183,49,44,.15)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--2  { background:radial-gradient(circle,rgba(220,170,110,.40) 0%,rgba(200,145,90,.12) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--3  { background:radial-gradient(circle,rgba(200,80,120,.40)  0%,rgba(156,39,76,.12)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--4  { background:radial-gradient(circle,rgba(150,100,190,.38) 0%,rgba(106,60,140,.12) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--5  { background:radial-gradient(circle,rgba(220,130,70,.42)  0%,rgba(190,100,40,.13) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--6  { background:radial-gradient(circle,rgba(220,80,70,.35)   0%,rgba(183,49,44,.10)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--7  { background:radial-gradient(circle,rgba(220,170,110,.35) 0%,rgba(200,145,90,.10) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--8  { background:radial-gradient(circle,rgba(200,80,120,.32)  0%,rgba(156,39,76,.10)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--9  { background:radial-gradient(circle,rgba(130,170,90,.35)  0%,rgba(85,110,60,.10)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--10 { background:radial-gradient(circle,rgba(220,80,70,.30)   0%,rgba(183,49,44,.08)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--11 { background:radial-gradient(circle,rgba(150,100,190,.30) 0%,rgba(106,60,140,.08) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--12 { background:radial-gradient(circle,rgba(220,130,70,.32)  0%,rgba(190,100,40,.09) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] body.theme-editorial .p-orb--13 { background:radial-gradient(circle,rgba(220,170,110,.30) 0%,rgba(200,145,90,.08) 50%,transparent 72%) }'

            /* ── Related articles ── */
            . "\n" . 'body.theme-editorial .ra-card { border-radius:4px }'
            . "\n" . 'body.theme-editorial .ra-skeleton { border-radius:4px }';
    }

    public function getFontLinks(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;700;900&family=Source+Serif+4:opsz,wght@8..60,300;8..60,400;8..60,500;8..60,600&display=swap" rel="stylesheet">';
    }

    public function getBodyClass(): string
    {
        return 'theme-editorial';
    }
}
