<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'match_id',
        'game_number',
        'score_a',
        'score_b',
        'winner_side',
        'entry_mode',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'match_id' => 'integer',
        'game_number' => 'integer',
        'score_a' => 'integer',
        'score_b' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'game_id');
    }
}
