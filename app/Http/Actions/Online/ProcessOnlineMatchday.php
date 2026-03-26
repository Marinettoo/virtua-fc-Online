<?php

namespace App\Http\Actions\Online;

use App\Models\OnlineLeague;
use App\Models\User;
use App\Services\OnlineLeagueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST /online-leagues/{leagueId}/process-matchday
 *
 * Simula los partidos CPU de la jornada actual.
 * Disponible para cualquier miembro de la liga
 * (no solo el owner) para que la liga no se bloquee
 * si el owner está inactivo.
 */
class ProcessOnlineMatchday
{
    public function __construct(private readonly OnlineLeagueService $service) {}

    public function __invoke(Request $request, string $leagueId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $league = OnlineLeague::findOrFail($leagueId);

        // Verificar que el usuario es miembro de la liga
        abort_unless(
            $league->slots()->where('user_id', $user->id)->exists(),
            403,
            'No eres miembro de esta liga.'
        );

        $this->service->processMatchday($league);

        return redirect()
            ->route('online-league.lobby', $league->id)
            ->with('success', 'Jornada ' . $league->current_matchday . ' procesada.');
    }
}
