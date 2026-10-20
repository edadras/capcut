<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reel_tracks', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('id');
            $table->string('license_url')->nullable()->after('license');
        });
    }

    public function down(): void
    {
        Schema::table('reel_tracks', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['external_id', 'license_url']);
        });
    }
};
