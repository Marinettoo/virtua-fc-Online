<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineLeague extends Model
{
    use HasUuids;

    protected $fillable = [
        'owner_user_id',
        'competition_id',
        'name',
        'invite_code',
        'current_matchday',
        'season',
        'status',
        'started_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'current_matchday' => 'integer',
    ];

    // ─── Relaciones ──────────────────────────────────────

    /** Usuario que creó la sala */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Competición base (LaLiga, etc.) */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** Slots de equipos (humanos + CPU) */
    public function slots(): HasMany
    {
        return $this->hasMany(OnlineLeagueSlot::class);
    }

    /** Solo los slots controlados por humanos */
    public function humanSlots(): HasMany
    {
        return $this->slots()->where('controller_type', 'human');
    }

    /** Solo los slots controlados por CPU */
    public function cpuSlots(): HasMany
    {
        return $this->slots()->where('controller_type', 'cpu');
    }

    /** Partidos de la liga */
    public function matches(): HasMany
    {
        return $this->hasMany(OnlineLeagueMatch::class);
    }

    /** Clasificación */
    public function standings(): HasMany
    {
        return $this->hasMany(OnlineLeagueStanding::class)
            ->orderBy('position');
    }

    /** Stats de jugadores */
    public function playerStats(): HasMany
    {
        return $this->hasMany(OnlinePlayerSeasonStats::class);
    }

    // ─── Helpers ─────────────────────────────────────────

    /** ¿Está la liga en estado lobby? */
    public function isInLobby(): bool
    {
        return $this->status === 'lobby';
    }

    /** ¿Está activa la temporada? */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** ¿Hay un slot libre (CPU) para que un jugador entre? */
    public function hasFreeCpuSlots(): bool
    {
        return $this->slots()->where('controller_type', 'cpu')->exists();
    }

    /** Genera un código de invitación único de 8 caracteres */
    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }
}
