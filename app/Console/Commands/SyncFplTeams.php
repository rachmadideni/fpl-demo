<?php

namespace App\Console\Commands;

use App\Services\FplService;
use Illuminate\Console\Command;

class SyncFplTeams extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fpl:sync-teams';

    /**
     * The console command description.
     */
    protected $description = 'Sync FPL teams from bootstrap data to database';

    /**
     * Execute the console command.
     */
    public function handle(FplService $fplService): int
    {
        $this->info('⚽ Syncing FPL Teams...');
        $this->newLine();

        // Check if data exists
        if (! $fplService->hasData()) {
            $this->error('❌ No bootstrap data found!');
            $this->line('Run: <fg=cyan>php artisan fpl:fetch-bootstrap --sync</>');

            return Command::FAILURE;
        }

        // Sync teams
        $result = $fplService->syncTeams();

        if ($result['success']) {
            $this->info('✅ Teams synced successfully!');
            $this->newLine();
            $this->line("New teams: <fg=green>{$result['new']}</>");
            $this->line("Updated teams: <fg=cyan>{$result['updated']}</>");
            $this->line("Total teams: <fg=yellow>{$result['total']}</>");

            // Show teams table
            $this->newLine();
            $teams = \App\Models\Team::orderBy('name')->get();
            $this->table(
                ['ID', 'Name', 'Short', 'Strength', 'Played', 'Points'],
                $teams->map(fn ($t) => [
                    $t->fpl_id,
                    $t->name,
                    $t->short_name,
                    $t->strength,
                    $t->played,
                    $t->points,
                ])
            );

            return Command::SUCCESS;
        } else {
            $this->error('❌ Sync failed!');
            $this->error($result['message']);

            return Command::FAILURE;
        }
    }
}
