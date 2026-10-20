@extends('reel-maker::layouts.app')

@section('title', $template->name)

@push('head')
<style>
    .layout { display:grid; grid-template-columns:minmax(0,320px) minmax(0,1fr); gap:20px; align-items:start; }
    @media (max-width:760px) { .layout { grid-template-columns:minmax(0,1fr); } }
    .drop { border:2px dashed #3a3a48; border-radius:var(--radius); padding:26px; text-align:center; cursor:pointer; }
    .drop.over { border-color:var(--accent); }
    .photos { display:grid; grid-template-columns:repeat(auto-fill,minmax(84px,1fr)); gap:8px; margin-top:12px; }
    .photo { position:relative; aspect-ratio:9/16; border-radius:10px; overflow:hidden; background:#000; cursor:grab; }
    .photo img { width:100%; height:100%; object-fit:cover; }
    .photo .n { position:absolute; top:4px; inset-inline-start:4px; background:rgba(0,0,0,.6); border-radius:999px; font-size:11px; padding:1px 7px; }
    .photo .x { position:absolute; top:4px; inset-inline-end:4px; background:rgba(0,0,0,.6); border:0; color:#fff; border-radius:999px; width:22px; height:22px; cursor:pointer; }
    .photo.dragging { opacity:.4; }
</style>
@endpush

@section('content')
<div class="layout">
    <div>
        @include('reel-maker::templates._card', ['template' => $template])
    </div>

    <form class="panel" method="post" enctype="multipart/form-data" action="{{ route('reel-maker.renders.store', $template) }}" id="reel-form">
        @csrf
        <h2 style="margin-top:0">{{ $template->name }}</h2>
        <p class="muted">{{ $template->min_images }} تا {{ $template->max_images }} عکس انتخاب کنید. ترتیب را با کشیدن عکس‌ها تغییر دهید.</p>

        @if ($errors->any())
            <div class="errors">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
        @endif

        <div class="drop" id="drop">📷 عکس‌ها را اینجا رها کنید یا کلیک کنید</div>
        <input type="file" id="picker" accept="image/jpeg,image/png,image/webp" multiple hidden>
        <input type="file" name="images[]" id="images" multiple hidden>
        <div class="photos" id="photos"></div>
        <p class="muted" id="count"></p>

        @if (($template->config['text_style'] ?? 'none') !== 'none')
            <label for="caption">متن روی ویدیو (اختیاری)</label>
            <input type="text" name="caption" id="caption" maxlength="120" value="{{ old('caption') }}" placeholder="مثلاً: تولدت مبارک ❤️">
        @endif

        <label for="track_id">آهنگ</label>
        <select name="track_id" id="track_id">
            <optgroup label="آهنگ‌های کتابخانه (داخل ویدیو)">
                @foreach ($tracks as $track)
                    <option value="{{ $track->id }}" @selected((int) old('track_id', $template->track_id) === $track->id)>
                        {{ $track->title }}{{ $track->artist ? ' — '.$track->artist : '' }}
                    </option>
                @endforeach
            </optgroup>
            @php($regions = ['global' => 'جهانی', 'turkey' => 'ترکیه', 'rising' => 'رو به رشد', 'autumn' => 'پاییزی', 'persian' => 'فارسی'])
            @if ($trending->isNotEmpty())
                <optgroup label="🔥 ترندهای اینستاگرام (در اینستاگرام اضافه می‌شود)">
                    @foreach ($trending as $track)
                        <option value="{{ $track->id }}" data-instagram="1" @selected((int) old('track_id') === $track->id)>
                            🔥 {{ $track->title }}{{ $track->artist ? ' — '.$track->artist : '' }} · {{ $regions[$track->region] ?? '' }}
                        </option>
                    @endforeach
                </optgroup>
            @endif
        </select>
        <p class="muted" id="ig-note" hidden style="font-size:13px;line-height:1.9">
            ویدیو بدون صدا ساخته می‌شود<span id="ig-beat"></span>.
            موقع انتشار ریلز در اینستاگرام روی آیکون 🎵 موسیقی بزنید و همین آهنگ را جست‌وجو کنید؛
            اینستاگرام مجوز این آهنگ‌ها را خودش دارد. (در بعضی حساب‌های تجاری ممکن است آهنگ در دسترس نباشد.)
        </p>

        <div style="margin-top:20px">
            <button class="btn" type="submit" id="submit" disabled>✨ ساخت ویدیو</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const min = {{ $template->min_images }}, max = {{ $template->max_images }};
    const drop = document.getElementById('drop'), picker = document.getElementById('picker');
    const input = document.getElementById('images'), list = document.getElementById('photos');
    const count = document.getElementById('count'), submit = document.getElementById('submit');
    let files = [], dragIndex = null;

    const sync = () => {
        const dt = new DataTransfer();
        files.forEach(f => dt.items.add(f));
        input.files = dt.files;
        count.textContent = `${files.length} عکس انتخاب شده`;
        submit.disabled = files.length < min || files.length > max;
        list.innerHTML = '';
        files.forEach((f, i) => {
            const el = document.createElement('div');
            el.className = 'photo'; el.draggable = true;
            el.innerHTML = `<img alt=""><span class="n">${i + 1}</span><button type="button" class="x" aria-label="حذف">×</button>`;
            el.querySelector('img').src = URL.createObjectURL(f);
            el.querySelector('.x').onclick = () => { files.splice(i, 1); sync(); };
            el.ondragstart = () => { dragIndex = i; el.classList.add('dragging'); };
            el.ondragend = () => el.classList.remove('dragging');
            el.ondragover = e => e.preventDefault();
            el.ondrop = e => {
                e.preventDefault(); e.stopPropagation();
                if (dragIndex === null || dragIndex === i) return;
                const [moved] = files.splice(dragIndex, 1);
                files.splice(i, 0, moved); dragIndex = null; sync();
            };
            list.appendChild(el);
        });
    };

    const add = incoming => {
        files = files.concat([...incoming].filter(f => f.type.startsWith('image/'))).slice(0, max);
        sync();
    };

    drop.onclick = () => picker.click();
    picker.onchange = () => { add(picker.files); picker.value = ''; };
    drop.ondragover = e => { e.preventDefault(); drop.classList.add('over'); };
    drop.ondragleave = () => drop.classList.remove('over');
    drop.ondrop = e => { e.preventDefault(); drop.classList.remove('over'); add(e.dataTransfer.files); };
    const track = document.getElementById('track_id'), note = document.getElementById('ig-note');
    const beats = @json($trending->pluck('bpm', 'id'));
    const toggleNote = () => {
        const opt = track.selectedOptions[0];
        note.hidden = !(opt && opt.dataset.instagram);
        document.getElementById('ig-beat').textContent = opt && beats[opt.value] ? ' و تعویض عکس‌ها روی ضرب همین آهنگ تنظیم می‌شود' : '';
    };
    track.onchange = toggleNote;
    toggleNote();
    document.getElementById('reel-form').onsubmit = () => { submit.disabled = true; submit.textContent = 'در حال ارسال…'; };
    sync();
})();
</script>
@endpush
