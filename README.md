# Reel Maker — ساخت خودکار ریلز و استوری برای لاراول

این پکیج به سایت لاراولی شما یک «سازنده ریلز/استوری» اضافه می‌کند:
کاربر یک قالب انتخاب می‌کند، عکس‌هایش را آپلود می‌کند (و اگر بخواهد یک متن کوتاه می‌نویسد)، و سیستم با FFmpeg
یک ویدیوی عمودی ۱۰۸۰×۱۹۲۰ با آهنگ، ترنزیشن، حرکت دوربین (Ken Burns)، فیلتر رنگی و متن فارسی می‌سازد.

- **افکت‌ها و ترنزیشن‌های حرفه‌ای به سبک CapCut**: زوم ضربه‌ای، زوم چرخشی، چرخش، ویپ (۴ جهت)، فلش، گلیچ، لرزش و محو حرکتی؛
  همه با **موشن‌بلور واقعی** (رندر ۴ برابر فریم‌ریت و ترکیب) و دقیقاً روی ضرب آهنگ. به‌علاوه ۳۶ ترنزیشن کلاسیک (اسلاید، وایپ، دایره، …).
- **افکت روی ضرب**: پالس زوم، فلش یا لرزش روی هر ضرب آهنگ.
- **پوشش‌ها**: نشت نور (Light leak) متحرک، دانه فیلم، نوار سینمایی، فیلم قدیمی؛ **متن متحرک** (پاپ، ورود از پایین، محو).
- **هزاران قالب** به‌صورت خودکار از ترکیب ۱۶ دسته‌بندی × ۴۷ ترنزیشن/افکت × ۸ حرکت × ۱۴ فیلتر × ۴ افکت ضرب × ۵ پوشش × ۴ ریتم × … × آهنگ‌ها؛
  دسته‌های پرانرژی (ترند، ورزشی، تولد، فشن) افکت‌های تند و دسته‌های احساسی (عاشقانه، خاطرات، عروسی) افکت‌های نرم می‌گیرند.
- **کتابخانه موسیقی خودکار**: دانلود آهنگ‌های Creative Commons قابل‌استفاده تجاری از ccMixter و Jamendo، با تشخیص BPM و **قسمت اوج آهنگ**.
- **هماهنگی با ضرب آهنگ**: BPM هر آهنگ موقع ایمپورت تشخیص داده می‌شود و تعویض عکس‌ها دقیقاً روی ضرب انجام می‌شود.
- **فارسی کامل**: رابط کاربری راست‌به‌چپ و متن فارسی روی ویدیو با فونت وزیرمتن (همراه پکیج).
- **صف (Queue)**: رندر در پس‌زمینه انجام می‌شود و صفحه وضعیت خودکار به‌روز می‌شود.
- **API JSON** برای اپ موبایل یا فرانت‌اند جدا.

> **درباره آهنگ‌های CapCut:** آهنگ‌های کتابخانه CapCut «عمومی» نیستند؛ بیشترشان آهنگ‌های تجاری‌اند که CapCut
> فقط برای استفاده داخل اپ خودش از ناشران مجوز گرفته. پیدا شدن یک آهنگ در اینترنت به معنی آزاد بودن آن نیست.
> دانلود و قرار دادنشان در سایت شما نقض کپی‌رایت است، اینستاگرام صدای ویدیو را قطع می‌کند و سایت ممکن است شکایت DMCA بگیرد.
> به همین دلیل این پروژه فقط آهنگ‌هایی را دانلود می‌کند که مجوزشان (CC BY یا CC0) استفاده تجاری را اجازه می‌دهد.
> اگر آهنگ‌های مشهور می‌خواهید، راه قانونی خرید لایسنس از Epidemic Sound، Artlist یا APIهای تجاری موسیقی است.

## پیش‌نیازها

- PHP 8.2+ و Laravel 11، 12 یا 13
- FFmpeg (نسخه ۶.۱ یا بالاتر توصیه می‌شود؛ روی نسخه‌های قدیمی‌تر `REEL_MAKER_CENTER_TEXT=false` بگذارید)
- یک Queue Worker (Redis یا database)

```bash
sudo apt install ffmpeg
```

## نصب

اگر این ریپو را کنار پروژه لاراول دارید (مثلاً در `packages/reel-maker`):

```jsonc
// composer.json پروژه اصلی
"repositories": [
    { "type": "path", "url": "packages/reel-maker" }
]
```

```bash
composer require edadras/reel-maker:@dev
php artisan migrate
php artisan storage:link
php artisan vendor:publish --tag=reel-maker-config   # اختیاری
```

## ۱) اضافه کردن آهنگ‌ها

### دانلود خودکار (پیشنهادی)

```bash
# ccMixter — بدون نیاز به کلید، مجوز CC BY
php artisan reel-maker:fetch-music --per-mood=50

# Jamendo — بیش از ۵۰۰ هزار آهنگ؛ یک Client ID رایگان از https://devportal.jamendo.com بگیرید
REEL_MAKER_JAMENDO_CLIENT_ID=xxxx php artisan reel-maker:fetch-music --provider=jamendo --per-mood=200

# فقط چند حال‌وهوا:
php artisan reel-maker:fetch-music --mood=happy --mood=romantic --per-mood=100
```

فقط آهنگ‌های **CC BY** و **CC0** پذیرفته می‌شوند (NonCommercial، NoDerivs و ShareAlike رد می‌شوند).
برای هر آهنگ مدت، BPM و **شروع قسمت اوج** (بلندترین بخش ۲۰ ثانیه‌ای، معمولاً کُر یا دراپ) تشخیص داده می‌شود و ویدیو از همان‌جا شروع می‌شود.

**ذکر منبع الزامی است:** مجوز CC BY می‌گوید نام آهنگ و هنرمند باید ذکر شود. صفحه نتیجه یک متن آماده (با دکمه کپی)
برای کپشن پست نشان می‌دهد و API هم آن را در فیلد `music_credit` برمی‌گرداند.

### آهنگ‌های خودتان

آهنگ‌ها را در پوشه‌هایی به نام «حال‌وهوا» (mood) بگذارید. نام فایل به شکل `Artist - Title.mp3` باشد:

```
music/
├── happy/        شاد
├── romantic/     عاشقانه
├── energetic/    پرانرژی
├── pop/  calm/  sad/  epic/  chill/  corporate/
```

```bash
php artisan reel-maker:import-music /path/to/music --license="Pixabay Content License"
```

مدت، BPM و قسمت اوج خودکار محاسبه می‌شود (ستون `highlight_start` در جدول `reel_tracks` را می‌توانید دستی هم تغییر دهید).

منابع پیشنهادی موسیقی با مجوز استفاده در ویدیو:
[Pixabay Music](https://pixabay.com/music/)،
[YouTube Audio Library](https://studio.youtube.com/)،
[Free Music Archive](https://freemusicarchive.org/) (فقط مجوزهای CC-BY)،
[Uppbeat](https://uppbeat.io/)، یا خرید لایسنس از Epidemic Sound / Artlist.

## ۲) ساخت هزاران قالب

```bash
php artisan reel-maker:generate-templates --count=5000
# فقط چند دسته:
php artisan reel-maker:generate-templates --count=1000 --category=birthday --category=love --seed=2
```

با `seed` یکسان همیشه همان قالب‌ها ساخته می‌شوند، پس اجرای دوباره چیزی را تکراری نمی‌کند. با seed دیگر قالب‌های جدید اضافه می‌شوند.
بعد از اضافه کردن آهنگ‌های جدید دوباره اجرا کنید تا به قالب‌ها وصل شوند (`--fresh` قالب‌های استفاده‌نشده را پاک و دوباره می‌سازد).

هر قالب یک JSON ساده است که می‌توانید دستی هم بسازید یا ویرایش کنید:

```jsonc
{
  "format": "reel",              // reel (تا ۹۰ ثانیه) یا story (تا ۶۰ ثانیه)
  "layout": "blur",              // fill = تمام صفحه، blur = عکس کامل با پس‌زمینه محو
  "slide_duration": 2.2,         // ثانیه برای هر عکس (با beat_sync به ضرب آهنگ گرد می‌شود)
  "transitions": ["zoom_in", "whip_left", "slideleft"],   // به‌ترتیب تکرار می‌شوند؛ ["cut"] = کات سریع
  // افکت‌ها: zoom_in, zoom_spin, spin, whip_left/right/up/down, flash, glitch, shake, blur
  // کلاسیک: fade, dissolve, slideleft, wiperight, circleopen, pixelize, … (۳۶ نوع xfade)
  "transition_duration": 0.5,
  "intro": "zoom_in",            // افکت شروع ویدیو (اختیاری)
  "beat_effect": "pulse",        // none, pulse, flash, shake
  "overlay": "light_leak",       // none, light_leak, grain, letterbox, film
  "text_animation": "pop",       // fade, pop, slide_up
  "motion": "mixed",             // none, zoom_in, zoom_out, pan_left, pan_right, pan_up, pan_down, mixed
  "filter": "vintage",           // none, warm, cool, bw, vintage, vivid, faded, cinematic, drama, sepia, pastel, golden, night, retro
  "text_style": "box",           // none, center, top, bottom, box
  "beat_sync": true,
  "min_images": 3,
  "max_images": 12
}
```

## ۳) پیش‌نمایش قالب‌ها (اختیاری)

```bash
php artisan reel-maker:previews --limit=500            # با عکس‌های نمونه رنگی
php artisan reel-maker:previews --images=/path/to/demo-photos --limit=500
```

هر پیش‌نمایش حدود ۱–۲ ثانیه زمان می‌برد. قالب‌هایی که پیش‌نمایش ندارند در گالری با یک کارت انیمیشنی نمایش داده می‌شوند.

## ۴) اجرای صف

```bash
php artisan queue:work --queue=default --timeout=1000
```

## سرعت رندر

افکت‌ها فقط در چند صدم ثانیه کنار هر کات با کیفیت بالا (۴ برابر فریم‌ریت) رندر می‌شوند و بقیه کلیپ سبک است.
روی یک سرور ۴ هسته‌ای، هر ثانیه ویدیوی ۱۰۸۰p حدود ۱.۵ تا ۲ ثانیه زمان می‌برد. برای سرعت بیشتر
`REEL_MAKER_PRESET=superfast` بگذارید یا چند Worker صف اجرا کنید.

## صفحات و API

| مسیر | توضیح |
|---|---|
| `GET /reels` | گالری قالب‌ها (فیلتر: `category`، `format`، `sort=new`) |
| `GET /reels/t/{slug}` | انتخاب عکس‌ها، متن و آهنگ |
| `POST /reels/t/{slug}` | شروع ساخت ویدیو |
| `GET /reels/r/{uuid}` | وضعیت و دانلود ویدیو |
| `GET /api/reels/templates?category=love&photos=5` | لیست قالب‌ها (JSON) |
| `GET /api/reels/templates/{slug}` | جزئیات قالب و آهنگ‌های قابل انتخاب |
| `POST /api/reels/templates/{slug}/render` | `images[]`، `caption`، `track_id` → `202` |
| `GET /api/reels/renders/{uuid}` | `status`: pending / processing / done / failed و `url` |
| `GET /api/reels/tracks` | لیست آهنگ‌ها |

پیشوند مسیرها و middlewareها در `config/reel-maker.php` قابل تغییرند. اگر می‌خواهید فقط کاربران لاگین‌شده ویدیو بسازند،
`'middleware' => ['web', 'auth']` و برای API `['api', 'auth:sanctum']` بگذارید. ویدیوهایی که کاربر لاگین‌شده ساخته فقط برای خودش قابل مشاهده‌اند.

برای شخصی‌سازی ظاهر صفحات:

```bash
php artisan vendor:publish --tag=reel-maker-views
```

## تنظیمات مهم (`.env`)

```dotenv
REEL_MAKER_FFMPEG=/usr/bin/ffmpeg
REEL_MAKER_FFPROBE=/usr/bin/ffprobe
REEL_MAKER_PRESET=veryfast          # سرعت/کیفیت x264
REEL_MAKER_TIMEOUT=900
REEL_MAKER_OUTPUT_DISK=public       # یا s3
REEL_MAKER_QUEUE=reels
REEL_MAKER_CENTER_TEXT=true         # روی FFmpeg قدیمی‌تر از 6.1: false
REEL_MAKER_FONT=                    # خالی = وزیرمتن همراه پکیج
```

## ساختار کد

```
src/
├── Support/Catalog.php              بلوک‌های سازنده: ترنزیشن، حرکت، فیلتر، دسته‌بندی، mood
├── Support/TemplateGenerator.php    تولید قطعی (deterministic) هزاران قالب
├── Rendering/FfmpegCommandBuilder   تبدیل قالب + عکس‌ها به دستور FFmpeg (بدون shell)
├── Rendering/AudioAnalyzer          مدت آهنگ، تشخیص BPM و قسمت اوج
├── Music/                           دانلود از ccMixter و Jamendo (فقط CC BY / CC0)
├── Support/TrackImporter            افزودن آهنگ به کتابخانه
├── Rendering/Renderer               اجرای رندر و ذخیره خروجی
├── Jobs/RenderReelJob               Job صف
├── Http/…                           کنترلرها و ولیدیشن
└── Console/…                        fetch-music، import-music، generate-templates، previews
```

## تست

```bash
composer install
vendor/bin/phpunit
```

فونت وزیرمتن تحت مجوز SIL Open Font License همراه پکیج است (`resources/fonts/OFL.txt`).
