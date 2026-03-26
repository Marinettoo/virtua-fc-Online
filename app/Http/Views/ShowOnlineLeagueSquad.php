<?php

namespace App\Http\Views;

use App\Models\OnlineLeague;
use App\Models\OnlinePlayerSeasonStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /online-leagues/{leagueId}/teams/{teamId}
 *
 * Plantilla + stats de temporada de un equipo en la liga online.
 * No muestra atributos internos de los jugadores.
 */
class ShowOnlineLeagueSquad
{
    public function __invoke(Request $request, string $leagueId, string $teamId): View
    {
        $league = OnlineLeague::findOrFail($leagueId);

        $slot = $league->slots()->where('team_id', $teamId)
            ->with(['team', 'user'])
            ->firstOrFail();

        $standing = $league->standings()->where('team_id', $teamId)->first();

        $stats = OnlinePlayerSeasonStats::where('online_league_id', $leagueId)
            ->where('team_id', $teamId)
            ->with('gamePlayer.playerTemplate') // solo nombre + posición
            ->orderByDesc('goals')
            ->get();

        return view('online-league.squad', [
            'league'   => $league,
            'slot'     => $slot,
            'standing' => $standing,
            'stats'    => $stats,
        ]);
    }
}
