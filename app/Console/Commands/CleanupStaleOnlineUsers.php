<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupStaleOnlineUsers extends Command
{
    protected $signature = 'users:cleanup-stale-online';
    protected $description = 'Set users offline if they haven\'t sent a heartbeat in 5 minutes';

    public function handle()
    {
        $cutoff = now()->subMinutes(5);
        
        $updated = User::where('is_online', true)
            ->where('last_seen_at', '<', $cutoff)
            ->update([
                'is_online' => false,
                'last_seen_at' => now(),
            ]);
        
        return Command::SUCCESS;
    }
}