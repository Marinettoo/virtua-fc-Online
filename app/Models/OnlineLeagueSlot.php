<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnlineLeagueSlot extends Model
{
    use HasUuids;

    protected $fillable = [
        'online_league_id',
        'team_id',
        'controller_type',
        'user_id',
        'game_id',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    // ─── Relaciones ──────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(OnlineLeague::class, 'online_league_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Usuario humano que controla este slot (null si CPU) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Partida de carrera del jugador humano vinculada a este slot */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** Stats de temporada de los jugadores de este equipo en la liga */
    public function playerStats(): HasMany
    {
        return $this->hasMany(OnlinePlayerSeasonStats::class, 'team_id', 'team_id')
            ->where('online_league_id', $this->online_league_id);
    }

    // ─── Helpers ─────────────────────────────────────────

    public function isHuman(): bool
    {
        return $this->controller_type === 'human';
    }

    public function isCpu(): bool
    {
        return $this->controller_type === 'cpu';
    }

    /**
     * Un jugador toma control de este slot (mid-season join).
     * El equipo pasa de CPU a human desde este momento.
     */
    public function claimByUser(User $user, Game $game): void
    {
        $this->update([
            'controller_type' => 'human',
            'user_id'         => $user->id,
            'game_id'         => $game->id,
            'joined_at'       => now(),
        ]);
    }
}
