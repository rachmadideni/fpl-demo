<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unavailable' => 'boolean',
        ];
    }

    /**
     * Get all players in this team
     */
    public function players()
    {
        return $this->hasMany(Player::class, 'team_id', 'fpl_id');
    }

    /**
     * Get full strength rating
     */
    public function getOverallStrengthAttribute(): float
    {
        return ($this->strength_overall_home + $this->strength_overall_away) / 2;
    }

    /**
     * Get attack strength rating
     */
    public function getAttackStrengthAttribute(): float
    {
        return ($this->strength_attack_home + $this->strength_attack_away) / 2;
    }

    /**
     * Get defence strength rating
     */
    public function getDefenceStrengthAttribute(): float
    {
        return ($this->strength_defence_home + $this->strength_defence_away) / 2;
    }
}
