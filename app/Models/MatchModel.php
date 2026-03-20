<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchModel extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'stage_id',
        'tie_id',
        'side_a_label',
        'side_b_label',
        'match_order',
        'best_of',
        'status',
        'winner_side',
        'umpire_name',
        'service_judge_name',
        'court',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'best_of' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'tie_id' => 'integer',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function tie(): BelongsTo
    {
        return $this->belongsTo(Tie::class, 'tie_id');
    }

    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'match_id');
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }
}
