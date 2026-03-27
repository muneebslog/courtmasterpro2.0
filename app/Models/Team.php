<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'flag',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TeamPlayer::class, 'team_id');
    }

    public function tiesAsTeamA(): HasMany
    {
        return $this->hasMany(Tie::class, 'team_a_id');
    }

    public function tiesAsTeamB(): HasMany
    {
        return $this->hasMany(Tie::class, 'team_b_id');
    }

    public function winningTies(): HasMany
    {
        return $this->hasMany(Tie::class, 'winner_team_id');
    }
}
