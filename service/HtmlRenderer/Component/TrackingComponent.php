<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class TrackingComponent implements ComponentInterface
{
    /** @var int */
    private $articleId;

    public function __construct(int $articleId)
    {
        $this->articleId = $articleId;
    }

    public function renderHtml(array $context): string
    {
        return '';
    }

    public function getCss(): string
    {
        return '';
    }

    public function getJs(): string
    {
        $trackUrl = json_encode(SEO_TRACK_SCRIPT);
        $aidJs    = $this->articleId;

        return
            // Visit tracking via sendBeacon on page load
            '(function(){'
            . 'var TRACK=' . $trackUrl . ',AID=' . $aidJs . ';'
            . 'if(!AID)return;'
            . 'if(document.readyState==="loading"){'
            .   'document.addEventListener("DOMContentLoaded",function(){navigator.sendBeacon(TRACK+"?aid="+AID);});'
            . '}else{'
            .   'navigator.sendBeacon(TRACK+"?aid="+AID);'
            . '}'
            // Link click tracking (external links via search.php)
            . 'document.addEventListener("click",function(e){'
            .   'var a=e.target.closest("a[href]");'
            .   'if(!a)return;'
            .   'var href=a.getAttribute("href");'
            .   'if(href&&href.indexOf("search.php?link=")!==-1){'
            .     'navigator.sendBeacon(TRACK+"?aid="+AID+"&type=link_click&href="+encodeURIComponent(href));'
            .   '}'
            . '});'
            . '})();'

            // Theme init (read from localStorage)
            . "\n" . '(function(){'
            . 'var t=localStorage.getItem("sl-theme");'
            . 'if(t)document.documentElement.setAttribute("data-theme",t);'
            . '})();'

            // Scroll reveal IntersectionObserver
            . "\n" . '(function(){'
            . 'var obs=new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(e,i){'
            . 'if(e.isIntersecting){setTimeout(function(){e.target.classList.add("vis")},i*60);obs.unobserve(e.target)}'
            . '})},{threshold:.08});'
            . 'document.querySelectorAll(".reveal").forEach(function(el){obs.observe(el)});'
            . '})();'

            // Bar-fill IntersectionObserver
            . "\n" . '(function(){'
            . 'var bo=new IntersectionObserver(function(entries){'
            . 'entries.forEach(function(e){'
            . 'if(e.isIntersecting){e.target.style.width=e.target.dataset.width+"%";bo.unobserve(e.target)}'
            . '})},{threshold:.2});'
            . 'document.querySelectorAll(".bar-fill").forEach(function(el){bo.observe(el)});'
            . '})();';
    }
}
