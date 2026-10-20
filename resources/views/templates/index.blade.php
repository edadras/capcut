@extends('reel-maker::layouts.app')

@section('content')
    @php($current = request('category'))
    <nav class="chips">
        <a class="chip {{ ! $current ? 'on' : '' }}" href="{{ route('reel-maker.templates.index', request()->except(['category', 'page'])) }}">همه</a>
        @foreach ($categories as $key => $cat)
            <a class="chip {{ $current === $key ? 'on' : '' }}"
               href="{{ route('reel-maker.templates.index', array_merge(request()->except('page'), ['category' => $key])) }}">{{ $cat['label'] }}</a>
        @endforeach
    </nav>
    <nav class="chips">
        @foreach (['' => 'ریلز و استوری', 'reel' => 'فقط ریلز', 'story' => 'فقط استوری'] as $key => $label)
            <a class="chip {{ (string) request('format') === $key ? 'on' : '' }}"
               href="{{ route('reel-maker.templates.index', array_merge(request()->except(['format', 'page']), ['format' => $key ?: null])) }}">{{ $label }}</a>
        @endforeach
        @foreach (['' => 'محبوب‌ترین', 'new' => 'جدیدترین'] as $key => $label)
            <a class="chip {{ (string) request('sort') === $key ? 'on' : '' }}"
               href="{{ route('reel-maker.templates.index', array_merge(request()->except(['sort', 'page']), ['sort' => $key ?: null])) }}">{{ $label }}</a>
        @endforeach
    </nav>

    <p class="muted">{{ number_format($templates->total()) }} قالب</p>

    <div class="grid">
        @forelse ($templates as $template)
            @include('reel-maker::templates._card', ['template' => $template])
        @empty
            <p class="muted">قالبی پیدا نشد.</p>
        @endforelse
    </div>

    {{ $templates->onEachSide(1)->links('reel-maker::pagination') }}
@endsection
