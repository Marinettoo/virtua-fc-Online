<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineLeagueStanding extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'online_league_id',
        'team_id',
        'position',
        'prev_position',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'points',
    ];

    protected $casts = [
        'position'      => 'integer',
        'prev_position' => 'integer',
        'played'        => 'integer',
        'won'           => 'integer',
        'drawn'         => 'integer',
        'lost'          => 'integer',
        'goals_for'     => 'integer',
        'goals_against' => 'integer',
        'points'        => 'integer',
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

    // ─── Helpers ─────────────────────────────────────────

    /** Diferencia de goles */
    public function getGoalDifferenceAttribute(): int
    {
        return $this->goals_for - $this->goals_against;
    }

    /**
     * Aplica el resultado de un partido a esta fila de clasificación.
     * $goalsFor    = goles que metió este equipo
     * $goalsAgainst = goles que le metieron
     */
    public function applyResult(int $goalsFor, int $goalsAgainst): void
    {
        $this->played       += 1;
        $this->goals_for    += $goalsFor;
        $this->goals_against += $goalsAgainst;

        if ($goalsFor > $goalsAgainst) {
            $this->won    += 1;
            $this->points += 3;
        } elseif ($goalsFor === $goalsAgainst) {
            $this->drawn  += 1;
            $this->points += 1;
        } else {
            $this->lost += 1;
        }

        $this->save();
    }
}
