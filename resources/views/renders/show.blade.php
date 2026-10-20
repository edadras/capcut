@extends('reel-maker::layouts.app')

@section('title', 'ویدیوی شما')

@section('content')
<div class="panel" style="max-width:420px;margin:0 auto;text-align:center" id="render"
     data-status-url="{{ route('reel-maker.renders.show', $render) }}">
    <div id="pending" @if ($render->status === 'done' || $render->status === 'failed') hidden @endif>
        <div style="font-size:48px">⏳</div>
        <h2>در حال ساخت ویدیو…</h2>
        <p class="muted">معمولاً کمتر از یک دقیقه طول می‌کشد. این صفحه خودکار به‌روز می‌شود.</p>
    </div>

    <div id="done" @if ($render->status !== 'done') hidden @endif>
        <video id="player" src="{{ $render->outputUrl() }}" controls playsinline
               style="width:100%;aspect-ratio:9/16;border-radius:var(--radius);background:#000"></video>
        <p style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn" id="download" href="{{ $render->outputUrl() }}" download>⬇️ دانلود</a>
            <a class="btn ghost" href="{{ route('reel-maker.templates.show', $render->template) }}">ساخت دوباره</a>
        </p>
        @if ($credit = ($render->track ?? $render->template->track)?->credit())
            <div style="text-align:start;margin-top:8px">
                <label for="credit">این متن را در کپشن پست بگذارید (شرط مجوز آهنگ):</label>
                <textarea id="credit" rows="2" readonly dir="ltr" onclick="this.select()">{{ $credit }}</textarea>
                <button class="btn ghost" type="button" style="margin-top:8px"
                        onclick="navigator.clipboard.writeText(document.getElementById('credit').value);this.textContent='کپی شد ✓'">کپی</button>
            </div>
        @endif
    </div>

    <div id="failed" @if ($render->status !== 'failed') hidden @endif>
        <div style="font-size:48px">😕</div>
        <h2>ساخت ویدیو ناموفق بود</h2>
        <a class="btn" href="{{ route('reel-maker.templates.show', $render->template) }}">تلاش دوباره</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const box = document.getElementById('render');
    const show = id => ['pending', 'done', 'failed'].forEach(k => document.getElementById(k).hidden = k !== id);
    const poll = async () => {
        try {
            const r = await fetch(box.dataset.statusUrl, { headers: { Accept: 'application/json' } });
            const data = await r.json();
            if (data.status === 'done') {
                document.getElementById('player').src = data.url;
                document.getElementById('download').href = data.url;
                return show('done');
            }
            if (data.status === 'failed') return show('failed');
        } catch (e) {}
        setTimeout(poll, 3000);
    };
    if (!document.getElementById('pending').hidden) poll();
})();
</script>
@endpush
