<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlinePlayerSeasonStats extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'online_league_id',
        'game_player_id',
        'team_id',
        'appearances',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'minutes_played',
        'clean_sheets',
    ];

    protected $casts = [
        'appearances'    => 'integer',
        'goals'          => 'integer',
        'assists'        => 'integer',
        'yellow_cards'   => 'integer',
        'red_cards'      => 'integer',
        'minutes_played' => 'integer',
        'clean_sheets'   => 'integer',
    ];

    // ─── Relaciones ──────────────────────────────────────

    public function league(): BelongsTo
    {
        return $this->belongsTo(OnlineLeague::class, 'online_league_id');
    }

    /** Jugador en la partida del manager */
    public function gamePlayer(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ─── Helpers ─────────────────────────────────────────

    /**
     * Suma los datos de un partido jugado a las stats del jugador.
     *
     * @param array $matchData [
     *   'goals'        => int,
     *   'assists'      => int,
     *   'yellow_cards' => int,
     *   'red_cards'    => int,
     *   'minutes'      => int,
     *   'clean_sheet'  => bool,
     * ]
     */
    public function addMatchStats(array $matchData): void
    {
        $this->appearances    += 1;
        $this->goals          += $matchData['goals']        ?? 0;
        $this->assists        += $matchData['assists']       ?? 0;
        $this->yellow_cards   += $matchData['yellow_cards']  ?? 0;
        $this->red_cards      += $matchData['red_cards']     ?? 0;
        $this->minutes_played += $matchData['minutes']       ?? 0;
        $this->clean_sheets   += ($matchData['clean_sheet'] ?? false) ? 1 : 0;

        $this->save();
    }
}
