<?php

namespace App\Http\Actions\Tournament;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generate an auto-selected lineup from client-provided player data.
 *
 * Stateless — receives all players as JSON and returns the best 11 + bench
 * based on overall score and position requirements for the given formation.
 */
class AutoLineup
{
    private const FORMATION_REQUIREMENTS = [
        '4-4-2' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 4, 'Forward' => 2],
        '4-3-3' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3],
        '4-2-3-1' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
        '3-4-3' => ['Goalkeeper' => 1, 'Defender' => 3, 'Midfielder' => 4, 'Forward' => 3],
        '3-5-2' => ['Goalkeeper' => 1, 'Defender' => 3, 'Midfielder' => 5, 'Forward' => 2],
        '4-1-4-1' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
        '5-3-2' => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 3, 'Forward' => 2],
        '5-4-1' => ['Goalkeeper' => 1, 'Defender' => 5, 'Midfielder' => 4, 'Forward' => 1],
        '4-1-2-3' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 3, 'Forward' => 3],
        '4-3-2-1' => ['Goalkeeper' => 1, 'Defender' => 4, 'Midfielder' => 5, 'Forward' => 1],
    ];

    private const POSITION_GROUP_MAP = [
        'Goalkeeper' => 'Goalkeeper',
        'Centre-Back' => 'Defender',
        'Left-Back' => 'Defender',
        'Right-Back' => 'Defender',
        'Defensive Midfield' => 'Midfielder',
        'Central Midfield' => 'Midfielder',
        'Attacking Midfield' => 'Midfielder',
        'Left Midfield' => 'Midfielder',
        'Right Midfield' => 'Midfielder',
        'Left Winger' => 'Forward',
        'Right Winger' => 'Forward',
        'Centre-Forward' => 'Forward',
        'Second Striker' => 'Forward',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'players' => 'required|array',
            'formation' => 'nullable|string',
        ]);

        $formation = $request->input('formation', '4-4-2');
        $requirements = self::FORMATION_REQUIREMENTS[$formation] ?? self::FORMATION_REQUIREMENTS['4-4-2'];
        $players = collect($request->input('players'));

        // Sort players by overall score descending, with fitness tiebreaker
        $players = $players->sortByDesc(fn ($p) =>
            ($p['overallScore'] ?? 50) * 100 + ($p['fitness'] ?? 90)
        )->values();

        // Group by position group
        $byGroup = [];
        foreach ($players as $player) {
            $group = self::POSITION_GROUP_MAP[$player['position'] ?? ''] ?? 'Midfielder';
            $byGroup[$group][] = $player;
        }

        $lineup = [];
        $usedIds = [];

        // Fill each position group from best available
        foreach ($requirements as $group => $count) {
            $available = collect($byGroup[$group] ?? [])
                ->reject(fn ($p) => in_array($p['id'], $usedIds));

            $selected = $available->take($count);

            foreach ($selected as $player) {
                $lineup[] = $player;
                $usedIds[] = $player['id'];
            }

            // If not enough players in this group, fill from remaining
            $deficit = $count - $selected->count();
            if ($deficit > 0) {
                $fillers = $players->reject(fn ($p) => in_array($p['id'], $usedIds))->take($deficit);
                foreach ($fillers as $player) {
                    $lineup[] = $player;
                    $usedIds[] = $player['id'];
                }
            }
        }

        // Bench: next best players not in lineup (up to 12)
        $bench = $players->reject(fn ($p) => in_array($p['id'], $usedIds))
            ->take(12)
            ->values()
            ->all();

        return response()->json([
            'lineup' => array_values($lineup),
            'bench' => $bench,
            'formation' => $formation,
        ]);
    }
}
