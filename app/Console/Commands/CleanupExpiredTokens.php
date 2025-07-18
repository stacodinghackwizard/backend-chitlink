<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;
use Carbon\Carbon;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired personal access tokens';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting token cleanup...');

        // Delete tokens that have expired
        $expiredTokens = PersonalAccessToken::where('expires_at', '<', Carbon::now())->delete();

        // Delete tokens that haven't been used in the last 2 hours (inactivity)
        $inactiveTokens = PersonalAccessToken::where('last_used_at', '<', Carbon::now()->subHours(2))->delete();

        $this->info("Cleaned up {$expiredTokens} expired tokens and {$inactiveTokens} inactive tokens.");

        return Command::SUCCESS;
    }
} 