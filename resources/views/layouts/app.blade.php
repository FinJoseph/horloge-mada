<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b1026">
    <meta name="description" content="Horloge intelligente du call center — heure officielle de Madagascar">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Horloge Mada">
    <meta property="og:title" content="Horloge Mada">
    <meta property="og:description" content="Horloge intelligente du call center — heure officielle de Madagascar, progression de journée, pause, paie et chat.">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    <meta name="twitter:card" content="summary">
    <title>Horloge Mada</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2322d3ee' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 6v6l4 2'/%3E%3C/svg%3E">
    <style>
        #preloader{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:radial-gradient(120% 120% at 50% 0%,#131a36 0%,#0b1026 55%,#070b1a 100%);transition:opacity .6s ease,visibility .6s ease}
        #preloader.hide{opacity:0;visibility:hidden;pointer-events:none}
        .pre-inner{text-align:center;transform:translateY(-4vh)}
        .pre-logo{position:relative;width:96px;height:96px;margin:0 auto;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(145deg,#22d3ee,#a855f7);box-shadow:0 0 40px rgba(34,211,238,.45),0 0 90px rgba(168,85,247,.25);animation:preBreathe 1.8s ease-in-out infinite}
        .pre-logo::before{content:'';position:absolute;inset:-14px;border-radius:9999px;border:2px dashed rgba(34,211,238,.4);animation:preSpin 9s linear infinite}
        .pre-logo::after{content:'';position:absolute;inset:-14px;border-radius:9999px;border-top:2px solid rgba(168,85,247,.8);animation:preSpin 2.6s linear infinite}
        .pre-logo svg{width:44px;height:44px}
        .pre-title{margin-top:28px;font-size:1.5rem;font-weight:800;letter-spacing:.12em;background:linear-gradient(90deg,#22d3ee,#a855f7,#f59e0b);-webkit-background-clip:text;background-clip:text;color:transparent;animation:preGlow 2.4s ease-in-out infinite}
        .pre-sub{margin-top:8px;font-size:.78rem;color:rgba(255,255,255,.55);letter-spacing:.35em;text-transform:uppercase;animation:preFade 2.4s ease-in-out infinite}
        .pre-bar{width:220px;height:5px;margin:26px auto 0;border-radius:9999px;background:rgba(255,255,255,.1);overflow:hidden}
        .pre-bar span{display:block;height:100%;width:40%;border-radius:9999px;background:linear-gradient(90deg,#22d3ee,#a855f7);animation:preLoad 1.2s ease-in-out infinite}
        .pre-percent{margin-top:14px;font-size:.72rem;color:rgba(255,255,255,.4);font-variant-numeric:tabular-nums}
        .pre-cat{position:absolute;bottom:9vh;left:50%;transform:translateX(-50%);font-size:44px;animation:preCat 2.2s ease-in-out infinite;filter:drop-shadow(0 6px 16px rgba(0,0,0,.5))}
        @keyframes preBreathe{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
        @keyframes preSpin{to{transform:rotate(360deg)}}
        @keyframes preGlow{0%,100%{opacity:.85}50%{opacity:1}}
        @keyframes preFade{0%,100%{opacity:.5}50%{opacity:1}}
        @keyframes preLoad{0%{transform:translateX(-110%)}50%{transform:translateX(0)}100%{transform:translateX(310%)}}
        @keyframes preCat{0%,100%{transform:translateX(-50%) translateY(0) rotate(-4deg)}50%{transform:translateX(-50%) translateY(-10px) rotate(4deg)}}
    </style>
    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans text-white antialiased">
    <div id="preloader" aria-hidden="true">
        <div class="pre-inner">
            <div class="pre-logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </div>
            <div class="pre-title">Horloge Mada</div>
            <div class="pre-sub">Fianarantsoa</div>
            <div class="pre-bar"><span></span></div>
            <div class="pre-percent">Chargement...</div>
        </div>
        <div class="pre-cat">🐱</div>
    </div>
    {{ $slot }}
    @livewireScripts
</body>
</html>
