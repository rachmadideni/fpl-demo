<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FplService
{
    protected string $apiUrl = 'https://fantasy.premierleague.com/api/bootstrap-static';

    protected string $storagePath = 'fpl-data/bootstrap-static.json';

    protected string $archivePath = 'fpl-data/archive';

    protected int $maxArchiveFiles = 4;

    /**
     * Fetch bootstrap data from FPL API
     */
    public function fetchBootstrapData(): array
    {
        try {
            Log::info('Starting FPL bootstrap data fetch');

            $response = Http::timeout(30)
                ->retry(3, 100) // Retry 3 times with 100ms delay
                ->get($this->apiUrl);

            if ($response->failed()) {
                throw new \Exception('API returned error: '.$response->status());
            }

            $data = $response->json();

            // Validate data structure
            if (! isset($data['elements']) || ! isset($data['teams']) || ! isset($data['events'])) {
                throw new \Exception('Invalid data structure received from API');
            }

            // Save the data
            $this->saveData($data);

            $stats = [
                'size' => strlen($response->body()),
                'players_count' => count($data['elements']),
                'teams_count' => count($data['teams']),
                'events_count' => count($data['events']),
            ];

            Log::info('FPL bootstrap data fetched successfully', $stats);

            // Send Slack notification
            $this->sendSlackNotification('success', $stats);

            return [
                'success' => true,
                'message' => 'Data fetched and saved successfully',
                'size' => strlen($response->body()),
                'players_count' => count($data['elements']),
                'teams_count' => count($data['teams']),
                'events_count' => count($data['events']),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $error = 'Network error while fetching FPL data: '.$e->getMessage();
            Log::error($error);
            $this->sendSlackNotification('error', ['message' => $error]);

            return [
                'success' => false,
                'message' => $error,
            ];
        } catch (\Exception $e) {
            $error = 'Error fetching FPL data: '.$e->getMessage();
            Log::error($error);
            $this->sendSlackNotification('error', ['message' => $error]);

            return [
                'success' => false,
                'message' => $error,
            ];
        }
    }

    /**
     * Save data to storage (latest + timestamped archive)
     */
    protected function saveData(array $data): void
    {
        // Ensure directories exist
        if (! Storage::exists('fpl-data')) {
            Storage::makeDirectory('fpl-data');
        }

        if (! Storage::exists($this->archivePath)) {
            Storage::makeDirectory($this->archivePath);
        }

        // Save latest version
        Storage::put($this->storagePath, json_encode($data, JSON_PRETTY_PRINT));

        // Save timestamped archive version
        $timestamp = now()->format('Y-m-d_His');
        Storage::put("{$this->archivePath}/bootstrap-static-{$timestamp}.json", json_encode($data));

        Log::info('FPL data saved', [
            'latest' => $this->storagePath,
            'archive' => "{$this->archivePath}/bootstrap-static-{$timestamp}.json",
        ]);
    }

    /**
     * Clean old archive files (keep only the latest N files)
     */
    public function cleanOldArchives(): int
    {
        $files = Storage::files($this->archivePath);

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return Storage::lastModified($b) <=> Storage::lastModified($a);
        });

        // Keep only the latest N files
        $filesToDelete = array_slice($files, $this->maxArchiveFiles);

        $deletedCount = 0;
        foreach ($filesToDelete as $file) {
            Storage::delete($file);
            $deletedCount++;
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned {$deletedCount} old FPL archive files");
            
            // Notify Slack about cleanup
            if (config('logging.channels.fpl_slack.url')) {
                try {
                    $message = "🧹 *FPL Archive Cleanup*\n\n";
                    $message .= "• Deleted `{$deletedCount}` old archive file(s)\n";
                    $message .= "• Remaining files: `".(count($files) - $deletedCount)."`\n";
                    $message .= "• Time: `".now()->format('Y-m-d H:i:s')."`";
                    
                    Log::channel('fpl_slack')->info($message);
                } catch (\Exception $e) {
                    // Silently fail
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Get latest stored data
     */
    public function getLatestData(): ?array
    {
        if (! Storage::exists($this->storagePath)) {
            return null;
        }

        $json = Storage::get($this->storagePath);

        return json_decode($json, true);
    }

    /**
     * Get when data was last updated
     */
    public function getDataAge(): ?string
    {
        if (! Storage::exists($this->storagePath)) {
            return null;
        }

        $lastModified = Storage::lastModified($this->storagePath);

        return now()->diffForHumans(now()->createFromTimestamp($lastModified));
    }

    /**
     * Get archive file count
     */
    public function getArchiveCount(): int
    {
        if (! Storage::exists($this->archivePath)) {
            return 0;
        }

        return count(Storage::files($this->archivePath));
    }

    /**
     * Check if data exists
     */
    public function hasData(): bool
    {
        return Storage::exists($this->storagePath);
    }

    /**
     * Send Slack notification
     */
    protected function sendSlackNotification(string $status, array $data): void
    {
        if (! config('logging.channels.fpl_slack.url')) {
            return; // Slack webhook not configured
        }

        try {
            if ($status === 'success') {
                $message = "✅ *FPL Bootstrap Data Fetched Successfully*\n\n";
                $message .= "📊 *Statistics:*\n";
                $message .= "• Players: `".number_format($data['players_count'])."`\n";
                $message .= "• Teams: `{$data['teams_count']}`\n";
                $message .= "• Events: `{$data['events_count']}`\n";
                $message .= "• Size: `".number_format($data['size'])."` bytes\n";
                $message .= "• Time: `".now()->format('Y-m-d H:i:s')."`";

                Log::channel('fpl_slack')->info($message);
            } else {
                $message = "❌ *FPL Bootstrap Data Fetch Failed*\n\n";
                $message .= "⚠️ *Error:* {$data['message']}\n";
                $message .= "• Time: `".now()->format('Y-m-d H:i:s')."`";

                Log::channel('fpl_slack')->error($message);
            }
        } catch (\Exception $e) {
            // Silently fail if Slack notification fails
            Log::warning('Failed to send Slack notification: '.$e->getMessage());
        }
    }
}
