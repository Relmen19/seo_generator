<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class NavbarComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        $a           = $context['article'] ?? [];
        $tpl         = $context['template'] ?? [];
        $siteProfile = $context['siteProfile'] ?? [];
        $navSearch   = $context['navSearch'] ?? '';

        $logo = '/uploads/' . $siteProfile['icon_path'] ?? SEO_DEFAULT_LOGO_URL;
        $brandName = $siteProfile['brand_name'] ?? 'SEO Generator';

        // Split brand name for accent styling (first word normal, rest accented)
        $brandParts = explode(' ', $brandName, 2);
        $brandHtml = $this->e($brandParts[0]);
        if (isset($brandParts[1])) {
            $brandHtml .= '<span class="nav-accent">' . $this->e($brandParts[1]) . '</span>';
        }

        /* Navbar links: all hrefs use {{link:KEY}} placeholders
           so they go through replaceLinkPlaceholders() -> search.php tracking */
        $navItems = $tpl['nav_items'] ?? null;
        $navLinksHtml = '<a href="{{link:home}}">Главная</a>';
        if (is_string($navItems)) {
            $navItems = json_decode($navItems, true);
        }
        if (is_array($navItems) && !empty($navItems)) {
            $navLinksHtml = '';
            foreach ($navItems as $ni) {
                $niLabel = $this->e($ni['label'] ?? '');
                $niKey   = $ni['link_key'] ?? '';
                if ($niLabel && $niKey) {
                    $navLinksHtml .= '<a href="{{link:' . $this->e($niKey) . '}}">' . $niLabel . '</a>';
                }
            }
        }

        $logoHtml = $logo ? '<span class="nav-logo-icon"><img src="' . $logo . '" alt=""></span>' : '';

        return '<header class="navbar" id="navbar">'
            . '<div class="navbar-inner">'
            . '<a href="{{link:home}}" class="nav-logo">' . $logoHtml . $brandHtml . '</a>'
            . '<nav class="nav-links">' . $navLinksHtml . $navSearch . '</nav>'
            . '<button id="theme-toggle" class="theme-toggle" aria-label="Переключить тему">🌙</button>'
            . '</div></header>';
    }

    public function getCss(): string
    {
        return '.navbar { position:fixed; top:0; left:0; right:0; z-index:200; background:rgba(248,250,252,.82); backdrop-filter:blur(24px) saturate(180%); -webkit-backdrop-filter:blur(24px) saturate(180%); border-bottom:1px solid var(--color-border); transition:box-shadow .3s,background .4s }'
            . "\n" . '[data-theme="dark"] .navbar { background:rgba(5,13,26,.84) }'
            . "\n" . '.navbar.scrolled { box-shadow:0 1px 0 var(--color-border) }'
            . "\n" . '.navbar-inner { max-width:1200px; margin:0 auto; padding:14px 32px; display:flex; align-items:center; justify-content:space-between }'
            . "\n" . '.nav-logo { font-family:var(--type-font-heading); font-size:20px; font-weight:900; color:var(--color-text); text-decoration:none; display:flex; align-items:center; gap:8px }'
            . "\n" . '.nav-logo:hover { text-decoration:none }'
            . "\n" . '.nav-logo-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:16px }'
            . "\n" . '.nav-accent { color:var(--color-accent) }'
            . "\n" . '.nav-links { align-items: center; display:flex; gap:28px }'
            . "\n" . '.nav-links a { font-size:14px; color:var(--color-text-3); text-decoration:none; font-weight:500; transition:color .2s }'
            . "\n" . '.nav-links a:hover { color:var(--color-text); text-decoration:none }'
            . "\n" . '.theme-toggle { background:none; border:1px solid var(--color-border); border-radius:100px; width:40px; height:40px; font-size:17px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .2s; flex-shrink:0 }'
            . "\n" . '.theme-toggle:hover { background:var(--color-accent-soft); border-color:rgba(37,99,235,.3); transform:scale(1.08) }'
            . "\n" . '@media(max-width:768px) {'
            .   '.navbar-inner { padding:12px 16px }'
            .   '.nav-links { display:none }'
            . '}';
    }

    public function getJs(): string
    {
        return 'window.addEventListener("scroll",function(){'
            . 'document.getElementById("navbar").classList.toggle("scrolled",window.scrollY>50)'
            . '});'

            . "\n" . '(function(){'
            . 'var btn=document.getElementById("theme-toggle");'
            . 'if(!btn)return;'
            . 'var html=document.documentElement;'
            . 'function updateIcon(){'
            . 'btn.textContent=html.getAttribute("data-theme")==="dark"?"☀️":"🌙"'
            . '}'
            . 'updateIcon();'
            . 'btn.addEventListener("click",function(){'
            . 'var isDark=html.getAttribute("data-theme")==="dark";'
            . 'var next=isDark?"light":"dark";'
            . 'html.setAttribute("data-theme",next);'
            . 'localStorage.setItem("sl-theme",next);'
            . 'updateIcon()'
            . '});'
            . '})();';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
