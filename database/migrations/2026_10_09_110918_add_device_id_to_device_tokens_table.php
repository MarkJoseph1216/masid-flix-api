<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            // Index for fast lookup by stable device id
            $table->string('device_id')->nullable()->after('device_type');
            $table->index(['user_id', 'device_id']);
        });

        // One row per token globally (guards against duplicates)
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->unique('fcm_token');
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropUnique(['fcm_token']);
            $table->dropIndex(['user_id', 'device_id']);
            $table->dropColumn('device_id');
        });
    }
};