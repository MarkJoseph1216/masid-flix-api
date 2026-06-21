<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watch_room_participants', function (Blueprint $table) {
            $table->dropColumn('left_at');
        });
    }

    public function down(): void
    {
        Schema::table('watch_room_participants', function (Blueprint $table) {
            $table->timestamp('left_at')->nullable();
        });
    }
};