<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_rooms', function (Blueprint $table) {
            $table->string('imdb_id')->nullable()->after('media_id');
        });
    }

    public function down(): void
    {
        Schema::table('watch_rooms', function (Blueprint $table) {
            $table->dropColumn('imdb_id');
        });
    }
};