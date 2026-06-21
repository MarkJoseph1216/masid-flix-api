<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_rooms', function (Blueprint $table) {
            $table->integer('season')->default(1)->after('media_type');
            $table->integer('episode')->default(1)->after('season');
        });
    }

    public function down(): void
    {
        Schema::table('watch_rooms', function (Blueprint $table) {
            $table->dropColumn(['season', 'episode']);
        });
    }
};