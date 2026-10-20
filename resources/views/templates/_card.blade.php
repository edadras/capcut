@php
    $palette = [['#ff6b6b','#feca57'],['#48dbfb','#5f27cd'],['#1dd1a1','#10ac84'],['#ff9ff3','#f368e0'],['#54a0ff','#2e86de'],['#ff9f43','#ee5253']];
    [$c1, $c2] = $palette[$template->id % count($palette)];
    $preview = $template->preview_path ? Storage::disk(config('reel-maker.output_disk'))->url($template->preview_path) : null;
@endphp
<a class="card f-{{ $template->config['filter'] ?? 'none' }}" href="{{ route('reel-maker.templates.show', $template) }}">
    <div class="thumb">
        <div class="ph" style="--c1:{{ $c1 }};--c2:{{ $c2 }}"></div>
        @if ($preview)
            <video src="{{ $preview }}" muted loop playsinline preload="metadata" data-autoplay></video>
        @endif
        <span class="badge">{{ $template->format === 'story' ? 'استوری' : 'ریلز' }} · {{ $template->min_images }}–{{ $template->max_images }} عکس</span>
    </div>
    <div class="meta">
        <div>{{ $template->name }}</div>
        <div class="sub">🎵 {{ $template->track?->title ?? 'بدون آهنگ' }}</div>
    </div>
</a>
