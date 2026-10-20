<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ساخت ریلز و استوری')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#0f0f14; --card:#1a1a22; --muted:#9a9aab; --text:#f4f4f8; --accent:#ff3d71; --accent2:#7b5cff; --radius:16px; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Vazirmatn, Tahoma, sans-serif; background:var(--bg); color:var(--text); }
        a { color:inherit; text-decoration:none; }
        .wrap { max-width:1200px; margin:0 auto; padding:16px; }
        header.top { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 0 20px; }
        header.top h1 { font-size:22px; margin:0; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:0; border-radius:999px; padding:12px 22px; font:inherit; font-weight:600; cursor:pointer; background:linear-gradient(135deg,var(--accent),var(--accent2)); color:#fff; }
        .btn.ghost { background:var(--card); }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .chips { display:flex; gap:8px; overflow-x:auto; padding-bottom:8px; scrollbar-width:none; }
        .chip { white-space:nowrap; padding:8px 16px; border-radius:999px; background:var(--card); color:var(--muted); font-size:14px; }
        .chip.on { background:var(--text); color:var(--bg); }
        .grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; margin-top:14px; }
        .card { background:var(--card); border-radius:var(--radius); overflow:hidden; display:block; }
        .thumb { aspect-ratio:9/16; position:relative; overflow:hidden; background:#222; }
        .thumb video { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; display:block; }
        .thumb .badge { position:absolute; top:8px; inset-inline-start:8px; background:rgba(0,0,0,.55); padding:3px 9px; border-radius:999px; font-size:12px; }
        .card .meta { padding:10px 12px; font-size:13px; }
        .card .meta .sub { color:var(--muted); font-size:12px; margin-top:4px; }
        .ph { position:absolute; inset:0; background:linear-gradient(160deg,var(--c1),var(--c2)); animation:kb 4s ease-in-out infinite alternate; }
        @keyframes kb { from { transform:scale(1); } to { transform:scale(1.15); } }
        .f-bw .ph { filter:grayscale(1); } .f-sepia .ph,.f-vintage .ph { filter:sepia(.7); } .f-faded .ph { filter:contrast(.85) brightness(1.05); }
        .f-vivid .ph { filter:saturate(1.6); } .f-drama .ph { filter:contrast(1.35); } .f-night .ph { filter:brightness(.7) hue-rotate(20deg); }
        .panel { background:var(--card); border-radius:var(--radius); padding:18px; }
        .muted { color:var(--muted); }
        .pagination { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin:24px 0; list-style:none; padding:0; }
        .pagination a, .pagination span { padding:6px 12px; border-radius:10px; background:var(--card); font-size:14px; }
        .errors { background:#3a1520; color:#ffb3c4; padding:12px 16px; border-radius:12px; margin-bottom:14px; }
        input[type=text], select, textarea { width:100%; background:#101016; color:var(--text); border:1px solid #2c2c38; border-radius:12px; padding:12px; font:inherit; }
        label { display:block; margin:14px 0 6px; font-weight:600; font-size:14px; }
    </style>
    @stack('head')
</head>
<body>
<div class="wrap">
    <header class="top">
        <h1><a href="{{ route('reel-maker.templates.index') }}">🎬 ساخت ریلز و استوری</a></h1>
    </header>
    @yield('content')
</div>
<script>
    // Play gallery previews only while they are on screen.
    (() => {
        const io = new IntersectionObserver(entries => entries.forEach(e => e.isIntersecting ? e.target.play().catch(() => {}) : e.target.pause()), { threshold: 0.4 });
        document.querySelectorAll('video[data-autoplay]').forEach(v => io.observe(v));
    })();
</script>
@stack('scripts')
</body>
</html>
