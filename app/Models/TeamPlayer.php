<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamPlayer extends Model
{
    protected $fillable = [
        'team_id',
        'player_name',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * @return HasMany<MatchPlayer, $this>
     */
    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'team_player_id');
    }
}
