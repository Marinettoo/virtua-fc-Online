<?php

namespace App\Http\Actions\Tournament;

use App\Models\Competition;
use App\Models\Team;
use App\Support\TeamColors;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Bootstrap a client-side tournament.
 *
 * Loads all WC2026 reference data (groups, bracket, teams, players) and
 * returns a JSON payload for the client to store in IndexedDB.
 * No game state is persisted server-side.
 */
class CreateTournament
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'userTeamId' => 'required|string|exists:teams,id',
        ]);

        $userTeamId = $request->input('userTeamId');
        $tournamentId = Str::uuid()->toString();

        // Load WC2026 reference data
        $groups = $this->loadJson('data/2025/WC2026/groups.json');
        $schedule = $this->loadJson('data/2025/WC2026/schedule.json');
        $bracket = $this->loadJson('data/2025/WC2026/bracket.json');
        $thirdPlaceTable = $this->loadJson('data/2025/WC2026/third_place_table.json');

        // Build team FIFA code → ID mapping from the database
        $allFifaCodes = [];
        foreach ($groups as $group) {
            foreach ($group['teams'] as $code) {
                $allFifaCodes[] = $code;
            }
        }

        $teams = Team::where('type', 'national')
            ->whereIn('fifa_code', $allFifaCodes)
            ->with('playerTemplates.player')
            ->get()
            ->keyBy('fifa_code');

        // Build tournament teams and players
        $tournamentTeams = [];
        $tournamentPlayers = [];
        $teamCodeToId = [];

        foreach ($teams as $fifaCode => $team) {
            $teamCodeToId[$fifaCode] = $team->id;

            $tournamentTeams[] = [
                'id' => $team->id,
                'tournamentId' => $tournamentId,
                'name' => $team->name,
                'shortName' => $fifaCode,
                'fifaCode' => $fifaCode,
                'country' => $team->country,
                'crestUrl' => $team->image,
                'colors' => $team->colors ? json_decode($team->colors, true) : TeamColors::get($team->name),
                'rating' => $this->calculateTeamRating($team),
            ];

            foreach ($team->playerTemplates as $pt) {
                $player = $pt->player;
                if (!$player) continue;

                $technical = $pt->game_technical_ability ?? $player->technical_ability ?? 60;
                $physical = $pt->game_physical_ability ?? $player->physical_ability ?? 60;

                $tournamentPlayers[] = [
                    'id' => Str::uuid()->toString(),
                    'templateId' => $pt->id,
                    'tournamentId' => $tournamentId,
                    'teamId' => $team->id,
                    'name' => $player->name,
                    'position' => $pt->position,
                    'number' => $pt->number ?? 0,
                    'technicalAbility' => $technical,
                    'physicalAbility' => $physical,
                    'fitness' => 95,
                    'morale' => 70,
                    'foot' => $player->foot ?? 'right',
                    'nationality' => $player->nationality ?? $team->country,
                    'dateOfBirth' => $player->date_of_birth?->format('Y-m-d'),
                    'height' => $player->height,
                    'overallScore' => (int) round(($technical * 0.6) + ($physical * 0.4)),
                ];
            }
        }

        // Build group match fixtures
        $matches = [];
        foreach ($groups as $groupLetter => $group) {
            foreach ($group['matches'] as $match) {
                $homeTeamId = $teamCodeToId[$match['home']] ?? null;
                $awayTeamId = $teamCodeToId[$match['away']] ?? null;

                if (!$homeTeamId || !$awayTeamId) continue;

                $matches[] = [
                    'id' => Str::uuid()->toString(),
                    'tournamentId' => $tournamentId,
                    'cupTieId' => null,
                    'groupLetter' => $groupLetter,
                    'roundNumber' => $match['round'],
                    'homeTeamId' => $homeTeamId,
                    'awayTeamId' => $awayTeamId,
                    'homeScore' => null,
                    'awayScore' => null,
                    'isExtraTime' => false,
                    'homeScoreEt' => null,
                    'awayScoreEt' => null,
                    'homeScorePenalties' => null,
                    'awayScorePenalties' => null,
                    'homeLineup' => null,
                    'awayLineup' => null,
                    'homeFormation' => '4-4-2',
                    'awayFormation' => '4-4-2',
                    'homePossession' => null,
                    'awayPossession' => null,
                    'played' => false,
                    'mvpPlayerId' => null,
                    'substitutions' => [],
                    'scheduledDate' => $match['date'],
                    'matchNumber' => null,
                ];
            }
        }

        // Build initial group standings
        $groupStandings = [];
        foreach ($groups as $groupLetter => $group) {
            foreach ($group['teams'] as $fifaCode) {
                $teamId = $teamCodeToId[$fifaCode] ?? null;
                if (!$teamId) continue;

                $groupStandings[] = [
                    'tournamentId' => $tournamentId,
                    'groupLetter' => $groupLetter,
                    'teamId' => $teamId,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goalsFor' => 0,
                    'goalsAgainst' => 0,
                    'goalDifference' => 0,
                    'points' => 0,
                    'position' => 0,
                ];
            }
        }

        $tournament = [
            'id' => $tournamentId,
            'name' => __('game.wc2026_name'),
            'season' => '2025',
            'format' => 'group_stage_cup',
            'currentPhase' => 'group_stage',
            'currentRound' => 1,
            'userTeamId' => $userTeamId,
            'status' => 'in_progress',
            'groups' => $groups,
            'schedule' => $schedule,
            'bracket' => $bracket,
            'thirdPlaceTable' => $thirdPlaceTable,
            'teamCodeToId' => $teamCodeToId,
            'createdAt' => now()->toISOString(),
        ];

        return response()->json([
            'tournament' => $tournament,
            'teams' => $tournamentTeams,
            'players' => $tournamentPlayers,
            'matches' => $matches,
            'groupStandings' => $groupStandings,
        ]);
    }

    private function loadJson(string $relativePath): array
    {
        $path = base_path($relativePath);

        return json_decode(file_get_contents($path), true);
    }

    private function calculateTeamRating(Team $team): int
    {
        $templates = $team->playerTemplates;
        if ($templates->isEmpty()) return 50;

        $totalScore = $templates->sum(function ($pt) {
            $technical = $pt->game_technical_ability ?? $pt->player?->technical_ability ?? 60;
            $physical = $pt->game_physical_ability ?? $pt->player?->physical_ability ?? 60;

            return (int) round(($technical * 0.6) + ($physical * 0.4));
        });

        return (int) round($totalScore / $templates->count());
    }
}
