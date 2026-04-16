<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Theme;

class DefaultTheme implements ThemeInterface
{
    public function getCssVariables(): string
    {
        return ':root {'
            . '--blue:#2563EB;--blue-dark:#1D4ED8;--blue-light:#EFF6FF;'
            . '--teal:#0D9488;--green:#16A34A;--green-light:#DCFCE7;'
            . '--warn:#F59E0B;--red:#EF4444;'
            . '--dark:#0F172A;--dark2:#1E293B;--slate:#334155;--muted:#64748B;'
            . '--border:rgba(0,0,0,0.08);--bg:#F8FAFC;--white:#FFFFFF;'
            . '--r:14px;--fh:"Geologica",sans-serif;--fb:"Onest",sans-serif'
            . '}'

            . "\n" . '[data-theme="dark"] {'
            . '--bg:#050D1A;--white:rgba(255,255,255,0.04);'
            . '--dark:#E2E8F0;--dark2:#040C18;'
            . '--slate:#94A3B8;--muted:#5A7A9F;'
            . '--border:rgba(255,255,255,0.08);--blue-light:rgba(37,99,235,0.12)'
            . '}';
    }

    public function getBaseCss(): string
    {
        return
            /* ── RESET ── */
            '*,*::before,*::after { box-sizing:border-box; margin:0; padding:0 }'
            . "\n" . 'html { scroll-behavior:smooth; scroll-padding-top:80px }'
            . "\n" . 'body { font-family:var(--fb); background:var(--bg); color:var(--dark); line-height:1.7; overflow-x:hidden; transition:background .4s,color .3s }'

            /* ── TYPOGRAPHY ── */
            . "\n" . 'h1 { font-family:var(--fh); font-size:clamp(2rem,5vw,3.5rem); font-weight:900; line-height:1.08; letter-spacing:-1.5px; margin-bottom:.5em; color:var(--dark) }'
            . "\n" . 'h2 { font-family:var(--fh); font-size:clamp(1.5rem,3vw,2.2rem); font-weight:900; letter-spacing:-.5px; line-height:1.1; margin:1.5em 0 .7em; color:var(--dark) }'
            . "\n" . 'h3 { font-family:var(--fh); font-size:1.35rem; font-weight:700; margin:1.2em 0 .5em; color:var(--dark) }'
            . "\n" . 'h4 { font-family:var(--fh); font-size:1.05rem; font-weight:700; margin:.8em 0 .3em; color:var(--dark) }'
            . "\n" . 'p { margin-bottom:1em; color:var(--slate) }'
            . "\n" . 'ul,ol { margin:0 0 1em 1.5em; overflow:hidden }'
            . "\n" . 'li { margin-bottom:.4em; color:var(--slate); break-inside:avoid; page-break-inside:avoid }'
            . "\n" . 'a { color:var(--blue); text-decoration:none }'
            . "\n" . 'a:hover { text-decoration:underline }'
            . "\n" . 'img { max-width:100%; height:auto }'
            . "\n" . 'blockquote { border-left:4px solid var(--blue); padding:.8em 1.2em; margin:1em 0; background:var(--blue-light); border-radius:0 var(--r) var(--r) 0; font-style:italic; color:var(--slate) }'
            . "\n" . '.sec-title { font-family:var(--fh); font-size:clamp(1.5rem,3vw,2.2rem); font-weight:900; letter-spacing:-.5px; line-height:1.1; margin:0 0 .8em; color:var(--dark) }'
            . "\n" . '.container { max-width:960px; margin:0 auto; padding:0 24px }'
            . "\n" . 'section { padding:80px 0; position:relative; background:transparent }'
            . "\n" . '.clearfix::after { content:""; display:table; clear:both }'
            . "\n" . '.clearfix { clear:both }'

            /* ── BACKGROUND IMAGE LAYOUT ── */
            . "\n" . '.has-bg-img { background-size:cover; background-position:center; background-repeat:no-repeat; position:relative }'
            . "\n" . '.has-bg-img::before { content:""; position:absolute; inset:0; background:rgba(248,250,252,.88); backdrop-filter:blur(2px); z-index:0; pointer-events:none }'
            . "\n" . '[data-theme="dark"] .has-bg-img::before { background:rgba(5,13,26,.88) }'
            . "\n" . '.has-bg-img > .container { position:relative; z-index:1 }'

            /* ── APPLE-STYLE SECTION DIVIDERS ── */
            . "\n" . '.section-divider { display:flex; align-items:center; justify-content:center; padding:0; height:1px; position:relative; z-index:2 }'
            . "\n" . '.section-divider-line { width:min(100%,960px); margin:0 auto; height:1px; background:var(--border) }'

            /* ── MAIN ── */
            . "\n" . '.page-main { padding-top:64px; position:relative; z-index:1 }'

            /* ── REVEAL ANIMATION ── */
            . "\n" . '.reveal { opacity:0; transform:translateY(28px); transition:opacity .6s ease,transform .6s ease }'
            . "\n" . '.reveal.vis { opacity:1; transform:none }'

            /* ── macOS WINDOW ── */
            . "\n" . '.mac-window { background:rgba(255,255,255,.7); backdrop-filter:blur(12px); border-radius:20px; border:1px solid var(--border); box-shadow:0 8px 40px rgba(15,23,42,.06); overflow:hidden }'
            . "\n" . '[data-theme="dark"] .mac-window { background:rgba(255,255,255,.04) }'
            . "\n" . '.mac-bar { padding:14px 20px; background:var(--dark2); display:flex; align-items:center; gap:10px }'
            . "\n" . '.mac-dots { display:flex; gap:6px }'
            . "\n" . '.mac-dots span { width:12px; height:12px; border-radius:50%; display:block }'
            . "\n" . '.mac-dots span:nth-child(1) { background:#FF5F57 }'
            . "\n" . '.mac-dots span:nth-child(2) { background:#FFBD2E }'
            . "\n" . '.mac-dots span:nth-child(3) { background:#28C840 }'
            . "\n" . '.mac-title { font-family:var(--fh); font-size:13px; font-weight:600; color:rgba(255,255,255,.5); margin-left:8px }'
            . "\n" . '.mac-body { padding:24px 28px }'

            /* ── PROGRESS BARS ── */
            . "\n" . '.bar-track { height:8px; background:var(--border); border-radius:100px; overflow:hidden; position:relative }'
            . "\n" . '.bar-fill { height:100%; border-radius:100px; width:0; transition:width 1.4s cubic-bezier(.4,0,.2,1) }'
            . "\n" . '.bar-fill--shimmer::after { content:""; position:absolute; top:0; left:0; right:0; bottom:0; background:linear-gradient(90deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.45) 50%,rgba(255,255,255,0) 100%); animation:shimmer 2.5s ease-in-out infinite }'
            . "\n" . '.highlight { background:var(--blue-light); border-left:4px solid var(--blue); padding:18px 22px; border-radius:0 var(--r) var(--r) 0; margin:1.2em 0; color:var(--slate) }'

            /* ── IMG FRAME + SHINE ── */
            . "\n" . '.img-frame { position:relative; border-radius:20px; overflow:hidden; box-shadow:0 16px 56px rgba(15,23,42,.12) }'
            . "\n" . '.img-frame img { display:block; width:100%; height:auto; transition:transform .5s ease }'
            . "\n" . '.img-frame:hover img { transform:scale(1.03) }'
            . "\n" . '.img-shine { position:absolute; inset:0; background:linear-gradient(135deg,rgba(255,255,255,.2) 0%,transparent 50%,rgba(255,255,255,.05) 100%); pointer-events:none }'

            /* ── BLOCK IMAGE — TOP / BOTTOM LAYOUT ── */
            . "\n" . '.blk-img { margin:0 0 28px; width:100% }'
            . "\n" . '.blk-img--bottom { margin:28px 0 0 }'
            . "\n" . '.blk-img .img-frame { border-radius:20px; overflow:hidden; box-shadow:0 12px 40px rgba(15,23,42,.10); position:relative }'
            . "\n" . '.blk-img .img-frame img { display:block; width:100%; max-height:420px; object-fit:cover; transition:transform .5s ease }'
            . "\n" . '.blk-img .img-frame:hover img { transform:scale(1.02) }'
            . "\n" . '.blk-img-caption { display:block; text-align:center; font-size:.85rem; color:var(--muted); font-style:italic; margin-top:10px; line-height:1.4 }'

            /* ── UNIVERSAL INLINE IMAGE CARD ── */
            . "\n" . '.img-card { border-radius:20px; overflow:visible; background:transparent; border:none }'
            . "\n" . '.img-card--right { float:right; margin:4px 0 28px 36px; max-width:340px; width:42% }'
            . "\n" . '.img-card--left  { float:left;  margin:4px 36px 28px 0; max-width:340px; width:42% }'
            . "\n" . '.img-card--center { display:block; clear:both; margin:28px auto; max-width:600px }'
            . "\n" . '.img-card--full   { display:block; clear:both; margin:28px 0; width:100%; max-width:100% }'
            . "\n" . '.img-card .img-frame { border-radius:20px; overflow:hidden; box-shadow:0 16px 56px rgba(15,23,42,.12) }'
            . "\n" . '.img-card .img-frame img { max-height:380px; object-fit:cover }'
            . "\n" . '.img-card-caption { padding:10px 4px 0; font-size:12.5px; color:var(--muted); text-align:center; font-style:italic; line-height:1.4 }'
            . "\n" . '[data-theme="dark"] .img-card .img-frame { box-shadow:0 16px 56px rgba(0,0,0,.35) }'
            . "\n" . '[data-theme="dark"] .blk-img .img-frame { box-shadow:0 12px 40px rgba(0,0,0,.3) }'
            . "\n" . '[data-theme="dark"] .hero-img-frame { box-shadow:0 24px 80px rgba(0,0,0,.4) }'

            /* ── SHARED ── */
            . "\n" . '.sec-desc{font-size:15px;color:var(--muted);margin-bottom:1.5em;line-height:1.6}'
            . "\n" . '.btn-primary{display:inline-block;padding:14px 28px;border-radius:12px;background:var(--blue);color:#fff;font-family:var(--fh);font-size:15px;font-weight:700;text-decoration:none;transition:all .2s}'
            . "\n" . '.btn-primary:hover{background:var(--blue-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(37,99,235,.25);text-decoration:none;color:#fff}'

            /* ── ANIMATIONS ── */
            . "\n" . '@keyframes fadeUp { from { opacity:0; transform:translateY(24px) } to { opacity:1; transform:none } }'
            . "\n" . '@keyframes shimmer { 0% { transform:translateX(-100%) } 50%,100% { transform:translateX(100%) } }'
            . "\n" . '@keyframes pDrift1  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(45px, 55px)  scale(1.07)} }'
            . "\n" . '@keyframes pDrift2  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-50px,-42px) scale(1.05)} }'
            . "\n" . '@keyframes pDrift3  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(32px,-58px) scale(1.08)} }'
            . "\n" . '@keyframes pDrift4  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-38px,48px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift5  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(60px,-30px) scale(1.09)} }'
            . "\n" . '@keyframes pDrift6  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-28px,62px) scale(1.04)} }'
            . "\n" . '@keyframes pDrift7  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(52px, 38px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift8  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-44px,-34px)scale(1.07)} }'
            . "\n" . '@keyframes pDrift9  { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(36px, 50px) scale(1.05)} }'
            . "\n" . '@keyframes pDrift10 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-55px,28px) scale(1.08)} }'
            . "\n" . '@keyframes pDrift11 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(42px,-46px) scale(1.06)} }'
            . "\n" . '@keyframes pDrift12 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(-32px,-52px)scale(1.05)} }'
            . "\n" . '@keyframes pDrift13 { 0%,100%{transform:translate(0,0)   scale(1)}    50%{transform:translate(58px, 44px) scale(1.07)} }'
            . "\n" . '@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}'
            . "\n" . '@keyframes pulseRing{0%,100%{box-shadow:0 0 0 0 var(--pulse-c,rgba(37,99,235,.3))}50%{box-shadow:0 0 0 8px transparent}}'
            . "\n" . '@keyframes spPulse{0%,100%{r:3;opacity:.3}50%{r:7;opacity:0}}'
            . "\n" . '@keyframes ra-shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}'

            /* ── RESPONSIVE (shared elements) ── */
            . "\n" . '@media(max-width:768px) {'
            .   'section { padding:56px 0 }'
            .   '.img-card--right,.img-card--left { float:none; margin:20px auto; max-width:100%; width:100% }'
            .   '.img-card--center { max-width:100% }'
            .   '.img-card .img-frame img { max-height:280px }'
            .   '.blk-img,.blk-img--bottom { margin:20px 0 }'
            .   '.blk-img .img-frame img { max-height:260px }'
            .   '.section-divider-line { width:calc(100% - 48px) }'
            . '}'
            . "\n" . '@media(max-width:414px) {'
            .   '.mac-body { padding:16px }'
            .   '.img-card .img-frame img { max-height:220px }'
            .   '.blk-img .img-frame img { max-height:200px }'
            . '}';
    }

    public function getFontLinks(): string
    {
        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . '<link href="https://fonts.googleapis.com/css2?family=Geologica:wght@300;400;500;700;900&family=Onest:wght@300;400;500&display=swap" rel="stylesheet">';
    }

    public function getBodyClass(): string
    {
        return '';
    }
}
