<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->onDelete('cascade');
            $table->string('media_id');
            $table->string('media_type');
            $table->string('room_code')->unique();
            $table->string('title');
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('current_time')->default(0);
            $table->boolean('is_playing')->default(false);
            $table->integer('max_participants')->default(10);
            $table->timestamps();
        });

        Schema::create('watch_room_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('watch_rooms')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_room_participants');
        Schema::dropIfExists('watch_rooms');
    }
};