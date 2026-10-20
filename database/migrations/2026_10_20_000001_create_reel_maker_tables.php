<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('mood', 32)->index();
            $table->string('path');
            $table->float('duration')->default(0);
            $table->float('bpm')->nullable();
            $table->float('highlight_start')->default(0);
            $table->string('license')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('reel_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category', 32)->index();
            $table->string('format', 16)->default('reel')->index();
            $table->foreignId('track_id')->nullable()->constrained('reel_tracks')->nullOnDelete();
            $table->json('config');
            $table->unsignedTinyInteger('min_images');
            $table->unsignedTinyInteger('max_images');
            $table->string('preview_path')->nullable();
            $table->unsignedInteger('uses')->default(0)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('reel_renders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->index();
            $table->foreignId('template_id')->constrained('reel_templates')->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('reel_tracks')->nullOnDelete();
            $table->json('images');
            $table->text('caption')->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->string('output_path')->nullable();
            $table->float('duration')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_renders');
        Schema::dropIfExists('reel_templates');
        Schema::dropIfExists('reel_tracks');
    }
};
