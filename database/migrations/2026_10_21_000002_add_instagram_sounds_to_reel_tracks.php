<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks can now also be "instagram" sounds: no audio file is stored; the video is
 * rendered silent (beat-synced if the BPM is known) and the user adds the song from
 * Instagram's licensed music library when posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reel_tracks', function (Blueprint $table) {
            $table->string('kind', 16)->default('file')->index()->after('external_id');
            $table->string('region', 32)->nullable()->index()->after('mood');
            $table->json('links')->nullable()->after('source_url');
            $table->date('trending_at')->nullable()->after('links');
            $table->string('path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reel_tracks', function (Blueprint $table) {
            $table->dropColumn(['kind', 'region', 'links', 'trending_at']);
        });
    }
};
