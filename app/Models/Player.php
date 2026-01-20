<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    /** @use HasFactory<\Database\Factories\PlayerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'news_added' => 'datetime',
            'in_dreamteam' => 'boolean',
            'special' => 'boolean',
        ];
    }

    /**
     * Get the position
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the team
     */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'fpl_id');
    }

    /**
     * Get the price in millions
     */
    public function getPriceAttribute(): float
    {
        return $this->now_cost / 10;
    }

    /**
     * Get the full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->second_name}";
    }

    /**
     * Scope to filter by team
     */
    public function scopeTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to filter available players only
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'a');
    }
}
