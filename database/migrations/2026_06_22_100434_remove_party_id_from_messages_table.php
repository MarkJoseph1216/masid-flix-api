// database/migrations/xxxx_remove_party_id_from_messages_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        DB::statement('DROP POLICY IF EXISTS "Allow party members to chat" ON messages');
        DB::statement('DROP POLICY IF EXISTS "Allow party members to insert" ON messages');
        DB::statement('DROP POLICY IF EXISTS "Allow party members to select" ON messages');
        DB::statement('DROP POLICY IF EXISTS "Allow party members to update" ON messages');
        DB::statement('DROP POLICY IF EXISTS "party_members_can_chat" ON messages');
        
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'party_id')) {
                $table->dropColumn('party_id');
            }
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('party_id')->nullable()->after('receiver_id');
        });
    }
};