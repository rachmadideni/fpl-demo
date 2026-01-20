<?php

namespace App\Console\Commands;

use App\Jobs\CleanOldFplDataJob;
use App\Jobs\FetchFplBootstrapDataJob;
use App\Services\FplService;
use Illuminate\Console\Command;

class FetchFplBootstrapData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'fpl:fetch-bootstrap 
                            {--sync : Run synchronously instead of queuing}
                            {--force : Force fetch even if recently updated}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch the latest FPL bootstrap data from the API';

    /**
     * Execute the console command.
     */
    public function handle(FplService $fplService): int
    {
        $this->info('🏆 FPL Bootstrap Data Fetch');
        $this->newLine();

        // Show current data status
        if ($fplService->hasData()) {
            $age = $fplService->getDataAge();
            $archiveCount = $fplService->getArchiveCount();

            $this->line("📊 Current data was updated <fg=yellow>{$age}</>");
            $this->line("📁 Archive files: <fg=cyan>{$archiveCount}</> (max: 4)");
            $this->newLine();

            if (! $this->option('force')) {
                if (! $this->confirm('Do you want to fetch new data?', true)) {
                    $this->info('Cancelled.');

                    return Command::SUCCESS;
                }
            }
        }

        // Decide whether to run sync or queue
        if ($this->option('sync')) {
            return $this->runSynchronously($fplService);
        } else {
            return $this->runQueued();
        }
    }

    /**
     * Run the fetch synchronously
     */
    protected function runSynchronously(FplService $fplService): int
    {
        $this->info('⏳ Fetching data synchronously...');

        $result = $fplService->fetchBootstrapData();

        if ($result['success']) {
            $this->newLine();
            $this->info('✅ Data fetched successfully!');
            $this->line("Size: <fg=cyan>".number_format($result['size'] ?? 0).'</> bytes');
            $this->line("Players: <fg=cyan>{$result['players_count']}</>");
            $this->line("Teams: <fg=cyan>{$result['teams_count']}</>");
            $this->line("Events: <fg=cyan>{$result['events_count']}</>");

            // Clean old archives
            $this->newLine();
            $this->info('🧹 Cleaning old archives...');
            $deletedCount = $fplService->cleanOldArchives();

            if ($deletedCount > 0) {
                $this->line("Deleted <fg=red>{$deletedCount}</> old archive file(s)");
            } else {
                $this->line('No old files to delete');
            }

            return Command::SUCCESS;
        } else {
            $this->newLine();
            $this->error('❌ Failed to fetch data');
            $this->error($result['message'] ?? 'Unknown error');

            return Command::FAILURE;
        }
    }

    /**
     * Run the fetch as a queued job
     */
    protected function runQueued(): int
    {
        $this->info('📤 Queuing fetch job...');

        FetchFplBootstrapDataJob::dispatch();
        CleanOldFplDataJob::dispatch()->delay(now()->addMinutes(1));

        $this->newLine();
        $this->info('✅ Jobs queued successfully!');
        $this->line('The data will be fetched in the background.');
        $this->line('Check logs with: <fg=cyan>php artisan pail</>');

        return Command::SUCCESS;
    }
}
