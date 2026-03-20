<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stage extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'best_of',
        'order_index',
        'status',
    ];

    protected $casts = [
        'best_of' => 'integer',
        'order_index' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function ties(): HasMany
    {
        return $this->hasMany(Tie::class, 'stage_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'stage_id');
    }
}
