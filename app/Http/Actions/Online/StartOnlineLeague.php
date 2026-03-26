<?php

namespace App\Http\Actions\Online;

use App\Models\OnlineLeague;
use App\Models\User;
use App\Services\OnlineLeagueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST /online-leagues/{leagueId}/start
 *
 * El owner arranca la liga: genera el calendario completo
 * y cambia el estado a 'active'.
 */
class StartOnlineLeague
{
    public function __construct(private readonly OnlineLeagueService $service) {}

    public function __invoke(Request $request, string $leagueId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $league = OnlineLeague::findOrFail($leagueId);

        abort_unless(
            $league->owner_user_id === $user->id,
            403,
            'Solo el creador puede arrancar la liga.'
        );

        $this->service->startLeague($league);

        return redirect()
            ->route('online-league.lobby', $league->id)
            ->with('success', '¡Temporada iniciada! La jornada 1 está lista.');
    }
}
