<?php

namespace App\Http\Actions\Online;

use App\Http\Requests\Online\CreateOnlineLeagueRequest;
use App\Models\Game;
use App\Models\User;
use App\Services\OnlineLeagueService;
use Illuminate\Http\RedirectResponse;

/**
 * POST /online-leagues
 *
 * El usuario crea una nueva liga online.
 * Debe tener una partida activa del equipo que quiere usar.
 */
class CreateOnlineLeague
{
    public function __construct(private readonly OnlineLeagueService $service) {}

    public function __invoke(CreateOnlineLeagueRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $league = $this->service->createLeague(
            owner:         $user,
            competitionId: $request->competition_id,
            name:          $request->name,
            ownerTeamId:   $request->team_id,
            ownerGameId:   $request->game_id,
        );

        return redirect()
            ->route('online-league.lobby', $league->id)
            ->with('success', '¡Liga creada! Comparte el código: ' . $league->invite_code);
    }
}
