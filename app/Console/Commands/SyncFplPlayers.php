<?php

namespace App\Console\Commands;

use App\Services\FplService;
use Illuminate\Console\Command;

class SyncFplPlayers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fpl:sync-players 
                            {--fresh : Truncate players table before syncing}';

    /**
     * The console command description.
     */
    protected $description = 'Sync FPL players from bootstrap data to database';

    /**
     * Execute the console command.
     */
    public function handle(FplService $fplService): int
    {
        $this->info('⚽ Syncing FPL Players...');
        $this->newLine();

        // Check if data exists
        if (! $fplService->hasData()) {
            $this->error('❌ No bootstrap data found!');
            $this->line('Run: <fg=cyan>php artisan fpl:fetch-bootstrap --sync</>');

            return Command::FAILURE;
        }

        // Show data age
        $age = $fplService->getDataAge();
        $this->line("📊 Using data updated <fg=yellow>{$age}</>");
        $this->newLine();

        // Truncate if fresh option
        if ($this->option('fresh')) {
            if ($this->confirm('This will delete all existing players. Continue?', false)) {
                $this->info('🗑️  Truncating players table...');
                \App\Models\Player::truncate();
            } else {
                $this->info('Cancelled.');

                return Command::SUCCESS;
            }
        }

        // Sync players
        $this->info('🔄 Syncing players...');
        $progressBar = $this->output->createProgressBar(100);
        $progressBar->start();

        $result = $fplService->syncPlayers();

        $progressBar->finish();
        $this->newLine(2);

        if ($result['success']) {
            $this->info('✅ Players synced successfully!');
            $this->newLine();
            $this->line("New players: <fg=green>{$result['new']}</>");
            $this->line("Updated players: <fg=cyan>{$result['updated']}</>");
            $this->line("Total players: <fg=yellow>{$result['total']}</>");

            return Command::SUCCESS;
        } else {
            $this->error('❌ Sync failed!');
            $this->error($result['message']);

            return Command::FAILURE;
        }
    }
}
