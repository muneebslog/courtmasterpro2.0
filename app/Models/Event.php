<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'event_name',
        'event_type',
    ];

    public const EVENT_TYPE_SINGLES = 'singles';

    public const EVENT_TYPE_DOUBLES = 'doubles';

    public const EVENT_TYPE_TEAM = 'team';

    /**
     * @return array<int, string>
     */
    public static function eventTypes(): array
    {
        return [
            self::EVENT_TYPE_SINGLES,
            self::EVENT_TYPE_DOUBLES,
            self::EVENT_TYPE_TEAM,
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class, 'event_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'event_id');
    }

    /**
     * Matches belonging to this event (via stages).
     *
     * @return HasManyThrough<MatchModel, Stage>
     */
    public function matches(): HasManyThrough
    {
        return $this->hasManyThrough(MatchModel::class, Stage::class);
    }

    public function isDeletable(): bool
    {
        return ! $this->stages()->exists() && ! $this->matches()->exists();
    }
}
