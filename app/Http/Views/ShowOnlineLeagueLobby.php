<?php

namespace App\Http\Views;

use App\Models\OnlineLeague;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * GET /online-leagues/{leagueId}
 *
 * Hub principal de la liga: clasificación, jornada actual,
 * lista de managers y partidos pendientes.
 */
class ShowOnlineLeagueLobby
{
    public function __invoke(Request $request, string $leagueId): View
    {
        $league = OnlineLeague::with([
            'competition',
            'slots.team',
            'slots.user',
            'standings' => fn ($q) => $q->with('team')->orderBy('position'),
        ])->findOrFail($leagueId);

        $currentMatchday = $league->matches()
            ->where('round_number', $league->current_matchday)
            ->with(['homeTeam', 'awayTeam'])
            ->get();

        return view('online-league.lobby', [
            'league'          => $league,
            'slots'           => $league->slots,
            'standings'       => $league->standings,
            'currentMatchday' => $currentMatchday,
            'isOwner'         => $request->user()?->id === $league->owner_user_id,
            'mySlot'          => $league->slots->firstWhere('user_id', $request->user()?->id),
        ]);
    }
}
