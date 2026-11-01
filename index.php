<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Marketing landing — exact Navid home.html (self-contained CSS/SVG/JS).
 * Entry for `/` and `index.php`. Does NOT redirect to login.
 * No Characteristics/system-code section. Footer logo = element tile only.
 */
$owner = rrp_config_ready() ? rrp_current_owner() : null;
$canonical = rrp_config_ready() ? rrp_app_url() : 'https://sipaw.nakilab.com/';
$year = (int) date('Y');
?><!--
  Source of truth: Navid-uploaded home.html (SI^PAW logos, SIPO hero, features, demo).
  Footer: element-tile logo + Product / Company / Social / Blog.
-->
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>SIPAW: Your pet's SI companion</title>
<meta name="description" content="siPaw by Nakilab. Chat with SIPO, get reminders on time, and ask other owners. One app for every paw.">
<meta property="og:title" content="SIPAW: Your pet's SI companion">
<meta property="og:site_name" content="SIPAW">
<meta name="theme-color" content="#000000">
<link rel="canonical" href="<?= rrp_h($canonical) ?>">
<link rel="manifest" href="<?= rrp_h(rrp_asset('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" href="<?= rrp_h(rrp_asset('assets/icons/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= rrp_h(rrp_asset('assets/icons/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SIPAW">
<meta name="rrp-sw" content="<?= rrp_h(rrp_asset('sw.js')) ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@500;600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* siPaw home. Brandbook v1.0: black and white do the work, Si Blue on the primary action, focus, selection, live dot
   and SIPO's light. Square corners. Hero = SIPO with the pets, one bubble above SIPO that walks through the product. */
:root{
  --bg:#000000; --panel:#0A0A0B; --raised:#18181B; --line:#27272A; --line-hi:#3F3F46; --edge:#71717A;
  --text:#FFFFFF; --sub:#A1A1AA; --meta:#71717A;
  --blue:#2E6AFF; --blue-hover:#2459E0; --on-blue:#FFFFFF;
  --solid:#FFFFFF; --on-solid:#000000;
  --glow:rgba(46,106,255,.30); --glow-far:rgba(46,106,255,.08);
  --phone:#0A0A0B;
  --f-display:"Barlow","Arial Narrow",system-ui,sans-serif;
  --f-body:"Inter",system-ui,-apple-system,"Segoe UI",sans-serif;
  --f-data:"JetBrains Mono",ui-monospace,Menlo,monospace;
  --ease:cubic-bezier(.16,1,.3,1);
  --wrap:1200px;
  color-scheme:dark;
}
@media (prefers-color-scheme: light){:root:not([data-theme="dark"]){
  --bg:#FFFFFF; --panel:#FFFFFF; --raised:#F4F4F5; --line:#E4E4E7; --line-hi:#D4D4D8; --edge:#71717A;
  --text:#000000; --sub:#52525B; --meta:#71717A;
  --solid:#000000; --on-solid:#FFFFFF; --glow:rgba(46,106,255,.16); --glow-far:rgba(46,106,255,.05); --phone:#FFFFFF; color-scheme:light}}
:root[data-theme="light"]{
  --bg:#FFFFFF; --panel:#FFFFFF; --raised:#F4F4F5; --line:#E4E4E7; --line-hi:#D4D4D8; --edge:#71717A;
  --text:#000000; --sub:#52525B; --meta:#71717A;
  --solid:#000000; --on-solid:#FFFFFF; --glow:rgba(46,106,255,.16); --glow-far:rgba(46,106,255,.05); --phone:#FFFFFF; color-scheme:light}

*{box-sizing:border-box;margin:0}
html{background:var(--bg);scroll-behavior:smooth}
body{background:var(--bg);color:var(--text);font:400 17px/1.6 var(--f-body);-webkit-font-smoothing:antialiased;overflow-x:hidden}
img,svg{display:block;max-width:100%}
a{color:inherit}
button{font:inherit;color:inherit;background:none;border:0;cursor:pointer;padding:0;text-align:inherit}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px}
h1,h2,h3{font-family:var(--f-display);font-weight:600;text-transform:uppercase;letter-spacing:.02em;line-height:.98;text-wrap:balance}
.wrap{max-width:var(--wrap);margin:0 auto;padding-left:max(24px,env(safe-area-inset-left,0px));padding-right:max(24px,env(safe-area-inset-right,0px))}
.label{font:500 13px/1 var(--f-display);letter-spacing:.24em;text-transform:uppercase;color:var(--sub)}
.data{font-family:var(--f-data);font-size:12px;font-weight:500;letter-spacing:.04em;font-variant-numeric:tabular-nums}
.skip{position:absolute;left:-999px;top:8px;background:var(--solid);color:var(--on-solid);padding:10px 14px;z-index:100}
.skip:focus{left:8px}
.vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}

/* logo: SI^PAW artwork from files. Header/nav ALWAYS endorsed (+ by NAKILAB). Element tile = system code / app icon. */
.logo-word{width:96px;height:auto;margin:-6px 0 -6px -12px;color:var(--text)}
.logo-endorsed{width:200px;height:auto;margin-left:-22px}
.nav .logo-endorsed{width:118px;height:auto;margin:-4px 0 -4px -14px}
.on-light{display:none}
@media (prefers-color-scheme: light){:root:not([data-theme="dark"]) .on-light{display:block}:root:not([data-theme="dark"]) .on-dark{display:none}}
:root[data-theme="light"] .on-light{display:block}
:root[data-theme="light"] .on-dark{display:none}
/* footer logo = element tile only (app icon). Header keeps endorsed wordmark. */
footer .foot-tile{width:min(160px,42vw);aspect-ratio:1;height:auto;display:block;border:1px solid var(--line);background:#000}
footer .foot-tile img{width:100%;height:100%;object-fit:contain;display:block}
/* demo notif: SI system squircle chip */
.push .ap{border-radius:10px;letter-spacing:.04em}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:52px;padding:0 28px;font:600 14px/1 var(--f-display);letter-spacing:.14em;text-transform:uppercase;text-decoration:none;border:1px solid var(--text);transition:background-color .2s,color .2s,border-color .2s,transform .15s var(--ease)}
.btn:active{transform:scale(.98)}
.btn.primary{background:var(--blue);border-color:var(--blue);color:var(--on-blue)}
.btn.primary:hover{background:var(--blue-hover);border-color:var(--blue-hover)}
.btn.outline:hover{background:var(--solid);color:var(--on-solid)}
.btn svg{width:16px;height:16px}
.btn.sm{min-height:44px;padding:0 18px;font-size:12px}

/* ---------- nav ---------- */
.nav{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 82%,transparent);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
.nav .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;height:72px}
.nav ul{display:flex;gap:32px;list-style:none;padding:0}
.nav ul a{font:600 12px/1 var(--f-display);letter-spacing:.16em;text-transform:uppercase;text-decoration:none;color:var(--sub);transition:color .2s}
.nav ul a:hover{color:var(--text)}
.nav .acts{display:flex;gap:10px}

/* ---------- hero ---------- */
.hero{position:relative;padding:48px 0 88px;border-bottom:1px solid var(--line);overflow:hidden}
.hero::before{content:"";position:absolute;right:-10%;top:-10%;width:80%;height:120%;background:radial-gradient(closest-side,var(--glow-far),transparent);pointer-events:none}
.hero .wrap{position:relative;display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:48px;align-items:center}
.intro{display:flex;flex-direction:column;gap:24px;min-width:0}
.hero h1{font-size:clamp(44px,5.8vw,82px)}
.hero h1 span{color:var(--sub)}
.lede{font-size:clamp(17px,1.5vw,19px);color:var(--sub);max-width:30rem}
.ctas{display:flex;gap:12px;flex-wrap:wrap}

.scene{position:relative;display:flex;flex-direction:column;align-items:center;cursor:pointer;-webkit-tap-highlight-color:transparent;padding-top:4px}
.scene::before{content:"";position:absolute;left:50%;top:52%;width:118%;aspect-ratio:1;translate:-50% -50%;background:radial-gradient(closest-side,var(--glow),var(--glow-far) 55%,transparent 75%);pointer-events:none}
.bubble{position:relative;z-index:2;width:min(560px,100%);border:1px solid color-mix(in srgb,var(--blue) 55%,var(--line-hi));background:color-mix(in srgb,var(--panel) 92%,transparent);padding:18px 22px 14px;display:flex;flex-direction:column;gap:10px;margin-bottom:22px}
.bubble::after{content:"";position:absolute;left:50%;bottom:-9px;width:16px;height:16px;margin-left:-8px;background:var(--panel);border-right:1px solid color-mix(in srgb,var(--blue) 55%,var(--line-hi));border-bottom:1px solid color-mix(in srgb,var(--blue) 55%,var(--line-hi));transform:rotate(45deg)}
.bubble .who{display:flex;align-items:center;justify-content:space-between;gap:12px}
.bubble .name{display:inline-flex;align-items:center;gap:10px;font:600 12px/1 var(--f-display);letter-spacing:.2em;text-transform:uppercase}
.bubble .name i{width:8px;height:8px;border-radius:50%;background:var(--blue);animation:live 1.8s ease-out infinite}
.bubble .tag{color:var(--sub)}
.bubble p.say{font:500 clamp(18px,1.8vw,22px)/1.42 var(--f-body);letter-spacing:-.01em;min-height:2.84em}
.bubble p.say .caret{display:inline-block;width:2px;height:1em;background:var(--blue);vertical-align:-.14em;margin-left:2px;animation:caret 1s steps(1) infinite}
.bubble .typing{display:none;gap:6px;align-items:center;height:2.84em}
.bubble.thinking .typing{display:flex}
.bubble.thinking p.say{display:none}
.bubble .typing i{width:8px;height:8px;border-radius:50%;background:var(--sub);animation:dot 1s ease-in-out infinite}
.bubble .typing i:nth-child(2){animation-delay:.15s}.bubble .typing i:nth-child(3){animation-delay:.3s}
.bubble .timer{height:2px;background:var(--line);overflow:hidden}
.bubble .timer i{display:block;height:100%;width:100%;background:var(--blue);transform-origin:left;transform:scaleX(0)}

.cast{position:relative;z-index:1;width:100%;max-width:600px;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.7fr) minmax(0,1fr);align-items:end}
.pet{display:flex;flex-direction:column;align-items:center;gap:10px;transition:transform .4s var(--ease)}
.pet svg{width:100%;max-width:170px;height:auto;filter:drop-shadow(0 16px 26px rgba(0,0,0,.45))}
.pet .tagline{display:flex;flex-direction:column;align-items:center;gap:4px;border:1px solid var(--line);background:var(--bg);padding:6px 10px;min-width:112px}
.pet .tagline b{font:600 13px/1 var(--f-display);letter-spacing:.16em;text-transform:uppercase}
.pet .tagline span{color:var(--sub);font-size:10px}
.pet.hi{transform:translateY(-8px)}
.pet.hi .tagline{border-color:var(--blue)}
.sipo{position:relative;width:100%;aspect-ratio:1;margin-bottom:28px}
/* only the current face (and the one fading out) is rendered, so one animation plays at a time */
.sipo .face{position:absolute;inset:0;display:none;opacity:0;transform:scale(.97);transition:opacity .28s var(--ease),transform .36s var(--ease)}
.sipo .face.on,.sipo .face.leaving{display:block}
.sipo .face.on.in{opacity:1;transform:none}
.sipo .face .still{display:none}
@media (prefers-reduced-motion:reduce){.sipo .face .anim{display:none}.sipo .face .still{display:block}}
.sipo .face img,.sipo .face svg{width:100%;height:100%;object-fit:contain;filter:drop-shadow(0 18px 40px rgba(46,106,255,.28))}
.floor{grid-column:1/-1;height:1px;margin-top:-1px;background:linear-gradient(90deg,transparent,var(--line-hi),transparent)}
.steps{display:flex;justify-content:center;gap:4px;list-style:none;padding:0;margin-top:14px;position:relative;z-index:2;max-width:100%}
.steps li{flex:0 1 44px;width:44px;min-width:0}
.steps button{width:100%;height:44px;display:grid;place-items:center}
.steps button i{display:block;width:24px;height:3px;background:var(--line-hi);transition:background-color .2s,width .3s var(--ease)}
.steps button[aria-current="step"] i{background:var(--blue);width:34px}
.steps button:hover i{background:var(--edge)}
@keyframes live{0%{box-shadow:0 0 0 0 rgba(46,106,255,.5)}100%{box-shadow:0 0 0 8px rgba(46,106,255,0)}}
@keyframes caret{50%{opacity:0}}
@keyframes dot{0%,100%{transform:translateY(0);opacity:.5}50%{transform:translateY(-4px);opacity:1}}
@keyframes rise{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

/* ---------- sections ---------- */
section.block{padding:128px 0;border-bottom:1px solid var(--line)}
.head{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;margin-bottom:48px}
.head h2{font-size:clamp(36px,4.6vw,64px);margin-top:18px;max-width:16ch}
.head p{max-width:34rem;color:var(--sub)}

/* what SIPO does: each card is one of SIPO's lines */
.fgrid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));border:1px solid var(--line)}
.fcard{position:relative;display:flex;flex-direction:column;gap:14px;padding:28px 22px 26px;min-height:300px;transition:background-color .25s}
.fcard + .fcard{border-left:1px solid var(--line)}
.fcard:hover{background:var(--panel)}
.fcard .ix{color:var(--meta)}
.fcard .face-mini{width:72px;height:72px;margin:6px 0 4px}
.fcard h3{font-size:22px}
.fcard p{font-size:15px;color:var(--sub)}
.fcard .go{margin-top:auto;font:600 11px/1 var(--f-display);letter-spacing:.18em;text-transform:uppercase;color:var(--sub);display:inline-flex;gap:8px;align-items:center}
.fcard::after{content:"";position:absolute;left:-1px;right:-1px;top:-1px;height:2px;background:var(--blue);transform:scaleX(0);transform-origin:left;transition:transform .35s var(--ease)}
.fcard[aria-pressed="true"]::after{transform:scaleX(1)}
.fcard[aria-pressed="true"] .go{color:var(--text)}

/* software with pets inside */
.mocks{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px;list-style:none;padding:0}
.mock{border:1px solid var(--line-hi);background:var(--panel);display:flex;flex-direction:column}
.mock .chrome{display:flex;align-items:center;gap:7px;height:44px;padding:0 16px;border-bottom:1px solid var(--line-hi)}
.mock .chrome i{width:9px;height:9px;border-radius:50%;background:var(--line-hi)}
.mock .chrome i:last-of-type{background:var(--blue)}
.mock .chrome span{margin-left:12px;color:var(--sub)}
.mock .body{position:relative;padding:28px 24px 30px;display:flex;flex-direction:column;gap:12px;min-height:250px}
.mock .ico{width:28px;height:28px;color:var(--blue)}
.mock .pets{position:absolute;right:18px;top:18px;display:flex;align-items:flex-end}
.mock .pets svg,.mock .pets img{width:76px;height:76px}
.mock .pets svg + svg{width:56px;height:56px;margin-left:-14px}
.mock h3{font:600 20px/1.05 var(--f-display);letter-spacing:.16em;margin-top:20px;max-width:10ch}
.mock p{font-size:15px;color:var(--sub)}

/* demo: the app, playing itself */
.demo{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:72px;align-items:center}
.dsteps{list-style:none;padding:0;display:flex;flex-direction:column;border-top:1px solid var(--line)}
.dsteps button{width:100%;display:grid;grid-template-columns:44px 1fr;gap:6px 14px;padding:18px 0;border-bottom:1px solid var(--line);position:relative}
.dsteps button .n{grid-row:span 2;color:var(--meta);padding-top:3px}
.dsteps button b{font:600 18px/1.1 var(--f-display);letter-spacing:.08em;text-transform:uppercase;color:var(--sub);transition:color .2s}
.dsteps button small{color:var(--sub);font-size:14px}
.dsteps button .bar{position:absolute;left:0;bottom:-1px;height:2px;width:100%;background:var(--blue);transform-origin:left;transform:scaleX(0)}
.dsteps button[aria-current="true"] b{color:var(--text)}
.dsteps button[aria-current="true"] .n{color:var(--blue)}

.phone{width:340px;height:690px;border-radius:50px;background:#18181B;padding:10px;box-shadow:0 0 0 1px #27272A,0 40px 90px rgba(0,0,0,.5);flex:none;position:relative}
.screen{position:relative;width:100%;height:100%;border-radius:40px;overflow:hidden;background:var(--bg);display:flex;flex-direction:column;border:1px solid var(--line)}
.screen .notch{position:absolute;top:9px;left:50%;width:102px;height:28px;margin-left:-51px;border-radius:16px;background:#000;z-index:5}
.screen .status{height:46px;flex:none;display:flex;align-items:flex-end;justify-content:space-between;padding:0 26px 6px;font-size:13px;font-weight:600}
.view{position:absolute;inset:46px 0 0;display:flex;flex-direction:column;padding:10px 18px 18px;gap:12px;opacity:0;transform:translateX(18px);transition:opacity .3s var(--ease),transform .4s var(--ease);pointer-events:none}
.view.on{opacity:1;transform:none;pointer-events:auto}
.view .top{display:flex;align-items:center;justify-content:space-between}
.view .top h4{font:600 22px/1 var(--f-display);text-transform:uppercase;letter-spacing:.02em}
.view .mini-sipo{width:38px;height:38px;flex:none}
.petrow{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.petcard{border:1px solid var(--line);background:var(--panel);display:flex;flex-direction:column}
.petcard .art{height:86px;background:var(--raised);display:flex;align-items:flex-end;justify-content:center;overflow:hidden}
.petcard .art svg{width:96px;height:96px;margin-bottom:-8px}
.petcard .meta{padding:8px 10px}
.petcard .meta b{display:block;font:600 14px/1.1 var(--f-display);letter-spacing:.12em;text-transform:uppercase}
.petcard .meta span{font-size:10px;color:var(--sub)}
.list{display:flex;flex-direction:column;border-top:1px solid var(--line)}
.li{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line);font-size:13px}
.li > div{flex:1;min-width:0}
.li b{display:block;font-weight:500;font-size:13px}
.li small{color:var(--sub);font-size:11px}
.li .data{color:var(--sub);font-size:11px}
.dotx{width:8px;height:8px;border-radius:50%;background:var(--blue);flex:none}
.dotx.idle{background:transparent;border:1px solid var(--sub)}
.chat{display:flex;flex-direction:column;gap:8px;flex:1;min-height:0}
.msg{max-width:84%;padding:9px 12px;font-size:13px;line-height:1.45;border:1px solid var(--line);animation:rise .35s var(--ease) both}
.msg.me{align-self:flex-end;background:var(--solid);color:var(--on-solid);border-color:var(--solid)}
.msg.si{align-self:flex-start;background:var(--panel);display:flex;gap:8px;align-items:flex-start}
.msg.si svg,.msg.si img{width:26px;height:26px;flex:none}
.msg .dots{display:inline-flex;gap:4px;padding:6px 0}
.msg .dots i{width:6px;height:6px;border-radius:50%;background:var(--sub);animation:dot 1s ease-in-out infinite}
.msg .dots i:nth-child(2){animation-delay:.15s}.msg .dots i:nth-child(3){animation-delay:.3s}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{padding:7px 10px;border:1px solid var(--line-hi);font-size:11px;animation:rise .35s var(--ease) both}
.composer{margin-top:auto;display:flex;border:1px solid var(--edge);height:42px}
.composer span{flex:1;padding:0 12px;display:flex;align-items:center;font-size:12px;color:var(--meta)}
.composer i{width:42px;background:var(--blue);display:grid;place-items:center;color:#fff}
.lock{flex:1;display:flex;flex-direction:column;align-items:center;padding-top:28px;gap:4px;background:radial-gradient(120% 70% at 50% 0%,var(--glow-far),transparent)}
.lock .time{font:600 64px/1 var(--f-body);letter-spacing:-.03em}
.lock .date{font-size:13px;color:var(--sub);margin-bottom:24px}
.push{width:100%;border:1px solid var(--line-hi);background:color-mix(in srgb,var(--raised) 92%,transparent);border-radius:18px;padding:12px;display:flex;gap:10px;animation:rise .45s var(--ease) both}
.push .ap{width:34px;height:34px;flex:none;background:var(--blue);color:#fff;display:grid;place-items:center;font:600 11px/1 var(--f-display);border-radius:8px}
.push b{font-size:13px}
.push p{font-size:12px;color:var(--sub)}
.push .row{display:flex;gap:8px;margin-top:10px}
.push .row span{flex:1;text-align:center;padding:7px 0;border:1px solid var(--line-hi);font-size:11px;font-weight:600;border-radius:10px}
.push .row span.do{background:var(--blue);border-color:var(--blue);color:#fff}
.push.done{opacity:.6}
.done-note{font-size:12px;color:var(--sub);display:flex;gap:8px;align-items:center;margin-top:10px;animation:rise .35s var(--ease) both}
.post{border:1px solid var(--line);padding:10px 12px;display:flex;flex-direction:column;gap:6px;animation:rise .35s var(--ease) both}
.post .row{display:flex;justify-content:space-between;gap:8px;color:var(--sub);font-size:10px}
.post b{font-weight:500;font-size:13px}
.board-tabs{display:flex;gap:6px}
.board-tabs span{padding:6px 10px;border:1px solid var(--line-hi);font-size:11px}
.board-tabs span.on{background:var(--blue);border-color:var(--blue);color:#fff}

.final .wrap{display:flex;justify-content:space-between;align-items:flex-end;gap:32px;flex-wrap:wrap}
.final h2{font-size:clamp(40px,6vw,88px);max-width:12ch}
.final p{color:var(--sub);margin-top:16px;max-width:30rem}
footer{padding:48px 0 40px;border-top:1px solid var(--line)}
footer .wrap{display:block}
footer .foot-grid{display:grid;grid-template-columns:auto repeat(4,minmax(0,1fr));gap:32px 36px;align-items:start}
footer .foot-brand{width:max-content}
footer .foot-h{font-family:var(--f-data);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--edge);margin:0 0 14px}
footer .foot-col{display:flex;flex-direction:column;gap:10px;align-items:flex-start}
footer .foot-col a{color:var(--sub);font-size:14px;text-decoration:none;line-height:1.3}
footer .foot-col a:hover{color:var(--text)}
footer .social{display:flex;flex-wrap:wrap;gap:8px}
footer .social a{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid var(--line-hi);color:var(--text);background:transparent}
footer .social a:hover{border-color:var(--edge);background:var(--raised)}
footer .social svg{width:16px;height:16px;display:block}
footer .foot-bar{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-top:36px;padding-top:20px;border-top:1px solid var(--line)}
footer .foot-bar p{color:var(--meta);font-size:12px;margin:0}
@media (max-width:900px){
  footer .foot-grid{grid-template-columns:1fr 1fr;gap:28px 20px}
  footer .foot-brand{grid-column:1 / -1}
}
@media (max-width:520px){
  footer .foot-grid{grid-template-columns:1fr}
}

/* ---------- responsive ---------- */
@media (max-width:1080px){
  .fgrid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .fcard + .fcard{border-left:0}
  .fcard{border-bottom:1px solid var(--line);min-height:0}
  .fcard:nth-child(even){border-left:1px solid var(--line)}
  .mocks{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width:900px){
  .nav ul{display:none}
  .hero{padding:24px 0 64px}
  .hero .wrap{grid-template-columns:minmax(0,1fr);gap:24px}
  /* phones: headline, then the scene, then the one-shot steps and CTAs */
  .intro{display:contents}
  .hero h1{order:-2;font-size:clamp(32px,11vw,64px)}
  .lede{order:-1}
  .intro > .brand{display:none}
  .intro .ctas{order:2}
  .demo{grid-template-columns:1fr;gap:48px}
  .phone{justify-self:center}
  section.block{padding:96px 0}
}
@media (max-width:560px){
  .fgrid,.mocks{grid-template-columns:1fr}
  .fcard:nth-child(even){border-left:0}
  .nav .acts .btn.outline{display:none}
  .bubble{padding:16px 16px 12px}
  .pet .tagline{min-width:0;max-width:100%;padding:5px 6px;text-align:center}
  .pet .tagline span{font-size:9px;letter-spacing:0}
  .steps button i{width:100%;max-width:24px}
  .steps button[aria-current="step"] i{max-width:34px}
  .scene::before{width:100%}
  .phone{width:min(330px,100%);height:660px}
}
@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto}
  *,*::before,*::after{animation:none!important;transition-duration:.01ms!important}
}
</style>
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
  <symbol id="lg-word" viewBox="0 0 629 372"><path d="M84.00000000000001 242.4V234.90000000000003Q84.00000000000001 233.40000000000003 85.05000000000001 232.35000000000002Q86.10000000000002 231.3 87.60000000000002 231.3H114.30000000000001Q115.80000000000001 231.3 116.85000000000002 232.35000000000002Q117.90000000000002 233.40000000000003 117.90000000000002 234.90000000000003V240.60000000000002Q117.90000000000002 254.4 130.20000000000002 263.55Q142.5 272.7 163.8 272.7Q182.10000000000002 272.7 191.4 264.9Q200.7 257.1 200.7 244.8Q200.7 236.4 195.6 230.55Q190.5 224.70000000000002 181.05 219.90000000000003Q171.60000000000002 215.10000000000002 152.70000000000002 207.90000000000003Q131.40000000000003 200.10000000000002 118.20000000000003 193.20000000000002Q105.00000000000003 186.3 96.15000000000002 174.45000000000002Q87.30000000000001 162.60000000000002 87.30000000000001 144.9Q87.30000000000001 118.50000000000003 106.50000000000001 103.20000000000002Q125.70000000000002 87.9 157.8 87.9Q180.3 87.9 197.55 95.70000000000002Q214.8 103.50000000000003 224.4 117.45000000000002Q234.0 131.4 234.0 149.70000000000002V154.8Q234.0 156.3 232.95000000000002 157.35000000000002Q231.90000000000003 158.4 230.40000000000003 158.4H203.40000000000003Q201.90000000000003 158.4 200.85000000000002 157.35000000000002Q199.8 156.3 199.8 154.8V151.50000000000003Q199.8 137.4 188.25 127.65Q176.70000000000002 117.9 156.3 117.9Q139.8 117.9 130.65000000000003 124.80000000000001Q121.50000000000003 131.70000000000002 121.50000000000003 144.3Q121.50000000000003 153.3 126.30000000000003 159.0Q131.10000000000002 164.70000000000002 140.70000000000002 169.35000000000002Q150.3 174.0 170.40000000000003 181.20000000000002Q191.70000000000002 189.3 204.45 195.9Q217.2 202.5 226.35 214.35000000000002Q235.5 226.20000000000002 235.5 243.9Q235.5 270.90000000000003 215.55 286.8Q195.60000000000002 302.7 161.10000000000002 302.7Q137.70000000000002 302.7 120.30000000000001 295.2Q102.90000000000002 287.7 93.45000000000002 274.2Q84.00000000000001 260.7 84.00000000000001 242.4ZM 269.1 296.7 V 94.078 Q 269.1 92.578 270.15 91.528 Q 271.2 90.478 272.7 90.478 H 300.3 Q 301.8 90.478 302.85 91.528 Q 303.9 92.578 303.9 94.078 V 296.7 Q 303.9 298.2 302.85 299.25 Q 301.8 300.3 300.3 300.3 H 272.7 Q 271.2 300.3 270.15 299.25 Q 269.1 298.2 269.1 296.7 Z" fill="#2E6AFF"/><path d="M395.92199999999997 92.004Q395.92199999999997 98.12400000000001 393.27 102.714Q390.618 107.304 385.773 109.85400000000001Q380.928 112.40400000000001 374.604 112.40400000000001H356.652Q356.24399999999997 112.40400000000001 356.24399999999997 112.81200000000001V141.78Q356.24399999999997 142.8 355.224 142.8H347.472Q346.452 142.8 346.452 141.78V72.31800000000001Q346.452 71.298 347.472 71.298H375.012Q381.234 71.298 385.977 73.899Q390.71999999999997 76.5 393.32099999999997 81.19200000000001Q395.92199999999997 85.88400000000001 395.92199999999997 92.004ZM386.13 92.208Q386.13 86.59800000000001 382.71299999999997 83.18100000000001Q379.296 79.76400000000001 373.686 79.76400000000001H356.652Q356.24399999999997 79.76400000000001 356.24399999999997 80.172V104.04Q356.24399999999997 104.44800000000001 356.652 104.44800000000001H373.686Q379.296 104.44800000000001 382.71299999999997 101.08200000000001Q386.13 97.71600000000001 386.13 92.208ZM452.226 141.984 448.554 130.05Q448.452 129.744 448.146 129.744H419.484Q419.178 129.744 419.07599999999996 130.05L415.404 141.984Q415.098 142.8 414.18 142.8H405.918Q404.796 142.8 405.102 141.678L427.44 72.21600000000001Q427.746 71.4 428.664 71.4H438.864Q439.782 71.4 440.08799999999997 72.21600000000001L462.63 141.678L462.73199999999997 142.086Q462.73199999999997 142.8 461.81399999999996 142.8H453.45Q452.532 142.8 452.226 141.984ZM422.034 121.99200000000002H445.49399999999997Q445.698 121.99200000000002 445.851 121.83900000000001Q446.004 121.686 445.902 121.48200000000001L434.07 83.64000000000001Q433.96799999999996 83.436 433.764 83.436Q433.56 83.436 433.45799999999997 83.64000000000001L421.626 121.48200000000001Q421.524 121.686 421.677 121.83900000000001Q421.83 121.99200000000002 422.034 121.99200000000002ZM490.068 141.984 470.892 72.522 470.79 72.21600000000001Q470.79 71.4 471.81 71.4H480.072Q480.99 71.4 481.194 72.31800000000001L494.55600000000004 123.114Q494.658 123.31800000000001 494.81100000000004 123.31800000000001Q494.964 123.31800000000001 495.06600000000003 123.114L507.714 72.31800000000001Q507.918 71.4 508.836 71.4H516.894Q517.812 71.4 518.016 72.31800000000001L531.378 123.21600000000001Q531.48 123.42000000000002 531.582 123.42000000000002Q531.684 123.42000000000002 531.7860000000001 123.21600000000001L545.25 72.31800000000001Q545.454 71.4 546.3720000000001 71.4H554.124Q554.634 71.4 554.889 71.706Q555.144 72.012 555.042 72.522L536.58 141.882Q536.376 142.8 535.458 142.8H527.604Q526.686 142.8 526.482 141.882L513.12 89.25Q513.018 88.94400000000002 512.9159999999999 88.94400000000002Q512.814 88.94400000000002 512.712 89.25L499.75800000000004 141.882Q499.656 142.8 498.636 142.8H491.19Q490.272 142.8 490.068 141.984Z" fill="currentColor"/></symbol>
<symbol id="lg-endorsed-dark" viewBox="0 0 629 462"><path d="M84.00000000000001 242.4V234.90000000000003Q84.00000000000001 233.40000000000003 85.05000000000001 232.35000000000002Q86.10000000000002 231.3 87.60000000000002 231.3H114.30000000000001Q115.80000000000001 231.3 116.85000000000002 232.35000000000002Q117.90000000000002 233.40000000000003 117.90000000000002 234.90000000000003V240.60000000000002Q117.90000000000002 254.4 130.20000000000002 263.55Q142.5 272.7 163.8 272.7Q182.10000000000002 272.7 191.4 264.9Q200.7 257.1 200.7 244.8Q200.7 236.4 195.6 230.55Q190.5 224.70000000000002 181.05 219.90000000000003Q171.60000000000002 215.10000000000002 152.70000000000002 207.90000000000003Q131.40000000000003 200.10000000000002 118.20000000000003 193.20000000000002Q105.00000000000003 186.3 96.15000000000002 174.45000000000002Q87.30000000000001 162.60000000000002 87.30000000000001 144.9Q87.30000000000001 118.50000000000003 106.50000000000001 103.20000000000002Q125.70000000000002 87.9 157.8 87.9Q180.3 87.9 197.55 95.70000000000002Q214.8 103.50000000000003 224.4 117.45000000000002Q234.0 131.4 234.0 149.70000000000002V154.8Q234.0 156.3 232.95000000000002 157.35000000000002Q231.90000000000003 158.4 230.40000000000003 158.4H203.40000000000003Q201.90000000000003 158.4 200.85000000000002 157.35000000000002Q199.8 156.3 199.8 154.8V151.50000000000003Q199.8 137.4 188.25 127.65Q176.70000000000002 117.9 156.3 117.9Q139.8 117.9 130.65000000000003 124.80000000000001Q121.50000000000003 131.70000000000002 121.50000000000003 144.3Q121.50000000000003 153.3 126.30000000000003 159.0Q131.10000000000002 164.70000000000002 140.70000000000002 169.35000000000002Q150.3 174.0 170.40000000000003 181.20000000000002Q191.70000000000002 189.3 204.45 195.9Q217.2 202.5 226.35 214.35000000000002Q235.5 226.20000000000002 235.5 243.9Q235.5 270.90000000000003 215.55 286.8Q195.60000000000002 302.7 161.10000000000002 302.7Q137.70000000000002 302.7 120.30000000000001 295.2Q102.90000000000002 287.7 93.45000000000002 274.2Q84.00000000000001 260.7 84.00000000000001 242.4ZM 269.1 296.7 V 94.078 Q 269.1 92.578 270.15 91.528 Q 271.2 90.478 272.7 90.478 H 300.3 Q 301.8 90.478 302.85 91.528 Q 303.9 92.578 303.9 94.078 V 296.7 Q 303.9 298.2 302.85 299.25 Q 301.8 300.3 300.3 300.3 H 272.7 Q 271.2 300.3 270.15 299.25 Q 269.1 298.2 269.1 296.7 Z" fill="#2E6AFF"/><path d="M395.92199999999997 92.004Q395.92199999999997 98.12400000000001 393.27 102.714Q390.618 107.304 385.773 109.85400000000001Q380.928 112.40400000000001 374.604 112.40400000000001H356.652Q356.24399999999997 112.40400000000001 356.24399999999997 112.81200000000001V141.78Q356.24399999999997 142.8 355.224 142.8H347.472Q346.452 142.8 346.452 141.78V72.31800000000001Q346.452 71.298 347.472 71.298H375.012Q381.234 71.298 385.977 73.899Q390.71999999999997 76.5 393.32099999999997 81.19200000000001Q395.92199999999997 85.88400000000001 395.92199999999997 92.004ZM386.13 92.208Q386.13 86.59800000000001 382.71299999999997 83.18100000000001Q379.296 79.76400000000001 373.686 79.76400000000001H356.652Q356.24399999999997 79.76400000000001 356.24399999999997 80.172V104.04Q356.24399999999997 104.44800000000001 356.652 104.44800000000001H373.686Q379.296 104.44800000000001 382.71299999999997 101.08200000000001Q386.13 97.71600000000001 386.13 92.208ZM452.226 141.984 448.554 130.05Q448.452 129.744 448.146 129.744H419.484Q419.178 129.744 419.07599999999996 130.05L415.404 141.984Q415.098 142.8 414.18 142.8H405.918Q404.796 142.8 405.102 141.678L427.44 72.21600000000001Q427.746 71.4 428.664 71.4H438.864Q439.782 71.4 440.08799999999997 72.21600000000001L462.63 141.678L462.73199999999997 142.086Q462.73199999999997 142.8 461.81399999999996 142.8H453.45Q452.532 142.8 452.226 141.984ZM422.034 121.99200000000002H445.49399999999997Q445.698 121.99200000000002 445.851 121.83900000000001Q446.004 121.686 445.902 121.48200000000001L434.07 83.64000000000001Q433.96799999999996 83.436 433.764 83.436Q433.56 83.436 433.45799999999997 83.64000000000001L421.626 121.48200000000001Q421.524 121.686 421.677 121.83900000000001Q421.83 121.99200000000002 422.034 121.99200000000002ZM490.068 141.984 470.892 72.522 470.79 72.21600000000001Q470.79 71.4 471.81 71.4H480.072Q480.99 71.4 481.194 72.31800000000001L494.55600000000004 123.114Q494.658 123.31800000000001 494.81100000000004 123.31800000000001Q494.964 123.31800000000001 495.06600000000003 123.114L507.714 72.31800000000001Q507.918 71.4 508.836 71.4H516.894Q517.812 71.4 518.016 72.31800000000001L531.378 123.21600000000001Q531.48 123.42000000000002 531.582 123.42000000000002Q531.684 123.42000000000002 531.7860000000001 123.21600000000001L545.25 72.31800000000001Q545.454 71.4 546.3720000000001 71.4H554.124Q554.634 71.4 554.889 71.706Q555.144 72.012 555.042 72.522L536.58 141.882Q536.376 142.8 535.458 142.8H527.604Q526.686 142.8 526.482 141.882L513.12 89.25Q513.018 88.94400000000002 512.9159999999999 88.94400000000002Q512.814 88.94400000000002 512.712 89.25L499.75800000000004 141.882Q499.656 142.8 498.636 142.8H491.19Q490.272 142.8 490.068 141.984Z" fill="#FFFFFF"/><path d="M91.75215000000003 372.0168Q91.75215000000003 375.08805 90.97410000000002 377.2584Q90.07320000000001 379.92015 88.12807500000002 381.373875Q86.18295000000002 382.8276 83.23455000000001 382.8276Q81.80130000000003 382.8276 80.57280000000003 382.2543Q79.34430000000002 381.681 78.44340000000003 380.65725Q78.36150000000002 380.57535 78.30007500000002 380.6163Q78.23865000000002 380.65725 78.23865000000002 380.73915V382.0905Q78.23865000000002 382.5 77.82915000000003 382.5H74.71695000000003Q74.30745000000002 382.5 74.30745000000002 382.0905V354.2445Q74.30745000000002 353.835 74.71695000000003 353.835H77.82915000000003Q78.23865000000002 353.835 78.23865000000002 354.2445V363.37635Q78.23865000000002 363.45825 78.30007500000002 363.4992Q78.36150000000002 363.54015 78.44340000000003 363.45825Q79.34430000000002 362.4345 80.59327500000002 361.881675Q81.84225000000002 361.32885 83.23455000000001 361.32885Q86.30580000000002 361.32885 88.27140000000003 362.844Q90.23700000000002 364.35915 91.09695000000002 367.06185Q91.75215000000003 369.1503 91.75215000000003 372.0168ZM87.78000000000002 372.0987Q87.78000000000002 368.7408 86.46960000000001 366.6933Q85.28205000000003 364.7277 82.82505000000002 364.7277Q80.53185000000002 364.7277 79.34430000000002 366.81615Q78.27960000000002 368.69985 78.27960000000002 372.05775Q78.27960000000002 375.41565 79.26240000000001 377.21745Q80.49090000000002 379.42875 82.86600000000001 379.42875Q85.56870000000002 379.42875 86.75625000000002 377.1765Q87.78000000000002 375.25185 87.78000000000002 372.0987ZM95.43765000000002 390.23955V387.8235Q95.43765000000002 387.414 95.84715000000003 387.414H95.92905000000002Q97.56705000000002 387.37305 98.50890000000003 387.0045Q99.45075000000003 386.63595 100.06500000000003 385.63267499999995Q100.67925000000002 384.6294 101.25255000000003 382.62285Q101.29350000000002 382.5819 101.27302500000002 382.47952499999997Q101.25255000000003 382.37715 101.25255000000003 382.37715L94.41390000000003 362.1069L94.37295000000003 361.9431Q94.37295000000003 361.65645 94.74150000000003 361.65645H98.05845000000002Q98.42700000000002 361.65645 98.54985000000002 361.98405L103.17720000000003 377.4222Q103.21815000000002 377.5041 103.30005000000003 377.5041Q103.38195000000002 377.5041 103.42290000000003 377.4222L108.00930000000002 361.98405Q108.13215000000002 361.65645 108.50070000000002 361.65645H111.73575000000002Q112.18620000000003 361.65645 112.06335000000003 362.1069L104.61045000000003 383.93325Q103.66860000000003 386.7588 102.72675000000002 388.130625Q101.78490000000002 389.50245 100.26975000000002 390.07574999999997Q98.75460000000002 390.64905 96.01095000000002 390.64905H95.72430000000003Q95.43765000000002 390.64905 95.43765000000002 390.23955Z" fill="#FFFFFF" fill-opacity="0.72"/><image x="126.61" y="350.52" width="208.69" height="39.00" xlink:href="data:image/png;base64,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eKzvySH9+hwACzt+/f0B/Bpxyv7deTsD+GqAsRARERERUSRC77ifAuBCj69PH3kDwG4JnYxB/XUeADdr0brMgIPXXwPAc4j33y0m66/zaqV5Fm0kIiIiImqokDvuV3nIY6b2rgZwaMmfT438pC1SWNwZcVhpPuZ/+7jc17MDuAXA2IDjISIiIiKiiCfuLnY4i+7WAmRk6yQt0pY3JvIfwlTN9Xa9yCN5/zshfpP01wUA3BN4LERERERE1JCJ+98BrOP4Nam7neZ/JXTCxuQWHdb1EPVxNOJRFgEwPvf1KgBOMxwPERERERE1NMdd2lxROCO6cJLlUMdoakmVeWi0wHyOj3UUgOsQh07RD8NaXM91tX0iIiIiIoqYdY773IWJGIXxJICvRXzyx4zyHpVcfde2BvA64pedi4sALB14LEREREREVMMd940SC9Guu8sBnIX0LA/gSg+vu3gi709pGTeDLmDkC9gREREREVFNVZmU53Nse7UHgNscvA65tQ+AWxM8qdsBOM/xa74KYAfEbzCXcnI/gGkDj4eIiIiIiDyrUlH8Jp0sdNqNnFl7Y38MwBQtavcegFcA/MjReMm9jQEcCGBGXchp6ftijP7csz/rtLv7YeH3I22qo/dK3k+z6ntqOgBzab7783BHqravBmBLHf9EAJ8A8D/6az+mqZjXP6TpJEP634j+19J2cJO0PsGsOnlfWifwRERERERUUwMjI67aYhMRERERERFR6sXpiIiIiIiIiKgLnLgTERERERERRYwTdyIiIiIiIqKIceJOREREREREFDFO3ImIiIiIiIgixok7EREREREREeL1/wDw1Sk+hNnxBgAAAABJRU5ErkJggg=="/></symbol>
<symbol id="lg-endorsed-light" viewBox="0 0 629 462"><path d="M84.00000000000001 242.4V234.90000000000003Q84.00000000000001 233.40000000000003 85.05000000000001 232.35000000000002Q86.10000000000002 231.3 87.60000000000002 231.3H114.30000000000001Q115.80000000000001 231.3 116.85000000000002 232.35000000000002Q117.90000000000002 233.40000000000003 117.90000000000002 234.90000000000003V240.60000000000002Q117.90000000000002 254.4 130.20000000000002 263.55Q142.5 272.7 163.8 272.7Q182.10000000000002 272.7 191.4 264.9Q200.7 257.1 200.7 244.8Q200.7 236.4 195.6 230.55Q190.5 224.70000000000002 181.05 219.90000000000003Q171.60000000000002 215.10000000000002 152.70000000000002 207.90000000000003Q131.40000000000003 200.10000000000002 118.20000000000003 193.20000000000002Q105.00000000000003 186.3 96.15000000000002 174.45000000000002Q87.30000000000001 162.60000000000002 87.30000000000001 144.9Q87.30000000000001 118.50000000000003 106.50000000000001 103.20000000000002Q125.70000000000002 87.9 157.8 87.9Q180.3 87.9 197.55 95.70000000000002Q214.8 103.50000000000003 224.4 117.45000000000002Q234.0 131.4 234.0 149.70000000000002V154.8Q234.0 156.3 232.95000000000002 157.35000000000002Q231.90000000000003 158.4 230.40000000000003 158.4H203.40000000000003Q201.90000000000003 158.4 200.85000000000002 157.35000000000002Q199.8 156.3 199.8 154.8V151.50000000000003Q199.8 137.4 188.25 127.65Q176.70000000000002 117.9 156.3 117.9Q139.8 117.9 130.65000000000003 124.80000000000001Q121.50000000000003 131.70000000000002 121.50000000000003 144.3Q121.50000000000003 153.3 126.30000000000003 159.0Q131.10000000000002 164.70000000000002 140.70000000000002 169.35000000000002Q150.3 174.0 170.40000000000003 181.20000000000002Q191.70000000000002 189.3 204.45 195.9Q217.2 202.5 226.35 214.35000000000002Q235.5 226.20000000000002 235.5 243.9Q235.5 270.90000000000003 215.55 286.8Q195.60000000000002 302.7 161.10000000000002 302.7Q137.70000000000002 302.7 120.30000000000001 295.2Q102.90000000000002 287.7 93.45000000000002 274.2Q84.00000000000001 260.7 84.00000000000001 242.4ZM 269.1 296.7 V 94.078 Q 269.1 92.578 270.15 91.528 Q 271.2 90.478 272.7 90.478 H 300.3 Q 301.8 90.478 302.85 91.528 Q 303.9 92.578 303.9 94.078 V 296.7 Q 303.9 298.2 302.85 299.25 Q 301.8 300.3 300.3 300.3 H 272.7 Q 271.2 300.3 270.15 299.25 Q 269.1 298.2 269.1 296.7 Z" fill="#2E6AFF"/><path d="M395.92199999999997 92.004Q395.92199999999997 98.12400000000001 393.27 102.714Q390.618 107.304 385.773 109.85400000000001Q380.928 112.40400000000001 374.604 112.40400000000001H356.652Q356.24399999999997 112.40400000000001 356.24399999999997 112.81200000000001V141.78Q356.24399999999997 142.8 355.224 142.8H347.472Q346.452 142.8 346.452 141.78V72.31800000000001Q346.452 71.298 347.472 71.298H375.012Q381.234 71.298 385.977 73.899Q390.71999999999997 76.5 393.32099999999997 81.19200000000001Q395.92199999999997 85.88400000000001 395.92199999999997 92.004ZM386.13 92.208Q386.13 86.59800000000001 382.71299999999997 83.18100000000001Q379.296 79.76400000000001 373.686 79.76400000000001H356.652Q356.24399999999997 79.76400000000001 356.24399999999997 80.172V104.04Q356.24399999999997 104.44800000000001 356.652 104.44800000000001H373.686Q379.296 104.44800000000001 382.71299999999997 101.08200000000001Q386.13 97.71600000000001 386.13 92.208ZM452.226 141.984 448.554 130.05Q448.452 129.744 448.146 129.744H419.484Q419.178 129.744 419.07599999999996 130.05L415.404 141.984Q415.098 142.8 414.18 142.8H405.918Q404.796 142.8 405.102 141.678L427.44 72.21600000000001Q427.746 71.4 428.664 71.4H438.864Q439.782 71.4 440.08799999999997 72.21600000000001L462.63 141.678L462.73199999999997 142.086Q462.73199999999997 142.8 461.81399999999996 142.8H453.45Q452.532 142.8 452.226 141.984ZM422.034 121.99200000000002H445.49399999999997Q445.698 121.99200000000002 445.851 121.83900000000001Q446.004 121.686 445.902 121.48200000000001L434.07 83.64000000000001Q433.96799999999996 83.436 433.764 83.436Q433.56 83.436 433.45799999999997 83.64000000000001L421.626 121.48200000000001Q421.524 121.686 421.677 121.83900000000001Q421.83 121.99200000000002 422.034 121.99200000000002ZM490.068 141.984 470.892 72.522 470.79 72.21600000000001Q470.79 71.4 471.81 71.4H480.072Q480.99 71.4 481.194 72.31800000000001L494.55600000000004 123.114Q494.658 123.31800000000001 494.81100000000004 123.31800000000001Q494.964 123.31800000000001 495.06600000000003 123.114L507.714 72.31800000000001Q507.918 71.4 508.836 71.4H516.894Q517.812 71.4 518.016 72.31800000000001L531.378 123.21600000000001Q531.48 123.42000000000002 531.582 123.42000000000002Q531.684 123.42000000000002 531.7860000000001 123.21600000000001L545.25 72.31800000000001Q545.454 71.4 546.3720000000001 71.4H554.124Q554.634 71.4 554.889 71.706Q555.144 72.012 555.042 72.522L536.58 141.882Q536.376 142.8 535.458 142.8H527.604Q526.686 142.8 526.482 141.882L513.12 89.25Q513.018 88.94400000000002 512.9159999999999 88.94400000000002Q512.814 88.94400000000002 512.712 89.25L499.75800000000004 141.882Q499.656 142.8 498.636 142.8H491.19Q490.272 142.8 490.068 141.984Z" fill="#000000"/><path d="M91.75215000000003 372.0168Q91.75215000000003 375.08805 90.97410000000002 377.2584Q90.07320000000001 379.92015 88.12807500000002 381.373875Q86.18295000000002 382.8276 83.23455000000001 382.8276Q81.80130000000003 382.8276 80.57280000000003 382.2543Q79.34430000000002 381.681 78.44340000000003 380.65725Q78.36150000000002 380.57535 78.30007500000002 380.6163Q78.23865000000002 380.65725 78.23865000000002 380.73915V382.0905Q78.23865000000002 382.5 77.82915000000003 382.5H74.71695000000003Q74.30745000000002 382.5 74.30745000000002 382.0905V354.2445Q74.30745000000002 353.835 74.71695000000003 353.835H77.82915000000003Q78.23865000000002 353.835 78.23865000000002 354.2445V363.37635Q78.23865000000002 363.45825 78.30007500000002 363.4992Q78.36150000000002 363.54015 78.44340000000003 363.45825Q79.34430000000002 362.4345 80.59327500000002 361.881675Q81.84225000000002 361.32885 83.23455000000001 361.32885Q86.30580000000002 361.32885 88.27140000000003 362.844Q90.23700000000002 364.35915 91.09695000000002 367.06185Q91.75215000000003 369.1503 91.75215000000003 372.0168ZM87.78000000000002 372.0987Q87.78000000000002 368.7408 86.46960000000001 366.6933Q85.28205000000003 364.7277 82.82505000000002 364.7277Q80.53185000000002 364.7277 79.34430000000002 366.81615Q78.27960000000002 368.69985 78.27960000000002 372.05775Q78.27960000000002 375.41565 79.26240000000001 377.21745Q80.49090000000002 379.42875 82.86600000000001 379.42875Q85.56870000000002 379.42875 86.75625000000002 377.1765Q87.78000000000002 375.25185 87.78000000000002 372.0987ZM95.43765000000002 390.23955V387.8235Q95.43765000000002 387.414 95.84715000000003 387.414H95.92905000000002Q97.56705000000002 387.37305 98.50890000000003 387.0045Q99.45075000000003 386.63595 100.06500000000003 385.63267499999995Q100.67925000000002 384.6294 101.25255000000003 382.62285Q101.29350000000002 382.5819 101.27302500000002 382.47952499999997Q101.25255000000003 382.37715 101.25255000000003 382.37715L94.41390000000003 362.1069L94.37295000000003 361.9431Q94.37295000000003 361.65645 94.74150000000003 361.65645H98.05845000000002Q98.42700000000002 361.65645 98.54985000000002 361.98405L103.17720000000003 377.4222Q103.21815000000002 377.5041 103.30005000000003 377.5041Q103.38195000000002 377.5041 103.42290000000003 377.4222L108.00930000000002 361.98405Q108.13215000000002 361.65645 108.50070000000002 361.65645H111.73575000000002Q112.18620000000003 361.65645 112.06335000000003 362.1069L104.61045000000003 383.93325Q103.66860000000003 386.7588 102.72675000000002 388.130625Q101.78490000000002 389.50245 100.26975000000002 390.07574999999997Q98.75460000000002 390.64905 96.01095000000002 390.64905H95.72430000000003Q95.43765000000002 390.64905 95.43765000000002 390.23955Z" fill="#000000" fill-opacity="0.72"/><image x="126.61" y="350.52" width="208.69" height="39.00" xlink:href="data:image/png;base64,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"/></symbol>
  <symbol id="pet-dog" viewBox="0 0 200 200">
    <path d="M48 200c0-48 22-74 52-74s52 26 52 74z" fill="var(--c1,#3F3F46)"/>
    <path d="M82 200c0-30 8-48 18-48s18 18 18 48z" fill="var(--c2,#FFFFFF)"/>
    <path d="M44 62c-14 6-20 40-10 64 6 14 22 6 26-8 4-18 2-48-16-56z" fill="var(--c3,#18181B)"/>
    <path d="M156 62c14 6 20 40 10 64-6 14-22 6-26-8-4-18-2-48 16-56z" fill="var(--c3,#18181B)"/>
    <ellipse cx="100" cy="92" rx="52" ry="50" fill="var(--c1,#3F3F46)"/>
    <path d="M100 52c-10 0-14 14-14 30 0 10-20 20-20 38 0 16 16 24 34 24s34-8 34-24c0-18-20-28-20-38 0-16-4-30-14-30z" fill="var(--c2,#FFFFFF)"/>
    <circle cx="80" cy="86" r="7" fill="#000"/><circle cx="120" cy="86" r="7" fill="#000"/>
    <circle cx="82.5" cy="83.5" r="2.2" fill="#fff"/><circle cx="122.5" cy="83.5" r="2.2" fill="#fff"/>
    <ellipse cx="100" cy="108" rx="11" ry="8" fill="#000"/>
    <path d="M100 116v8M90 126c4 5 16 5 20 0" stroke="#000" stroke-width="3" fill="none" stroke-linecap="round"/>
    <path d="M62 146c24 10 52 10 76 0" stroke="#000" stroke-width="9" fill="none" stroke-linecap="round"/>
  </symbol>
  <symbol id="pet-cat" viewBox="0 0 200 200">
    <path d="M52 200c0-46 20-70 48-70s48 24 48 70z" fill="var(--c1,#A1A1AA)"/>
    <path d="M86 200c0-28 6-44 14-44s14 16 14 44z" fill="var(--c2,#F4F4F5)"/>
    <path d="M50 40l18 44-30 4zM150 40l-18 44 30 4z" fill="var(--c1,#A1A1AA)"/>
    <path d="M54 54l10 26-16 2zM146 54l-10 26 16 2z" fill="var(--c3,#71717A)"/>
    <ellipse cx="100" cy="98" rx="56" ry="48" fill="var(--c1,#A1A1AA)"/>
    <path d="M78 58c6 8 8 16 6 24M100 54v26M122 58c-6 8-8 16-6 24" stroke="var(--c3,#71717A)" stroke-width="5" fill="none" stroke-linecap="round"/>
    <ellipse cx="100" cy="118" rx="30" ry="22" fill="var(--c2,#F4F4F5)"/>
    <ellipse cx="78" cy="96" rx="9" ry="10" fill="#E4E4E7"/><ellipse cx="122" cy="96" rx="9" ry="10" fill="#E4E4E7"/>
    <ellipse cx="78" cy="97" rx="3.2" ry="8" fill="#000"/><ellipse cx="122" cy="97" rx="3.2" ry="8" fill="#000"/>
    <path d="M94 110h12l-6 7z" fill="#3F3F46"/>
    <path d="M100 117v5M92 124c3 3 13 3 16 0" stroke="#000" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    <path d="M60 112l-22-4M60 120l-22 4M140 112l22-4M140 120l22 4" stroke="#000" stroke-width="1.6" opacity=".45" stroke-linecap="round"/>
    <path d="M66 146c22 9 46 9 68 0" stroke="#000" stroke-width="8" fill="none" stroke-linecap="round"/>
  </symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path d="M12 20.5s-8-5-8-11a4.5 4.5 0 0 1 8-2.8 4.5 4.5 0 0 1 8 2.8c0 6-8 11-8 11z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linejoin="round"/><path d="M7 12h3l1.5-2.5 2 5L15 12h2" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M12 7.5V12l3 2" stroke="currentColor" stroke-width="1.6" fill="none"/></symbol>
  <symbol id="i-chat" viewBox="0 0 24 24"><path d="M4 5h16v11H9.5L5 19.5V16H4z" stroke="currentColor" stroke-width="1.6" fill="none" stroke-linejoin="round"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-6.5-6.7-6.5-11.5a6.5 6.5 0 0 1 13 0C18.5 14.3 12 21 12 21z" stroke="currentColor" stroke-width="1.6" fill="none"/><circle cx="12" cy="9.5" r="2.3" stroke="currentColor" stroke-width="1.6" fill="none"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2" fill="none"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2" fill="none"/></symbol>
</defs></svg>

<nav class="nav" aria-label="Main">
  <div class="wrap">
    <a href="#top" aria-label="siPaw by Nakilab home"><svg class="logo-endorsed on-dark" viewBox="0 0 629 462" role="img" aria-label="siPaw by Nakilab"><use href="#lg-endorsed-dark"/></svg><svg class="logo-endorsed on-light" viewBox="0 0 629 462" role="img" aria-label="siPaw by Nakilab"><use href="#lg-endorsed-light"/></svg></a>
    <ul>
      <li><a href="#features">What SIPO does</a></li>
      <li><a href="#demo">Demo</a></li>
      <li><a href="#pets">Features</a></li>
    </ul>
    <div class="acts">
<?php if ($owner): ?>
      <a class="btn primary sm" href="dashboard.php">Open app</a>
      <?php else: ?>
      <a class="btn outline sm" href="login.php">Log in</a>
      <a class="btn primary sm" href="signup.php">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main id="main">
  <header class="hero" id="top">
    <div class="wrap">
      <div class="intro">
        <div class="brand"><svg class="logo-endorsed on-dark" viewBox="0 0 629 462" role="img" aria-label="siPaw by Nakilab"><use href="#lg-endorsed-dark"/></svg><svg class="logo-endorsed on-light" viewBox="0 0 629 462" role="img" aria-label="siPaw by Nakilab"><use href="#lg-endorsed-light"/></svg></div>
        <h1>Your pet's <span>SI companion.</span></h1>
        <p class="lede"><?= $owner ? 'Welcome back' . (!empty($owner['name']) ? ', ' . rrp_h(strtok(trim((string) $owner['name']), ' ')) : '') . '. SIPO is ready when you are.' : 'Add Milo and Luna, ask SIPO anything, and get a nudge before you forget. One app for every paw.' ?></p>
        <div class="ctas">
<?php if ($owner): ?>
        <a class="btn primary" href="dashboard.php">Open SIPAW <svg width="16" height="16" aria-hidden="true"><use href="#i-arrow"/></svg></a>
        <a class="btn outline" href="si-chat.php">Talk to SIPO</a>
        <?php else: ?>
        <a class="btn primary" id="register" href="signup.php">Add your first pet <svg width="16" height="16" aria-hidden="true"><use href="#i-arrow"/></svg></a>
        <a class="btn outline" href="#demo">Watch the demo</a>
        <?php endif; ?>
      </div>
      </div>

      <div class="scene" id="scene" role="button" tabindex="0" aria-label="SIPO with Milo and Luna. Tap for SIPO's next line">
        <div class="bubble" id="bubble">
          <div class="who"><span class="name"><i></i>SIPO</span><span class="tag data" id="line-tag">01 / HELLO</span></div>
          <div class="typing" aria-hidden="true"><i></i><i></i><i></i></div>
          <p class="say" id="say" aria-live="polite"></p>
          <div class="timer" aria-hidden="true"><i id="timer"></i></div>
        </div>
        <div class="cast">
          <div class="pet" id="pet-milo"><svg viewBox="0 0 200 200" aria-hidden="true"><use href="#pet-dog"/></svg><span class="tagline"><b>Milo</b><span class="data">BEAGLE / 4 YRS</span></span></div>
          <div class="sipo" id="sipo" aria-hidden="true">
            <!-- SIPO v2 Plush, 3D loops from /assets/mascot/v2 -->
            <div class="face" data-face="idle"><img class="anim" src="/assets/mascot/v2/idle-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/idle-still.webp" alt="" width="256" height="256" decoding="async"></div>
            <div class="face" data-face="thinking"><img class="anim" src="/assets/mascot/v2/thinking-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/thinking-still.webp" alt="" width="256" height="256" decoding="async"></div>
            <div class="face" data-face="writing"><img class="anim" src="/assets/mascot/v2/talking-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/talking-still.webp" alt="" width="256" height="256" decoding="async"></div>
            <div class="face" data-face="listening"><img class="anim" src="/assets/mascot/v2/listening-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/listening-still.webp" alt="" width="256" height="256" decoding="async"></div>
            <div class="face" data-face="happy"><img class="anim" src="/assets/mascot/v2/happy-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/happy-still.webp" alt="" width="256" height="256" decoding="async"></div>
            <div class="face" data-face="alert"><img class="anim" src="/assets/mascot/v2/concerned-l.webp" alt="" width="320" height="320" decoding="async"><img class="still" src="/assets/mascot/v2/concerned-still.webp" alt="" width="256" height="256" decoding="async"></div>
          </div>
          <div class="pet" id="pet-luna"><svg viewBox="0 0 200 200" aria-hidden="true" style="--c1:#71717A;--c2:#E4E4E7;--c3:#3F3F46"><use href="#pet-cat"/></svg><span class="tagline"><b>Luna</b><span class="data">TABBY / 2 YRS</span></span></div>
          <span class="floor" aria-hidden="true"></span>
        </div>
        <ol class="steps" id="steps" aria-label="SIPO's lines"></ol>
      </div>
    </div>
  </header>

  <section class="block" id="features" aria-labelledby="f-title">
    <div class="wrap">
      <div class="head">
        <div><p class="label">What SIPO does</p><h2 id="f-title">One companion for every paw</h2></div>
        <p>Tap a card and SIPO explains it. Each line changes SIPO's face, so you always know if it is listening, writing, thinking or warning you.</p>
      </div>
      <div class="fgrid" id="fgrid"></div>
    </div>
  </section>

  <section class="block" id="demo" aria-labelledby="d-title">
    <div class="wrap demo">
      <div>
        <p class="label">Demo</p>
        <h2 id="d-title" style="font-size:clamp(36px,4.6vw,64px);margin:18px 0 18px;max-width:14ch">Watch a day with siPaw</h2>
        <p style="color:var(--sub);max-width:32rem;margin-bottom:36px">The app plays itself. Pick a step to jump to it.</p>
        <ol class="dsteps" id="dsteps">
          <li><button data-v="0"><span class="n data">01</span><b>Your pets</b><small>Milo and Luna, and what is due today.</small><i class="bar"></i></button></li>
          <li><button data-v="1"><span class="n data">02</span><b>Ask SIPO</b><small>A real question, a calm answer.</small><i class="bar"></i></button></li>
          <li><button data-v="2"><span class="n data">03</span><b>The nudge</b><small>Flea meds, right on time. One tap to log it.</small><i class="bar"></i></button></li>
          <li><button data-v="3"><span class="n data">04</span><b>Owner boards</b><small>Tips from people with the same breed.</small><i class="bar"></i></button></li>
        </ol>
      </div>

      <div class="phone" aria-label="siPaw app demo" role="img">
        <div class="screen">
          <div class="notch"></div>
          <div class="status"><span class="data" style="font-size:13px;font-family:var(--f-body);font-weight:600">9:41</span><span class="data" style="font-size:10px">5G</span></div>

          <div class="view" data-view="0">
            <div class="top"><div><p class="label" style="font-size:10px">Tuesday</p><h4>Hi Navid</h4></div><img class="mini-sipo" src="/assets/mascot/v2/happy-still.webp" alt="" width="38" height="38" decoding="async"></div>
            <div class="petrow">
              <div class="petcard"><div class="art"><svg viewBox="0 0 200 200"><use href="#pet-dog"/></svg></div><div class="meta"><b>Milo</b><span class="data">BEAGLE / 12.4 KG</span></div></div>
              <div class="petcard"><div class="art"><svg viewBox="0 0 200 200" style="--c1:#71717A;--c2:#E4E4E7;--c3:#3F3F46"><use href="#pet-cat"/></svg></div><div class="meta"><b>Luna</b><span class="data">TABBY / 4.1 KG</span></div></div>
            </div>
            <p class="label" style="font-size:10px;margin-top:4px">Today</p>
            <div class="list">
              <div class="li"><span class="dotx"></span><div><b>Flea meds, Luna</b><small>Monthly drops</small></div><span class="data">20:00</span></div>
              <div class="li"><span class="dotx idle"></span><div><b>Walk, Milo</b><small>30 min, easy pace</small></div><span class="data">18:30</span></div>
              <div class="li"><span class="dotx idle"></span><div><b>Vet check, Milo</b><small>Annual visit</small></div><span class="data">THU</span></div>
            </div>
          </div>

          <div class="view" data-view="1">
            <div class="top"><div><p class="label" style="font-size:10px">Chat</p><h4>SIPO</h4></div><img class="mini-sipo" src="/assets/mascot/v2/listening-still.webp" alt="" width="38" height="38" decoding="async"></div>
            <div class="chat" id="chat"></div>
            <div class="composer"><span>Ask about Milo or Luna</span><i><svg width="16" height="16"><use href="#i-arrow"/></svg></i></div>
          </div>

          <div class="view" data-view="2" style="padding:0">
            <div class="lock">
              <span class="time">20:00</span><span class="date">Tuesday, 21 October</span>
              <div style="width:100%;padding:0 14px" id="nudge"></div>
            </div>
          </div>

          <div class="view" data-view="3">
            <div class="top"><div><p class="label" style="font-size:10px">Boards</p><h4>Beagles</h4></div><img class="mini-sipo" src="/assets/mascot/v2/idle-still.webp" alt="" width="38" height="38" decoding="async"></div>
            <div class="board-tabs"><span class="on">Beagles</span><span>Tabby cats</span><span>New owners</span></div>
            <div class="stack" id="posts" style="display:flex;flex-direction:column;gap:8px"></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="block" id="pets" aria-labelledby="p-title">
    <div class="wrap">
      <div class="head"><div><p class="label">Features</p><h2 id="p-title">Software with pets inside</h2></div><p>Short, funny, useful. Not a policy PDF.</p></div>
      <ul class="mocks">
        <li class="mock"><div class="chrome" aria-hidden="true"><i></i><i></i><i></i><span class="data">care.app</span></div>
          <div class="body"><svg class="ico" aria-hidden="true"><use href="#i-heart"/></svg><div class="pets" aria-hidden="true"><svg viewBox="0 0 200 200"><use href="#pet-dog"/></svg></div>
            <h3>Care dossiers</h3><p>Profiles SI actually reads, so you never retype “Milo hates baths” in every chat.</p></div></li>
        <li class="mock"><div class="chrome" aria-hidden="true"><i></i><i></i><i></i><span class="data">ping.app</span></div>
          <div class="body"><svg class="ico" aria-hidden="true"><use href="#i-clock"/></svg><div class="pets" aria-hidden="true"><svg viewBox="0 0 200 200" style="--c1:#71717A;--c2:#E4E4E7;--c3:#3F3F46"><use href="#pet-cat"/></svg></div>
            <h3>Nudge mode</h3><p>Walks, meds, check-ins. Your cat judges. We just remind.</p></div></li>
        <li class="mock"><div class="chrome" aria-hidden="true"><i></i><i></i><i></i><span class="data">chat.si</span></div>
          <div class="body"><svg class="ico" aria-hidden="true"><use href="#i-chat"/></svg><div class="pets" aria-hidden="true"><img src="/assets/mascot/v2/talking-still.webp" alt="" width="76" height="76" decoding="async"></div>
            <h3>SI on tap</h3><p>Fact-minded Super Intelligence. Cares hard. Sells nothing.</p></div></li>
        <li class="mock"><div class="chrome" aria-hidden="true"><i></i><i></i><i></i><span class="data">boards.app</span></div>
          <div class="body"><svg class="ico" aria-hidden="true"><use href="#i-pin"/></svg><div class="pets" aria-hidden="true"><svg viewBox="0 0 200 200"><use href="#pet-dog"/></svg><svg viewBox="0 0 200 200" style="--c1:#71717A;--c2:#E4E4E7;--c3:#3F3F46"><use href="#pet-cat"/></svg></div>
            <h3>Owner boards</h3><p>Species tips, not doomscroll. Social lite for people with lint rollers.</p></div></li>
      </ul>
    </div>
  </section>

  <section class="block final" id="cta" aria-labelledby="c-title">
    <div class="wrap">
      <div><h2 id="c-title"><?= $owner ? 'Your pets are waiting.' : 'Add your first pet.' ?></h2><p><?= $owner ? 'Pick up where you left off.' : 'Two minutes. SIPO takes it from there.' ?></p></div>
      <div class="ctas">
<?php if ($owner): ?>
        <a class="btn primary" href="si-chat.php">Talk to SIPO</a>
        <?php else: ?>
        <a class="btn primary" href="signup.php">Register</a>
        <a class="btn outline" href="login.php">Log in</a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="foot-tile" href="#top" aria-label="SIPAW home">
          <img src="<?= rrp_h(rrp_asset('assets/img/logos/sipaw-app-icon-1024.svg', true)) ?>" width="160" height="160" alt="SI^PAW element tile">
        </a>
      </div>
      <div class="foot-col">
        <p class="foot-h">Product</p>
        <a href="#features">What SIPO does</a>
        <a href="#demo">Demo</a>
        <a href="#pets">Features</a>
        <?php if (!$owner): ?><a href="signup.php">Register</a><?php endif; ?>
      </div>
      <div class="foot-col">
        <p class="foot-h">Company</p>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">NAKILAB</a>
        <a href="https://nakilab.com" target="_blank" rel="noopener noreferrer">Contact</a>
      </div>
      <div class="foot-col">
        <p class="foot-h">Social</p>
        <div class="social" aria-label="Social links">
          <a href="#" aria-label="X (Twitter)" title="X (Twitter)">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter"><path d="M4 4l16 16M20 4L4 20"/></svg>
          </a>
          <a href="#" aria-label="LinkedIn" title="LinkedIn">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" stroke-linejoin="miter"><rect x="3" y="3" width="18" height="18"/><path d="M8 11v6M8 8v.01M12 17v-4.5a2.5 2.5 0 015 0V17"/></svg>
          </a>
          <a href="#" aria-label="Facebook" title="Facebook">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M22 12.07C22 6.48 17.52 2 11.93 2S1.86 6.48 1.86 12.07c0 5.02 3.66 9.18 8.44 9.93v-7.03H7.9v-2.9h2.4V9.85c0-2.37 1.4-3.68 3.56-3.68 1.03 0 2.11.18 2.11.18v2.32h-1.19c-1.17 0-1.54.73-1.54 1.48v1.78h2.62l-.42 2.9h-2.2V22c4.78-.75 8.44-4.91 8.44-9.93z"/></svg>
          </a>
          <a href="#" aria-label="Instagram" title="Instagram">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 01-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 017.8 2zm-.2 2A3.6 3.6 0 004 7.6v8.8A3.6 3.6 0 007.6 20h8.8a3.6 3.6 0 003.6-3.6V7.6A3.6 3.6 0 0016.4 4H7.6zm9.65 1.5a1.25 1.25 0 110 2.5 1.25 1.25 0 010-2.5zM12 7a5 5 0 110 10 5 5 0 010-10zm0 2a3 3 0 100 6 3 3 0 000-6z"/></svg>
          </a>
        </div>
      </div>
      <div class="foot-col">
        <p class="foot-h">Blog</p>
        <a href="blog.php">Blog</a>
      </div>
    </div>
    <div class="foot-bar">
      <p>© <?= $year ?> NAKILAB · siPaw</p>
      <p>Add to Home Screen for the PWA</p>
    </div>
  </div>
</footer>

<script>
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const wait = ms => new Promise(r => setTimeout(r, ms));

  /* ================= Hero: SIPO's lines. Each line sets SIPO's face; `pet` lifts Milo or Luna. ================= */
  const LINES = [
    { face: 'happy',     tag: 'Hello',     title: 'Meet SIPO',   text: 'Hi, I am SIPO, your pet assistant. Ask me anything about Milo and Luna.' },
    { face: 'listening', tag: 'Care',      title: 'Pet profile', text: 'Tell me about your pet. I remember breed, health notes, habits, and that Milo hates baths.', pet: 'milo' },
    { face: 'writing',   tag: 'Reminders', title: 'Reminders',   text: 'I write down vaccines, pills and meals, then remind you on time. The day before, and on the day.', pet: 'luna' },
    { face: 'thinking',  tag: 'Ask SI',    title: 'Ask SI',      text: 'Forgot the flea meds again? I got you. Nudges beat guilt trips.', pet: 'luna' },
    { face: 'alert',     tag: 'Vet check', title: 'Vet check',   text: 'Something feels off? Tell me what you see. I say clearly when it is time to call the vet.', pet: 'milo' },
    { face: 'idle',      tag: 'Boards',    title: 'Owner boards', text: 'Ask other owners on the breed boards. Real tips, no doomscroll. Ready to add your first pet?' }
  ];
  const MOOD = { idle: 'idle', thinking: 'thinking', writing: 'talking', listening: 'listening', happy: 'happy', alert: 'concerned' };
  const HOLD = 5000, TYPE_MS = 24, THINK_MS = 650;
  const sipo = $('#sipo'), say = $('#say'), bubble = $('#bubble'), tag = $('#line-tag'), timer = $('#timer');
  const faces = Object.fromEntries($$('#sipo .face').map(f => [f.dataset.face, f]));
  let i = 0, auto = !reduce, hovering = false, typeT, holdT, thinkT, holdAnim;

  $('#steps').innerHTML = LINES.map((l, n) => `<li><button aria-label="Line ${n + 1}: ${l.tag}"><i></i></button></li>`).join('');
  const dots = $$('#steps button');
  dots.forEach((b, n) => b.addEventListener('click', e => { e.stopPropagation(); auto = false; go(n); }));

  const fgrid = $('#fgrid');
  fgrid.innerHTML = LINES.slice(1).map((l, k) => `
    <button class="fcard" data-line="${k + 1}" aria-pressed="false">
      <span class="ix data">0${k + 1}</span>
      <img class="face-mini" src="/assets/mascot/v2/${MOOD[l.face]}-still.webp" alt="" width="72" height="72" decoding="async">
      <h3>${l.title}</h3>
      <p>${l.text.split(/(?<=[.?]) /)[0]}</p>
      <span class="go">Ask SIPO <svg width="14" height="14"><use href="#i-arrow"/></svg></span>
    </button>`).join('');
  const cards = $$('#fgrid .fcard');
  cards.forEach(c => c.addEventListener('click', () => {
    auto = false; go(+c.dataset.line);
    $('#top').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
  }));

  let faceNow = null;
  function setFace(name) {
    if (name === faceNow) return;
    const prev = faces[faceNow], next = faces[name];
    faceNow = name;
    if (prev) { prev.classList.remove('on', 'in'); prev.classList.add('leaving'); setTimeout(() => { if (faceNow !== prev.dataset.face) prev.classList.remove('leaving'); }, 320); }
    next.classList.remove('leaving'); next.classList.add('on');
    requestAnimationFrame(() => requestAnimationFrame(() => next.classList.add('in')));
  }
  function clearTimers() { clearTimeout(typeT); clearTimeout(holdT); clearTimeout(thinkT); if (holdAnim) holdAnim.cancel(); }
  function go(n) {
    clearTimers();
    i = (n + LINES.length) % LINES.length;
    const l = LINES[i];
    dots.forEach((d, k) => k === i ? d.setAttribute('aria-current', 'step') : d.removeAttribute('aria-current'));
    cards.forEach(c => c.setAttribute('aria-pressed', +c.dataset.line === i));
    $('#pet-milo').classList.toggle('hi', l.pet === 'milo');
    $('#pet-luna').classList.toggle('hi', l.pet === 'luna');
    tag.textContent = `${String(i + 1).padStart(2, '0')} / ${l.tag.toUpperCase()}`;
    timer.style.transform = 'scaleX(0)';
    if (reduce) { setFace(l.face); say.textContent = l.text; return; }
    // a short "thinking" beat, then SIPO changes face and types the line
    bubble.classList.add('thinking');
    setFace('thinking');
    thinkT = setTimeout(() => { bubble.classList.remove('thinking'); setFace(l.face); type(l.text); }, go.started ? THINK_MS : 250);
    go.started = true;
  }
  // typing follows the clock, not the timer count, so a throttled tab still finishes on time
  function type(text) {
    const at = [];
    let t = 0;
    for (const ch of text) { at.push(t); t += /[.,?]/.test(ch) ? TYPE_MS * 6 : TYPE_MS; }
    const t0 = performance.now();
    const tick = () => {
      const el = performance.now() - t0;
      let k = 0;
      while (k < at.length && at[k] <= el) k++;
      if (k < text.length) { say.innerHTML = esc(text.slice(0, k)) + '<span class="caret"></span>'; typeT = setTimeout(tick, TYPE_MS); }
      else { say.textContent = text; hold(); }
    };
    tick();
  }
  function hold() {
    if (!auto) return;
    holdAnim = timer.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: HOLD, easing: 'linear', fill: 'forwards' });
    if (hovering) holdAnim.pause();
    holdT = setTimeout(function tick() { if (hovering) { holdT = setTimeout(tick, 300); return; } go(i + 1); }, HOLD);
  }
  const esc = s => s.replace(/[&<>]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));

  // tapping the scene moves to the next line and stops the auto-play; hovering pauses it
  const scene = $('#scene');
  scene.addEventListener('click', () => { auto = false; go(i + 1); });
  scene.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); auto = false; go(i + 1); } });
  scene.addEventListener('pointerenter', () => { hovering = true; if (holdAnim) holdAnim.pause(); });
  scene.addEventListener('pointerleave', () => { hovering = false; if (holdAnim && holdAnim.playState === 'paused') holdAnim.play(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight') { auto = false; go(i + 1); }
    if (e.key === 'ArrowLeft') { auto = false; go(i - 1); }
  });
  go(0);

  /* ================= Demo: the app plays itself ================= */
  const views = $$('.view'), dsteps = $$('#dsteps button');
  const DEMO_HOLD = [5200, 9000, 6500, 6500];
  let v = -1, demoRun = 0, demoAuto = !reduce, barAnim;
  const sipoMini = face => `<img src="/assets/mascot/v2/${{ writing: 'talking', alert: 'concerned' }[face] || face}-still.webp" alt="" width="30" height="30" decoding="async">`;

  async function playChat(run) {
    const chat = $('#chat');
    chat.innerHTML = '';
    const add = html => { chat.insertAdjacentHTML('beforeend', html); return chat.lastElementChild; };
    add('<div class="msg me">Milo seems tired after walks. What should I watch for?</div>');
    if (!reduce) await wait(900); if (run !== demoRun) return;
    const typing = add(`<div class="msg si">${sipoMini('thinking')}<span class="dots"><i></i><i></i><i></i></span></div>`);
    if (!reduce) await wait(1500); if (run !== demoRun) return;
    typing.outerHTML = `<div class="msg si">${sipoMini('writing')}<span>Check his pace, water and rest. Beagles push hard on warm days. If he is still tired tomorrow, call the vet.</span></div>`;
    if (!reduce) await wait(1200); if (run !== demoRun) return;
    add('<div class="msg si">' + sipoMini('happy') + '<span>Want a gentle walk plan for this week?</span></div>');
    if (!reduce) await wait(700); if (run !== demoRun) return;
    add('<div class="chips"><span class="chip">Yes, make one</span><span class="chip">Set a vet reminder</span></div>');
  }
  async function playNudge(run) {
    const box = $('#nudge');
    box.innerHTML = '';
    if (!reduce) await wait(500); if (run !== demoRun) return;
    box.innerHTML = '<div class="push"><span class="ap">SI</span><div style="flex:1"><div style="display:flex;justify-content:space-between"><b>SIPO</b><span class="data" style="font-size:10px;color:var(--sub)">NOW</span></div><p>Flea meds for Luna. Two drops between the shoulders.</p><div class="row"><span>Snooze 1 h</span><span class="do">Done</span></div></div></div>';
    if (!reduce) await wait(2600); if (run !== demoRun) return;
    box.firstElementChild.classList.add('done');
    box.insertAdjacentHTML('beforeend', '<p class="done-note"><svg width="16" height="16" style="color:var(--blue)"><use href="#i-check"/></svg>Logged for Luna. Next dose on 21 Nov.</p>');
  }
  async function playBoards(run) {
    const box = $('#posts');
    box.innerHTML = '';
    const POSTS = [
      ['Best harness for a puller?', 'ANA / 2H', '18 replies'],
      ['Tired after walks: normal at 4 years?', 'SAM / 5H', '9 replies'],
      ['SIPO tip: walk before 9:00 in summer', 'PINNED', 'Saved by 41']
    ];
    for (const [t, who, n] of POSTS) {
      if (!reduce) await wait(500); if (run !== demoRun) return;
      box.insertAdjacentHTML('beforeend', `<div class="post"><div class="row"><span class="data">${who}</span><span class="data">${n.toUpperCase()}</span></div><b>${t}</b></div>`);
    }
  }
  function show(n, fromUser) {
    v = n; demoRun++;
    const run = demoRun;
    views.forEach((el, k) => el.classList.toggle('on', k === n));
    dsteps.forEach((b, k) => b.setAttribute('aria-current', k === n));
    $$('#dsteps .bar').forEach(b => b.getAnimations().forEach(a => a.cancel()));
    if (n === 1) playChat(run);
    if (n === 2) playNudge(run);
    if (n === 3) playBoards(run);
    if (fromUser) demoAuto = false;
    if (demoAuto) {
      barAnim = dsteps[n].querySelector('.bar').animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration: DEMO_HOLD[n], easing: 'linear', fill: 'forwards' });
      barAnim.onfinish = () => { if (run === demoRun) show((n + 1) % views.length); };
    }
  }
  dsteps.forEach(b => b.addEventListener('click', () => show(+b.dataset.v, true)));
  // start the demo when it scrolls into view, pause the bar while it is off screen
  let started = false;
  new IntersectionObserver(([en]) => {
    if (en.isIntersecting && !started) { started = true; show(0); }
    if (barAnim) en.isIntersecting ? barAnim.play() : barAnim.pause();
  }, { threshold: .35 }).observe($('#demo'));
})();
</script>

<script>
(() => {
  const meta = document.querySelector('meta[name="rrp-sw"]');
  if (!meta || !('serviceWorker' in navigator)) return;
  const src = meta.getAttribute('content');
  if (!src) return;
  window.addEventListener('load', () => {
    navigator.serviceWorker.register(src).catch(() => {});
  });
})();
</script>
</body>
</html>
