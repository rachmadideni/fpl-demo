<?php

namespace App\Jobs;

use App\Services\FplService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchFplBootstrapDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

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
        Log::info('FetchFplBootstrapDataJob started');

        $result = $fplService->fetchBootstrapData();

        if ($result['success']) {
            Log::info('FetchFplBootstrapDataJob completed successfully', [
                'size' => $result['size'] ?? null,
                'players_count' => $result['players_count'] ?? null,
            ]);
        } else {
            Log::error('FetchFplBootstrapDataJob failed', [
                'message' => $result['message'] ?? 'Unknown error',
            ]);

            // Optionally throw exception to trigger retry
            throw new \Exception($result['message'] ?? 'Failed to fetch FPL data');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('FetchFplBootstrapDataJob failed permanently after all retries', [
            'error' => $exception?->getMessage(),
        ]);

        // Here you could:
        // - Send notification to Slack/Discord
        // - Send email to admin
        // - Log to database
    }
}
