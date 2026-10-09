<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

class PruneDeviceTokens extends Command
{
    protected $signature   = 'device-tokens:prune';
    protected $description = 'Delete inactive device tokens older than 60 days';

    public function handle(): int
    {
        $deleted = DeviceToken::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(60))
            ->delete();

        $this->info("Pruned {$deleted} stale device tokens.");
        return self::SUCCESS;
    }
}