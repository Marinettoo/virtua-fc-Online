<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeagueRoom extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'owner_id',
        'season_id',
        'auto_advance_hours',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(LeagueRoomMember::class);
    }

    public function matchdays(): HasMany
    {
        return $this->hasMany(LeagueRoomMatchday::class);
    }

    public function allMembersReady(): bool
    {
        return $this->members()->where('is_ready', false)->doesntExist();
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(\Illuminate\Support\Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
