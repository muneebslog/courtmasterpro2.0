<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    protected $table = 'match_players';

    protected $fillable = [
        'match_id',
        'side',
        'player_name',
        'flag',
        'position',
        'team_player_id',
    ];

    protected $casts = [
        'position' => 'integer',
        'match_id' => 'integer',
        'team_player_id' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class, 'team_player_id');
    }
}
