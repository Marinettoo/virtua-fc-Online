<?php

namespace App\Http\Actions\Tournament;

use App\Modules\Match\Services\StatelessMatchSimulator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Batch-simulate multiple matches for CPU vs CPU games.
 *
 * Used to simulate all group stage matches the user isn't playing.
 * Stateless — no database reads or writes.
 */
class SimulateBatch
{
    public function __construct(
        private readonly StatelessMatchSimulator $simulator,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'matches' => 'required|array|max:50',
            'matches.*.homeTeam' => 'required|array',
            'matches.*.homeTeam.id' => 'required|string',
            'matches.*.homeTeam.lineup' => 'required|array|min:7',
            'matches.*.awayTeam' => 'required|array',
            'matches.*.awayTeam.id' => 'required|string',
            'matches.*.awayTeam.lineup' => 'required|array|min:7',
        ]);

        $results = [];

        foreach ($request->input('matches') as $index => $matchPayload) {
            $results[] = [
                'index' => $index,
                'matchId' => $matchPayload['matchId'] ?? null,
                'result' => $this->simulator->simulateFromPayload($matchPayload),
            ];
        }

        return response()->json(['results' => $results]);
    }
}
