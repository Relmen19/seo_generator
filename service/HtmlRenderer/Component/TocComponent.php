<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

use Seo\Service\Editorial\TextExtractor;
use Seo\Service\HtmlRenderer\BlockRendererInterface;

class TocComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        /** @var array $blocks */
        $blocks = $context['blocks'] ?? [];
        /** @var array<string, BlockRendererInterface> $blockRenderers */
        $blockRenderers = $context['blockRenderers'] ?? [];

        $items = [];
        foreach ($blocks as $b) {
            $bid     = 'block-' . $b['id'];
            $content = TextExtractor::blockContent($b);
            $type    = $b['type'] ?? '';

            $label = '';
            if (isset($blockRenderers[$type])) {
                $label = $blockRenderers[$type]->getTocLabel($content, $b);
            }
            if ($label === '') {
                $label = $b['name'] ?? $type;
            }

            if (mb_strlen($label) > 26) {
                $label = mb_substr($label, 0, 24) . "\xe2\x80\xa6";
            }

            $items[] = '<a href="#' . $this->e($bid) . '" class="toc-link" data-target="' . $this->e($bid) . '">'
                . $this->e($label) . '</a>';
        }

        if (empty($items)) {
            return '';
        }

        return '<nav class="toc" id="toc">'
            . '<div class="toc-inner">'
            . '<div class="toc-label">Содержание</div>'
            . implode('', $items)
            . '</div></nav>';
    }

    public function getCss(): string
    {
        return '.toc { position:fixed; left:16px; top:50%; transform:translateY(-50%); z-index:100; opacity:.12; transition:opacity .4s ease; width:160px }'
            . "\n" . '.toc:hover { opacity:.96 }'
            . "\n" . '.toc-inner { background:rgba(248,250,252,.88); backdrop-filter:blur(20px); border:1px solid var(--border); border-radius:12px; padding:14px 10px; box-shadow:0 4px 24px rgba(15,23,42,.06) }'
            . "\n" . '[data-theme="dark"] .toc-inner { background:rgba(5,13,26,.88) }'
            . "\n" . '.toc-label { font-family:var(--fh); font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:2px; color:var(--muted); margin-bottom:10px; padding:0 8px }'
            . "\n" . '.toc-link { display:block; font-size:11.5px; color:var(--muted); padding:5px 8px; border-radius:6px; text-decoration:none; transition:all .2s; border-left:2px solid transparent; margin-bottom:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }'
            . "\n" . '.toc-link:hover { color:var(--dark); background:var(--blue-light); text-decoration:none }'
            . "\n" . '.toc-link.active { color:var(--blue); font-weight:600; border-left-color:var(--blue); background:var(--blue-light) }'
            . "\n" . '@media(max-width:1100px) { .toc { display:none } }';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'var toc=document.getElementById("toc");if(!toc)return;'
            . 'var links=toc.querySelectorAll(".toc-link");'
            . 'var secs=[];'
            . 'links.forEach(function(l){var t=document.getElementById(l.dataset.target);if(t)secs.push({el:t,link:l})});'
            . 'if(!secs.length)return;'
            . 'function upd(){'
            . 'var y=window.scrollY+140,cur=secs[0];'
            . 'secs.forEach(function(s){if(s.el.offsetTop<=y)cur=s});'
            . 'links.forEach(function(l){l.classList.remove("active")});'
            . 'cur.link.classList.add("active")'
            . '}'
            . 'window.addEventListener("scroll",upd);upd()'
            . '})();';
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
