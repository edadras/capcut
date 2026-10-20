<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FFmpeg / FFprobe binaries
    |--------------------------------------------------------------------------
    */
    'ffmpeg' => env('REEL_MAKER_FFMPEG', 'ffmpeg'),
    'ffprobe' => env('REEL_MAKER_FFPROBE', 'ffprobe'),

    // Max seconds a single render may take before being killed.
    'timeout' => (int) env('REEL_MAKER_TIMEOUT', 900),

    /*
    |--------------------------------------------------------------------------
    | Output format (Instagram Reels & Stories are both 9:16, 1080x1920)
    |--------------------------------------------------------------------------
    */
    'width' => 1080,
    'height' => 1920,
    'fps' => 30,
    'crf' => 21,
    'preset' => env('REEL_MAKER_PRESET', 'veryfast'),

    // Upscale factor before zoompan; higher = smoother Ken Burns, slower render.
    'zoompan_prescale' => 1.5,

    // Max duration per format (seconds).
    'max_duration' => [
        'story' => 60,
        'reel' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'upload_disk' => env('REEL_MAKER_UPLOAD_DISK', 'local'),
    'output_disk' => env('REEL_MAKER_OUTPUT_DISK', 'public'),
    'music_disk' => env('REEL_MAKER_MUSIC_DISK', 'local'),
    'directory' => 'reel-maker',

    'max_image_kb' => 15360,

    // Center multi-line captions (needs FFmpeg >= 6.1; set false on older builds).
    'center_text' => (bool) env('REEL_MAKER_CENTER_TEXT', true),

    // Font used for captions. Empty = bundled Vazirmatn Bold (supports Persian & Latin).
    'font' => env('REEL_MAKER_FONT'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    */
    'queue' => env('REEL_MAKER_QUEUE', 'default'),
    'connection' => env('REEL_MAKER_QUEUE_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'reels',
        'middleware' => ['web'],
        'api_prefix' => 'api/reels',
        'api_middleware' => ['api'],
    ],

    'per_page' => 24,
];
