<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    protected $fillable = [
        'match_id',
        'game_id',
        'event_type',
        'side',
        'player_name',
        'score_a_at_time',
        'score_b_at_time',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'match_id' => 'integer',
        'game_id' => 'integer',
        'score_a_at_time' => 'integer',
        'score_b_at_time' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }
}
