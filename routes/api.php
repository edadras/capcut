<?php

use Illuminate\Support\Facades\Route;
use ReelMaker\Http\Controllers\RenderController;
use ReelMaker\Http\Controllers\TemplateController;
use ReelMaker\Models\Track;

Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
Route::get('templates/{template}', [TemplateController::class, 'show'])->name('templates.show');
Route::post('templates/{template}/render', [RenderController::class, 'store'])->middleware('throttle:10,1')->name('renders.store');
Route::get('renders/{render}', [RenderController::class, 'show'])->name('renders.show');
Route::get('tracks', fn () => Track::active()->orderBy('mood')->orderBy('title')->get(['id', 'title', 'artist', 'mood', 'duration', 'bpm']))->name('tracks.index');
