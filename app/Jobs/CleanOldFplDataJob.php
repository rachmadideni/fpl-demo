<?php

namespace App\Jobs;

use App\Services\FplService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanOldFplDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(FplService $fplService): void
    {
        Log::info('CleanOldFplDataJob started');

        $deletedCount = $fplService->cleanOldArchives();

        if ($deletedCount > 0) {
            Log::info("CleanOldFplDataJob completed: deleted {$deletedCount} old archive files");
        } else {
            Log::info('CleanOldFplDataJob completed: no files to delete');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('CleanOldFplDataJob failed', [
            'error' => $exception?->getMessage(),
        ]);
    }
}
