<?php

declare(strict_types=1);

namespace Seo\Service\HtmlRenderer\Component;

class ParallaxComponent implements ComponentInterface
{
    public function renderHtml(array $context): string
    {
        return '<div id="parallax-layer" aria-hidden="true">'
            . '<div class="p-orb p-orb--1"></div>'
            . '<div class="p-orb p-orb--2"></div>'
            . '<div class="p-orb p-orb--3"></div>'
            . '<div class="p-orb p-orb--4"></div>'
            . '<div class="p-orb p-orb--5"></div>'
            . '<div class="p-orb p-orb--6"></div>'
            . '<div class="p-orb p-orb--7"></div>'
            . '<div class="p-orb p-orb--8"></div>'
            . '<div class="p-orb p-orb--9"></div>'
            . '<div class="p-orb p-orb--10"></div>'
            . '<div class="p-orb p-orb--11"></div>'
            . '<div class="p-orb p-orb--12"></div>'
            . '<div class="p-orb p-orb--13"></div>'
            . '</div>';
    }

    public function getCss(): string
    {
        return '#parallax-layer { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden }'

            /* Base orb shape */
            . "\n" . '.p-orb { position:absolute; border-radius:50%; will-change:transform }'

            /* Light mode: orbs are soft pastels */
            . "\n" . '.p-orb--1  { width:min(72vw,760px); height:min(72vw,760px); top:-8%;   left:-12%;  background:radial-gradient(circle,rgba(37,99,235,.28) 0%,rgba(37,99,235,.06) 90%,transparent 72%); filter:blur(80px); animation:pDrift1 22s ease-in-out infinite }'
            . "\n" . '.p-orb--2  { width:min(60vw,620px); height:min(60vw,620px); top:10%;   right:-14%; background:radial-gradient(circle,rgba(13,148,136,.24) 0%,rgba(13,148,136,.05) 90%,transparent 72%); filter:blur(70px); animation:pDrift2 28s ease-in-out infinite }'
            . "\n" . '.p-orb--3  { width:min(50vw,520px); height:min(50vw,520px); top:35%;   left:18%;   background:radial-gradient(circle,rgba(139,92,246,.22) 0%,rgba(139,92,246,.05) 90%,transparent 72%); filter:blur(75px); animation:pDrift3 19s ease-in-out infinite }'
            . "\n" . '.p-orb--4  { width:min(45vw,460px); height:min(45vw,460px); top:55%;   right:-8%;  background:radial-gradient(circle,rgba(236,72,153,.2)  0%,rgba(236,72,153,.04) 90%,transparent 72%); filter:blur(70px); animation:pDrift4 24s ease-in-out infinite }'
            . "\n" . '.p-orb--5  { width:min(40vw,400px); height:min(40vw,400px); top:75%;   left:-5%;   background:radial-gradient(circle,rgba(249,115,22,.22)  0%,rgba(249,115,22,.05) 90%,transparent 72%); filter:blur(65px); animation:pDrift5 31s ease-in-out infinite }'
            . "\n" . '.p-orb--6  { width:min(38vw,380px); height:min(38vw,380px); top:88%;   right:20%;  background:radial-gradient(circle,rgba(37,99,235,.18)  0%,rgba(37,99,235,.04) 90%,transparent 72%); filter:blur(72px); animation:pDrift6 20s ease-in-out infinite }'
            . "\n" . '.p-orb--7  { width:min(35vw,340px); height:min(35vw,340px); top:5%;    left:40%;   background:radial-gradient(circle,rgba(13,148,136,.18)  0%,rgba(13,148,136,.04) 90%,transparent 72%); filter:blur(68px); animation:pDrift7 26s ease-in-out infinite }'
            . "\n" . '.p-orb--8  { width:min(44vw,440px); height:min(44vw,440px); top:25%;   right:30%;  background:radial-gradient(circle,rgba(139,92,246,.15)  0%,rgba(139,92,246,.03) 90%,transparent 72%); filter:blur(76px); animation:pDrift8 17s ease-in-out infinite }'
            . "\n" . '.p-orb--9  { width:min(32vw,300px); height:min(32vw,300px); top:48%;   left:55%;   background:radial-gradient(circle,rgba(22,163,74,.18)   0%,rgba(22,163,74,.04)  90%,transparent 72%); filter:blur(64px); animation:pDrift9 33s ease-in-out infinite }'
            . "\n" . '.p-orb--10 { width:min(36vw,360px); height:min(36vw,360px); top:65%;   left:30%;   background:radial-gradient(circle,rgba(37,99,235,.16)  0%,rgba(37,99,235,.03) 90%,transparent 72%); filter:blur(70px); animation:pDrift10 21s ease-in-out infinite }'
            . "\n" . '.p-orb--11 { width:min(30vw,280px); height:min(30vw,280px); top:80%;   right:40%;  background:radial-gradient(circle,rgba(236,72,153,.14) 0%,rgba(236,72,153,.03) 90%,transparent 72%); filter:blur(62px); animation:pDrift11 29s ease-in-out infinite }'
            . "\n" . '.p-orb--12 { width:min(28vw,260px); height:min(28vw,260px); top:18%;   left:70%;   background:radial-gradient(circle,rgba(249,115,22,.16) 0%,rgba(249,115,22,.03) 90%,transparent 72%); filter:blur(60px); animation:pDrift12 23s ease-in-out infinite }'
            . "\n" . '.p-orb--13 { width:min(55vw,560px); height:min(55vw,560px); top:42%;   right:-18%; background:radial-gradient(circle,rgba(13,148,136,.16) 0%,rgba(13,148,136,.03) 90%,transparent 72%); filter:blur(82px); animation:pDrift13 36s ease-in-out infinite }'

            /* Dark mode: orbs are much brighter */
            . "\n" . '[data-theme="dark"] .p-orb--1  { background:radial-gradient(circle,rgba(96,165,250,.55)  0%,rgba(37,99,235,.18)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--2  { background:radial-gradient(circle,rgba(45,212,191,.5)   0%,rgba(13,148,136,.15) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--3  { background:radial-gradient(circle,rgba(196,181,253,.52) 0%,rgba(139,92,246,.16) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--4  { background:radial-gradient(circle,rgba(244,114,182,.48) 0%,rgba(236,72,153,.14) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--5  { background:radial-gradient(circle,rgba(251,146,60,.52)  0%,rgba(249,115,22,.15) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--6  { background:radial-gradient(circle,rgba(96,165,250,.4)   0%,rgba(37,99,235,.12)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--7  { background:radial-gradient(circle,rgba(45,212,191,.42)  0%,rgba(13,148,136,.12) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--8  { background:radial-gradient(circle,rgba(196,181,253,.38) 0%,rgba(139,92,246,.1)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--9  { background:radial-gradient(circle,rgba(74,222,128,.42)  0%,rgba(22,163,74,.12)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--10 { background:radial-gradient(circle,rgba(96,165,250,.38)  0%,rgba(37,99,235,.1)   50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--11 { background:radial-gradient(circle,rgba(244,114,182,.36) 0%,rgba(236,72,153,.1)  50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--12 { background:radial-gradient(circle,rgba(251,146,60,.4)   0%,rgba(249,115,22,.11) 50%,transparent 72%) }'
            . "\n" . '[data-theme="dark"] .p-orb--13 { background:radial-gradient(circle,rgba(45,212,191,.38)  0%,rgba(13,148,136,.1)  50%,transparent 72%) }';
    }

    public function getJs(): string
    {
        return '(function(){'
            . 'var cfg=['
            . '{s:".p-orb--1", y:0.14, x:0.03},'
            . '{s:".p-orb--2", y:-0.10,x:-0.04},'
            . '{s:".p-orb--3", y:0.20, x:0.06},'
            . '{s:".p-orb--4", y:-0.15,x:0.05},'
            . '{s:".p-orb--5", y:0.08, x:-0.03},'
            . '{s:".p-orb--6", y:-0.18,x:0.07},'
            . '{s:".p-orb--7", y:0.12, x:-0.05},'
            . '{s:".p-orb--8", y:-0.08,x:0.04},'
            . '{s:".p-orb--9", y:0.22, x:0.02},'
            . '{s:".p-orb--10",y:-0.12,x:-0.06},'
            . '{s:".p-orb--11",y:0.16, x:0.04},'
            . '{s:".p-orb--12",y:-0.06,x:0.08},'
            . '{s:".p-orb--13",y:0.10, x:-0.02}'
            . '];'
            . 'var orbs=cfg.map(function(c){return{el:document.querySelector(c.s),y:c.y,x:c.x}});'
            . 'var ticking=false;'
            . 'function applyParallax(){'
            . 'var sy=window.scrollY;'
            . 'orbs.forEach(function(o){'
            . 'if(o.el)o.el.style.transform="translate("+(sy*o.x)+"px,"+(sy*o.y)+"px)"'
            . '});'
            . 'ticking=false'
            . '}'
            . 'window.addEventListener("scroll",function(){'
            . 'if(!ticking){requestAnimationFrame(applyParallax);ticking=true}'
            . '},{passive:true});'
            . '})();';
    }
}
