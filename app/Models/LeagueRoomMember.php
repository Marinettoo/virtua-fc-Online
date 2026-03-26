<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueRoomMember extends Model
{
    protected $fillable = [
        'league_room_id',
        'user_id',
        'team_id',
        'is_ready',
    ];

    protected $casts = [
        'is_ready' => 'boolean',
    ];

    public function leagueRoom(): BelongsTo
    {
        return $this->belongsTo(LeagueRoom::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
