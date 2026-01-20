<?php

namespace App\Console\Commands;

use App\Services\FplService;
use Illuminate\Console\Command;

class SyncFplPositions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fpl:sync-positions';

    /**
     * The console command description.
     */
    protected $description = 'Sync FPL positions from bootstrap data to database';

    /**
     * Execute the console command.
     */
    public function handle(FplService $fplService): int
    {
        $this->info('📍 Syncing FPL Positions...');
        $this->newLine();

        // Check if data exists
        if (! $fplService->hasData()) {
            $this->error('❌ No bootstrap data found!');
            $this->line('Run: <fg=cyan>php artisan fpl:fetch-bootstrap --sync</>');

            return Command::FAILURE;
        }

        // Sync positions
        $result = $fplService->syncPositions();

        if ($result['success']) {
            $this->info('✅ Positions synced successfully!');
            $this->line("Synced: <fg=green>{$result['synced']}</> positions");

            // Show positions
            $this->newLine();
            $this->table(
                ['ID', 'Name', 'Short', 'Squad Select', 'Min Play', 'Max Play'],
                \App\Models\Position::all()->map(fn ($p) => [
                    $p->element_type,
                    $p->singular_name,
                    $p->singular_name_short,
                    $p->squad_select,
                    $p->squad_min_play,
                    $p->squad_max_play,
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
