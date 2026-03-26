<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueRoomMatchday extends Model
{
    protected $fillable = [
        'league_room_id',
        'matchday_number',
        'status',
        'auto_advance_at',
    ];

    protected $casts = [
        'auto_advance_at' => 'datetime',
    ];

    public function leagueRoom(): BelongsTo
    {
        return $this->belongsTo(LeagueRoom::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isSimulated(): bool
    {
        return $this->status === 'simulated';
    }
}
