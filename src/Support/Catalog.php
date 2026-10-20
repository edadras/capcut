<?php

namespace ReelMaker\Support;

/**
 * Building blocks every template is assembled from.
 * Each key maps to an FFmpeg implementation in the rendering layer.
 */
final class Catalog
{
    /** xfade transition names (see `ffmpeg -h filter=xfade`). "cut" = hard cut. */
    public const TRANSITIONS = [
        'cut', 'fade', 'fadeblack', 'fadewhite', 'dissolve', 'distance',
        'wipeleft', 'wiperight', 'wipeup', 'wipedown',
        'slideleft', 'slideright', 'slideup', 'slidedown',
        'smoothleft', 'smoothright', 'smoothup', 'smoothdown',
        'circleopen', 'circleclose', 'circlecrop', 'rectcrop',
        'vertopen', 'vertclose', 'horzopen', 'horzclose',
        'diagtl', 'diagbr', 'radial', 'pixelize', 'zoomin',
        'hblur', 'squeezev', 'squeezeh', 'coverleft', 'revealright',
    ];

    public const MOTIONS = [
        'none' => 'ثابت',
        'zoom_in' => 'زوم به داخل',
        'zoom_out' => 'زوم به بیرون',
        'pan_left' => 'حرکت به چپ',
        'pan_right' => 'حرکت به راست',
        'pan_up' => 'حرکت به بالا',
        'pan_down' => 'حرکت به پایین',
        'mixed' => 'ترکیبی',
    ];

    /** Color grades: key => [label, ffmpeg filter chain or null]. */
    public const FILTERS = [
        'none' => ['بدون فیلتر', null],
        'warm' => ['گرم', 'colorbalance=rs=0.08:gs=0.02:bs=-0.08:rm=0.05:bm=-0.05'],
        'cool' => ['سرد', 'colorbalance=rs=-0.07:bs=0.09:bm=0.05'],
        'bw' => ['سیاه و سفید', 'hue=s=0,eq=contrast=1.15'],
        'vintage' => ['وینتیج', 'curves=preset=vintage,vignette=PI/5'],
        'vivid' => ['زنده', 'eq=saturation=1.45:contrast=1.08'],
        'faded' => ['محو', 'eq=contrast=0.88:brightness=0.04:saturation=0.8'],
        'cinematic' => ['سینمایی', 'curves=preset=medium_contrast,colorbalance=rs=-0.05:bs=0.06:rh=0.06:bh=-0.04,vignette=PI/4'],
        'drama' => ['دراماتیک', 'eq=contrast=1.3:saturation=0.85,vignette=PI/3.5'],
        'sepia' => ['سپیا', 'colorchannelmixer=.393:.769:.189:0:.349:.686:.168:0:.272:.534:.131'],
        'pastel' => ['پاستلی', 'eq=saturation=0.7:brightness=0.06:gamma=1.1'],
        'golden' => ['طلایی', 'colorbalance=rm=0.1:gm=0.05:bm=-0.1:rh=0.05,eq=saturation=1.15'],
        'night' => ['شب', 'eq=brightness=-0.05:saturation=0.9,colorbalance=bs=0.1:bm=0.08'],
        'retro' => ['رترو', 'curves=preset=cross_process,noise=alls=8:allf=t'],
    ];

    /** Image placement: cover-crop the frame, or fit with a blurred background. */
    public const LAYOUTS = [
        'fill' => 'تمام صفحه',
        'blur' => 'پس‌زمینه محو',
    ];

    /** Pacing => seconds per photo. */
    public const PACING = [
        'flash' => 0.6,
        'fast' => 1.2,
        'normal' => 2.2,
        'slow' => 3.5,
    ];

    public const TEXT_STYLES = [
        'none' => 'بدون متن',
        'center' => 'وسط',
        'bottom' => 'پایین',
        'top' => 'بالا',
        'box' => 'کادر',
    ];

    /**
     * Categories mirror common Instagram template themes.
     * Each category has preferred moods (to match music) and stylistic leanings.
     */
    public const CATEGORIES = [
        'trend' => ['label' => 'ترند', 'moods' => ['energetic', 'pop'], 'pacing' => ['flash', 'fast'], 'filters' => ['vivid', 'none', 'cinematic', 'drama']],
        'love' => ['label' => 'عاشقانه', 'moods' => ['romantic', 'calm'], 'pacing' => ['normal', 'slow'], 'filters' => ['warm', 'pastel', 'faded', 'golden']],
        'birthday' => ['label' => 'تولد', 'moods' => ['happy', 'pop'], 'pacing' => ['fast', 'normal'], 'filters' => ['vivid', 'warm', 'pastel', 'none']],
        'travel' => ['label' => 'سفر', 'moods' => ['epic', 'happy', 'chill'], 'pacing' => ['fast', 'normal'], 'filters' => ['cinematic', 'vivid', 'golden', 'cool']],
        'memories' => ['label' => 'خاطرات', 'moods' => ['sad', 'calm', 'romantic'], 'pacing' => ['normal', 'slow'], 'filters' => ['vintage', 'faded', 'sepia', 'bw']],
        'friends' => ['label' => 'دوستان', 'moods' => ['happy', 'pop', 'energetic'], 'pacing' => ['flash', 'fast'], 'filters' => ['vivid', 'retro', 'none']],
        'family' => ['label' => 'خانواده', 'moods' => ['calm', 'happy'], 'pacing' => ['normal', 'slow'], 'filters' => ['warm', 'golden', 'none']],
        'wedding' => ['label' => 'عروسی', 'moods' => ['romantic', 'epic'], 'pacing' => ['normal', 'slow'], 'filters' => ['golden', 'pastel', 'cinematic', 'bw']],
        'fashion' => ['label' => 'فشن', 'moods' => ['energetic', 'pop', 'chill'], 'pacing' => ['flash', 'fast'], 'filters' => ['drama', 'bw', 'cool', 'cinematic']],
        'business' => ['label' => 'کسب‌وکار', 'moods' => ['corporate', 'chill'], 'pacing' => ['fast', 'normal'], 'filters' => ['none', 'vivid', 'cool']],
        'food' => ['label' => 'غذا', 'moods' => ['happy', 'chill'], 'pacing' => ['fast', 'normal'], 'filters' => ['warm', 'vivid', 'golden']],
        'sport' => ['label' => 'ورزشی', 'moods' => ['energetic', 'epic'], 'pacing' => ['flash', 'fast'], 'filters' => ['drama', 'cinematic', 'vivid']],
        'nature' => ['label' => 'طبیعت', 'moods' => ['calm', 'epic', 'chill'], 'pacing' => ['normal', 'slow'], 'filters' => ['vivid', 'cinematic', 'golden', 'cool']],
        'aesthetic' => ['label' => 'استتیک', 'moods' => ['chill', 'calm', 'sad'], 'pacing' => ['normal', 'slow'], 'filters' => ['faded', 'pastel', 'vintage', 'night']],
        'nowruz' => ['label' => 'نوروز', 'moods' => ['happy', 'calm'], 'pacing' => ['fast', 'normal'], 'filters' => ['vivid', 'warm', 'pastel']],
        'yalda' => ['label' => 'یلدا', 'moods' => ['romantic', 'calm'], 'pacing' => ['normal', 'slow'], 'filters' => ['warm', 'night', 'golden']],
    ];

    public const MOODS = [
        'energetic' => 'پرانرژی',
        'pop' => 'پاپ',
        'happy' => 'شاد',
        'romantic' => 'عاشقانه',
        'calm' => 'آرام',
        'sad' => 'غمگین',
        'epic' => 'حماسی',
        'chill' => 'چیل',
        'corporate' => 'رسمی',
    ];

    public static function filterChain(string $key): ?string
    {
        return self::FILTERS[$key][1] ?? null;
    }
}
