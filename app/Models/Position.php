<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get all players in this position
     */
    public function players()
    {
        return $this->hasMany(Player::class, 'position_id');
    }
}
