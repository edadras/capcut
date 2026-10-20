<?php

namespace ReelMaker;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReelMaker\Console\GeneratePreviewsCommand;
use ReelMaker\Console\GenerateTemplatesCommand;
use ReelMaker\Console\ImportMusicCommand;
use ReelMaker\Rendering\AudioAnalyzer;
use ReelMaker\Rendering\FfmpegCommandBuilder;

class ReelMakerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reel-maker.php', 'reel-maker');

        if (! $this->app['config']['reel-maker.font']) {
            $this->app['config']['reel-maker.font'] = realpath(__DIR__.'/../resources/fonts/Vazirmatn-Bold.ttf');
        }

        $this->app->singleton(FfmpegCommandBuilder::class, fn ($app) => new FfmpegCommandBuilder($app['config']['reel-maker.ffmpeg']));
        $this->app->singleton(AudioAnalyzer::class, fn ($app) => new AudioAnalyzer(
            $app['config']['reel-maker.ffmpeg'],
            $app['config']['reel-maker.ffprobe'],
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'reel-maker');

        if (config('reel-maker.routes.enabled')) {
            Route::prefix(config('reel-maker.routes.prefix'))
                ->middleware(config('reel-maker.routes.middleware'))
                ->as('reel-maker.')
                ->group(__DIR__.'/../routes/web.php');

            Route::prefix(config('reel-maker.routes.api_prefix'))
                ->middleware(config('reel-maker.routes.api_middleware'))
                ->as('reel-maker.api.')
                ->group(__DIR__.'/../routes/api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTemplatesCommand::class,
                ImportMusicCommand::class,
                GeneratePreviewsCommand::class,
            ]);

            $this->publishes([__DIR__.'/../config/reel-maker.php' => config_path('reel-maker.php')], 'reel-maker-config');
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/reel-maker')], 'reel-maker-views');
        }
    }
}
