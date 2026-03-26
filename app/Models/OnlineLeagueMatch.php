<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineLeagueMatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'online_league_id',
        'round_number',
        'home_team_id',
        'away_team_id',
        'match_type',
        'home_score',
        'away_score',
        'status',
        'home_game_match_id',
        'away_game_match_id',
        'played_at',
    ];

    protected $casts = [
        'played_at'    => 'datetime',
        'round_number' => 'integer',
        'home_score'   => 'integer',
        'away_score'   => 'integer',
    ];

    // ─── Relaciones ──────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(OnlineLeague::class, 'online_league_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    // ─── Helpers ─────────────────────────────────────────

    public function isPlayed(): bool
    {
        return $this->status === 'played';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCpuVsCpu(): bool
    {
        return $this->match_type === 'cpu_vs_cpu';
    }

    public function isHumanVsCpu(): bool
    {
        return $this->match_type === 'human_vs_cpu';
    }

    public function isHumanVsHuman(): bool
    {
        return $this->match_type === 'human_vs_human';
    }

    /**
     * Registra el resultado y marca el partido como jugado.
     */
    public function registerResult(int $homeScore, int $awayScore): void
    {
        $this->update([
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status'     => 'played',
            'played_at'  => now(),
        ]);
    }
}
