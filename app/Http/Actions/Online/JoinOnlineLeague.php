<?php

namespace App\Http\Actions\Online;

use App\Http\Requests\Online\JoinOnlineLeagueRequest;
use App\Models\User;
use App\Services\OnlineLeagueService;
use Illuminate\Http\RedirectResponse;

/**
 * POST /online-leagues/join
 *
 * Un jugador entra a una liga existente con código de invitación.
 */
class JoinOnlineLeague
{
    public function __construct(private readonly OnlineLeagueService $service) {}

    public function __invoke(JoinOnlineLeagueRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $slot = $this->service->joinLeague(
            user:   $user,
            code:   strtoupper($request->invite_code),
            teamId: $request->team_id,
            gameId: $request->game_id,
        );

        return redirect()
            ->route('online-league.lobby', $slot->online_league_id)
            ->with('success', '¡Te has unido a la liga!');
    }
}
