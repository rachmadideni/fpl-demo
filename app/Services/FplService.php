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

    /**
     * Sync players from bootstrap data to database
     */
    public function syncPlayers(): array
    {
        try {
            $data = $this->getLatestData();

            if (! $data || ! isset($data['elements'])) {
                return [
                    'success' => false,
                    'message' => 'No bootstrap data available. Run fpl:fetch-bootstrap first.',
                ];
            }

            // First sync positions if they don't exist
            $this->syncPositions();
            $this->syncTeams();

            $players = $data['elements'];
            $synced = 0;
            $updated = 0;

            foreach ($players as $playerData) {
                // Get position_id from element_type
                $position = \App\Models\Position::where('element_type', $playerData['element_type'])->first();

                if (! $position) {
                    Log::warning("Position not found for element_type: {$playerData['element_type']}");
                    continue;
                }

                $player = \App\Models\Player::updateOrCreate(
                    ['fpl_id' => $playerData['id']],
                    [
                        'code' => $playerData['code'],
                        'opta_code' => $playerData['opta_code'] ?? null,
                        'web_name' => $playerData['web_name'],
                        'first_name' => $playerData['first_name'],
                        'second_name' => $playerData['second_name'],
                        'photo' => $playerData['photo'] ?? null,
                        'squad_number' => $playerData['squad_number'],
                        'team_id' => $playerData['team'],
                        'position_id' => $position->id,
                        'element_type' => $playerData['element_type'],
                        'status' => $playerData['status'],
                        'news' => $playerData['news'] ?? null,
                        'news_added' => $playerData['news_added'],
                        'chance_of_playing_this_round' => $playerData['chance_of_playing_this_round'],
                        'chance_of_playing_next_round' => $playerData['chance_of_playing_next_round'],
                        'now_cost' => $playerData['now_cost'],
                        'cost_change_start' => $playerData['cost_change_start'],
                        'cost_change_event' => $playerData['cost_change_event'],
                        'total_points' => $playerData['total_points'],
                        'event_points' => $playerData['event_points'],
                        'points_per_game' => $playerData['points_per_game'],
                        'form' => $playerData['form'],
                        'selected_by_percent' => $playerData['selected_by_percent'],
                        'minutes' => $playerData['minutes'],
                        'goals_scored' => $playerData['goals_scored'],
                        'assists' => $playerData['assists'],
                        'clean_sheets' => $playerData['clean_sheets'],
                        'goals_conceded' => $playerData['goals_conceded'],
                        'own_goals' => $playerData['own_goals'],
                        'penalties_saved' => $playerData['penalties_saved'],
                        'penalties_missed' => $playerData['penalties_missed'],
                        'yellow_cards' => $playerData['yellow_cards'],
                        'red_cards' => $playerData['red_cards'],
                        'saves' => $playerData['saves'],
                        'bonus' => $playerData['bonus'],
                        'bps' => $playerData['bps'],
                        'influence' => $playerData['influence'],
                        'creativity' => $playerData['creativity'],
                        'threat' => $playerData['threat'],
                        'ict_index' => $playerData['ict_index'],
                        'expected_goals' => $playerData['expected_goals'],
                        'expected_assists' => $playerData['expected_assists'],
                        'expected_goal_involvements' => $playerData['expected_goal_involvements'],
                        'expected_goals_conceded' => $playerData['expected_goals_conceded'],
                        'transfers_in' => $playerData['transfers_in'],
                        'transfers_out' => $playerData['transfers_out'],
                        'transfers_in_event' => $playerData['transfers_in_event'],
                        'transfers_out_event' => $playerData['transfers_out_event'],
                        'in_dreamteam' => $playerData['in_dreamteam'],
                        'dreamteam_count' => $playerData['dreamteam_count'],
                        'special' => $playerData['special'],
                    ]
                );

                if ($player->wasRecentlyCreated) {
                    $synced++;
                } else {
                    $updated++;
                }
            }

            Log::info('Players synced successfully', [
                'new' => $synced,
                'updated' => $updated,
                'total' => count($players),
            ]);

            return [
                'success' => true,
                'new' => $synced,
                'updated' => $updated,
                'total' => count($players),
            ];

        } catch (\Exception $e) {
            $error = 'Error syncing players: '.$e->getMessage();
            Log::error($error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }
    }

    /**
     * Sync positions from bootstrap data to database
     */
    public function syncPositions(): array
    {
        try {
            $data = $this->getLatestData();

            if (! $data || ! isset($data['element_types'])) {
                return [
                    'success' => false,
                    'message' => 'No bootstrap data available.',
                ];
            }

            $positions = $data['element_types'];
            $synced = 0;

            foreach ($positions as $positionData) {
                \App\Models\Position::updateOrCreate(
                    ['element_type' => $positionData['id']],
                    [
                        'singular_name' => $positionData['singular_name'],
                        'singular_name_short' => $positionData['singular_name_short'],
                        'plural_name' => $positionData['plural_name'],
                        'plural_name_short' => $positionData['plural_name_short'],
                        'squad_select' => $positionData['squad_select'],
                        'squad_min_select' => $positionData['squad_min_select'] ?? 0,
                        'squad_max_select' => $positionData['squad_max_select'] ?? 0,
                        'squad_min_play' => $positionData['squad_min_play'],
                        'squad_max_play' => $positionData['squad_max_play'],
                    ]
                );
                $synced++;
            }

            Log::info('Positions synced', ['count' => $synced]);

            return [
                'success' => true,
                'synced' => $synced,
            ];

        } catch (\Exception $e) {
            $error = 'Error syncing positions: '.$e->getMessage();
            Log::error($error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }
    }

    /**
     * Sync teams from bootstrap data to database
     */
    public function syncTeams(): array
    {
        try {
            $data = $this->getLatestData();

            if (! $data || ! isset($data['teams'])) {
                return [
                    'success' => false,
                    'message' => 'No bootstrap data available.',
                ];
            }

            $teams = $data['teams'];
            $synced = 0;
            $updated = 0;

            foreach ($teams as $teamData) {
                $team = \App\Models\Team::updateOrCreate(
                    ['fpl_id' => $teamData['id']],
                    [
                        'code' => $teamData['code'],
                        'name' => $teamData['name'],
                        'short_name' => $teamData['short_name'],
                        'logo_url' => "https://resources.premierleague.com/premierleague/badges/100/t{$teamData['code']}.png",
                        'strength' => $teamData['strength'],
                        'position' => $teamData['position'],
                        'played' => $teamData['played'] ?? 0,
                        'win' => $teamData['win'] ?? 0,
                        'draw' => $teamData['draw'] ?? 0,
                        'loss' => $teamData['loss'] ?? 0,
                        'points' => $teamData['points'] ?? 0,
                        'form' => $teamData['form'],
                        'unavailable' => $teamData['unavailable'] ?? false,
                        'strength_overall_home' => $teamData['strength_overall_home'] ?? null,
                        'strength_overall_away' => $teamData['strength_overall_away'] ?? null,
                        'strength_attack_home' => $teamData['strength_attack_home'] ?? null,
                        'strength_attack_away' => $teamData['strength_attack_away'] ?? null,
                        'strength_defence_home' => $teamData['strength_defence_home'] ?? null,
                        'strength_defence_away' => $teamData['strength_defence_away'] ?? null,
                        'pulse_id' => $teamData['pulse_id'] ?? null,
                    ]
                );

                if ($team->wasRecentlyCreated) {
                    $synced++;
                } else {
                    $updated++;
                }
            }

            Log::info('Teams synced successfully', [
                'new' => $synced,
                'updated' => $updated,
                'total' => count($teams),
            ]);

            return [
                'success' => true,
                'new' => $synced,
                'updated' => $updated,
                'total' => count($teams),
            ];

        } catch (\Exception $e) {
            $error = 'Error syncing teams: '.$e->getMessage();
            Log::error($error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }
    }
}
