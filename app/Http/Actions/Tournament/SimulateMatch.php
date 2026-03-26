<?php

namespace App\Http\Actions\Tournament;

use App\Modules\Match\Services\StatelessMatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stateless match simulation endpoint for client-side tournaments.
 *
 * Receives all match inputs (teams, lineups, tactics) as JSON,
 * runs the simulation engine, and returns results.
 * No database reads or writes.
 */
class SimulateMatch
{
    public function __construct(
        private readonly StatelessMatchSimulator $simulator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'homeTeam' => 'required|array',
            'homeTeam.id' => 'required|string',
            'homeTeam.name' => 'required|string',
            'homeTeam.lineup' => 'required|array|min:7',
            'awayTeam' => 'required|array',
            'awayTeam.id' => 'required|string',
            'awayTeam.name' => 'required|string',
            'awayTeam.lineup' => 'required|array|min:7',
            'homeFormation' => 'nullable|string',
            'awayFormation' => 'nullable|string',
            'homeMentality' => 'nullable|string',
            'awayMentality' => 'nullable|string',
        ]);

        $result = $this->simulator->simulateFromPayload($request->all());

        return response()->json($result);
    }
}
