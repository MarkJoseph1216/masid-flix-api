<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

class DeviceTokenCleanup extends Command
{
    /**
     * Must match the signature scheduled in app/Console/Kernel.php:
     *   $schedule->command('device-tokens:cleanup')->daily();
     */
    protected $signature   = 'device-tokens:cleanup';
    protected $description = 'Remove inactive device tokens and prevent duplicate active tokens';

    public function handle(): int
    {
        $deleted = DeviceToken::where('is_active', false)
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $duplicatesRemoved = 0;
        $userIds = DeviceToken::where('is_active', true)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $keepId = DeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->value('id');

            $duplicatesRemoved += DeviceToken::where('user_id', $userId)
                ->where('is_active', true)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        $this->info("Pruned {$deleted} stale tokens, removed {$duplicatesRemoved} duplicate active tokens.");
        return self::SUCCESS;
    }
}