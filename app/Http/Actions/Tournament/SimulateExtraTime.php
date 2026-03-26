<?php

namespace App\Http\Actions\Tournament;

use App\Modules\Match\Services\StatelessMatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stateless extra time simulation for client-side tournaments.
 */
class SimulateExtraTime
{
    public function __construct(
        private readonly StatelessMatchSimulator $simulator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'homeTeam' => 'required|array',
            'homeTeam.id' => 'required|string',
            'homeTeam.lineup' => 'required|array|min:7',
            'awayTeam' => 'required|array',
            'awayTeam.id' => 'required|string',
            'awayTeam.lineup' => 'required|array|min:7',
        ]);

        $result = $this->simulator->simulateExtraTimeFromPayload($request->all());

        return response()->json($result);
    }
}
