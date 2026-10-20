<?php

use Illuminate\Support\Facades\Route;
use ReelMaker\Http\Controllers\RenderController;
use ReelMaker\Http\Controllers\TemplateController;

Route::get('/', [TemplateController::class, 'index'])->name('templates.index');
Route::get('t/{template}', [TemplateController::class, 'show'])->name('templates.show');
Route::post('t/{template}', [RenderController::class, 'store'])->middleware('throttle:10,1')->name('renders.store');
Route::get('r/{render}', [RenderController::class, 'show'])->name('renders.show');
