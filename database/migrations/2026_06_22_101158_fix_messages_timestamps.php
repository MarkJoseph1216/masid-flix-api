<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::table('messages')
            ->whereNull('created_at')
            ->update([
                'created_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()')
            ]);

        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'created_at')) {
                $table->timestamp('created_at')->nullable(false)->change();
            }
            if (Schema::hasColumn('messages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable(false)->change();
            }
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'created_at')) {
                $table->timestamp('created_at')->nullable()->change();
            }
            if (Schema::hasColumn('messages', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->change();
            }
        });
    }
};