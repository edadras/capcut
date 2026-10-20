@if ($paginator->hasPages())
    <ul class="pagination">
        @if (! $paginator->onFirstPage())
            <li><a href="{{ $paginator->previousPageUrl() }}">قبلی</a></li>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <li><span>…</span></li>
            @elseif (is_array($element))
                @foreach ($element as $page => $url)
                    <li>@if ($page == $paginator->currentPage())<span class="chip on">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif</li>
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}">بعدی</a></li>
        @endif
    </ul>
@endif
