<?php

namespace App\Http\Actions\Tournament;

use App\Modules\Match\Services\StatelessMatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stateless penalty shootout simulation for client-side tournaments.
 */
class SimulatePenalties
{
    public function __construct(
        private readonly StatelessMatchSimulator $simulator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'homeTeam' => 'required|array',
            'homeTeam.id' => 'required|string',
            'homeTeam.lineup' => 'required|array',
            'awayTeam' => 'required|array',
            'awayTeam.id' => 'required|string',
            'awayTeam.lineup' => 'required|array',
            'homeKickers' => 'required|array|min:5',
            'awayKickers' => 'required|array|min:5',
        ]);

        $result = $this->simulator->simulatePenaltiesFromPayload($request->all());

        return response()->json($result);
    }
}
