<?php

namespace App\Modules\Match\Services;

use App\Models\Game;
use App\Models\Team;
use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use Carbon\Carbon;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Match\DTOs\MatchSimulationOutput;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stateless adapter for MatchSimulator that works with plain arrays.
 *
 * Hydrates lightweight Team and GamePlayer objects from JSON payloads,
 * runs the existing MatchSimulator, and returns serializable results.
 * No database reads or writes — designed for the client-side tournament API.
 */
class StatelessMatchSimulator
{
    public function __construct(
        private readonly MatchSimulator $simulator,
    ) {}

    /**
     * Simulate a match from client-provided data.
     *
     * @param  array  $payload  Match data with teams, players, tactics
     * @return array Serializable result with scores, events, possession
     */
    public function simulateFromPayload(array $payload): array
    {
        $homeTeam = $this->hydrateTeam($payload['homeTeam']);
        $awayTeam = $this->hydrateTeam($payload['awayTeam']);
        $homePlayers = $this->hydratePlayers($payload['homeTeam']['lineup']);
        $awayPlayers = $this->hydratePlayers($payload['awayTeam']['lineup']);
        $homeBench = isset($payload['homeTeam']['bench'])
            ? $this->hydratePlayers($payload['homeTeam']['bench'])
            : null;
        $awayBench = isset($payload['awayTeam']['bench'])
            ? $this->hydratePlayers($payload['awayTeam']['bench'])
            : null;

        $homeFormation = Formation::tryFrom($payload['homeFormation'] ?? '4-4-2') ?? Formation::F_4_4_2;
        $awayFormation = Formation::tryFrom($payload['awayFormation'] ?? '4-4-2') ?? Formation::F_4_4_2;
        $homeMentality = Mentality::tryFrom($payload['homeMentality'] ?? 'balanced') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($payload['awayMentality'] ?? 'balanced') ?? Mentality::BALANCED;
        $homePlayingStyle = PlayingStyle::tryFrom($payload['homePlayingStyle'] ?? 'balanced') ?? PlayingStyle::BALANCED;
        $awayPlayingStyle = PlayingStyle::tryFrom($payload['awayPlayingStyle'] ?? 'balanced') ?? PlayingStyle::BALANCED;
        $homePressing = PressingIntensity::tryFrom($payload['homePressing'] ?? 'standard') ?? PressingIntensity::STANDARD;
        $awayPressing = PressingIntensity::tryFrom($payload['awayPressing'] ?? 'standard') ?? PressingIntensity::STANDARD;
        $homeDefLine = DefensiveLineHeight::tryFrom($payload['homeDefensiveLine'] ?? 'normal') ?? DefensiveLineHeight::NORMAL;
        $awayDefLine = DefensiveLineHeight::tryFrom($payload['awayDefensiveLine'] ?? 'normal') ?? DefensiveLineHeight::NORMAL;

        $matchSeed = $payload['matchSeed'] ?? Str::uuid()->toString();

        $output = $this->simulator->simulate(
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            homePlayers: $homePlayers,
            awayPlayers: $awayPlayers,
            homeFormation: $homeFormation,
            awayFormation: $awayFormation,
            homeMentality: $homeMentality,
            awayMentality: $awayMentality,
            homePlayingStyle: $homePlayingStyle,
            awayPlayingStyle: $awayPlayingStyle,
            homePressing: $homePressing,
            awayPressing: $awayPressing,
            homeDefLine: $homeDefLine,
            awayDefLine: $awayDefLine,
            homeBenchPlayers: $homeBench,
            awayBenchPlayers: $awayBench,
            matchSeed: $matchSeed,
        );

        return $this->serializeOutput($output, $payload);
    }

    /**
     * Simulate extra time from client-provided data.
     */
    public function simulateExtraTimeFromPayload(array $payload): array
    {
        $homeTeam = $this->hydrateTeam($payload['homeTeam']);
        $awayTeam = $this->hydrateTeam($payload['awayTeam']);
        $homePlayers = $this->hydratePlayers($payload['homeTeam']['lineup']);
        $awayPlayers = $this->hydratePlayers($payload['awayTeam']['lineup']);

        $homeFormation = Formation::tryFrom($payload['homeFormation'] ?? '4-4-2') ?? Formation::F_4_4_2;
        $awayFormation = Formation::tryFrom($payload['awayFormation'] ?? '4-4-2') ?? Formation::F_4_4_2;
        $homeMentality = Mentality::tryFrom($payload['homeMentality'] ?? 'balanced') ?? Mentality::BALANCED;
        $awayMentality = Mentality::tryFrom($payload['awayMentality'] ?? 'balanced') ?? Mentality::BALANCED;

        $homeEntryMinutes = $payload['homeEntryMinutes'] ?? [];
        $awayEntryMinutes = $payload['awayEntryMinutes'] ?? [];

        $result = $this->simulator->simulateExtraTime(
            $homeTeam,
            $awayTeam,
            $homePlayers,
            $awayPlayers,
            $homeEntryMinutes,
            $awayEntryMinutes,
            homeFormation: $homeFormation,
            awayFormation: $awayFormation,
            homeMentality: $homeMentality,
            awayMentality: $awayMentality,
        );

        return [
            'homeScoreET' => $result->homeScore,
            'awayScoreET' => $result->awayScore,
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
            'events' => $result->events->map(fn ($e) => $e->toArray())->values()->all(),
            'needsPenalties' => ($result->homeScore === $result->awayScore),
        ];
    }

    /**
     * Simulate a penalty shootout from client-provided data.
     */
    public function simulatePenaltiesFromPayload(array $payload): array
    {
        $homeKickers = collect($payload['homeKickers'] ?? []);
        $awayKickers = collect($payload['awayKickers'] ?? []);
        $homePlayers = $this->hydratePlayers($payload['homeTeam']['lineup']);
        $awayPlayers = $this->hydratePlayers($payload['awayTeam']['lineup']);

        // Simple penalty simulation: each kicker has a chance based on ability
        $homeGoals = 0;
        $awayGoals = 0;
        $kicks = [];

        for ($round = 0; $round < 5; $round++) {
            $homeKicker = $homeKickers[$round] ?? null;
            $awayKicker = $awayKickers[$round] ?? null;

            if ($homeKicker) {
                $scored = $this->simulatePenaltyKick($homeKicker['technicalAbility'] ?? 70);
                if ($scored) $homeGoals++;
                $kicks[] = [
                    'round' => $round + 1,
                    'team' => 'home',
                    'playerId' => $homeKicker['id'],
                    'playerName' => $homeKicker['name'] ?? '',
                    'scored' => $scored,
                ];
            }

            if ($awayKicker) {
                $scored = $this->simulatePenaltyKick($awayKicker['technicalAbility'] ?? 70);
                if ($scored) $awayGoals++;
                $kicks[] = [
                    'round' => $round + 1,
                    'team' => 'away',
                    'playerId' => $awayKicker['id'],
                    'playerName' => $awayKicker['name'] ?? '',
                    'scored' => $scored,
                ];
            }

            // Early termination check
            $roundsRemaining = 4 - $round;
            if (abs($homeGoals - $awayGoals) > $roundsRemaining) {
                break;
            }
        }

        // Sudden death if tied after 5 rounds
        $suddenDeathRound = 6;
        while ($homeGoals === $awayGoals && $suddenDeathRound <= 20) {
            $homeIdx = ($suddenDeathRound - 1) % max(1, $homeKickers->count());
            $awayIdx = ($suddenDeathRound - 1) % max(1, $awayKickers->count());

            $homeKicker = $homeKickers[$homeIdx] ?? $homeKickers->first();
            $awayKicker = $awayKickers[$awayIdx] ?? $awayKickers->first();

            $homeScored = $this->simulatePenaltyKick($homeKicker['technicalAbility'] ?? 70);
            $awayScored = $this->simulatePenaltyKick($awayKicker['technicalAbility'] ?? 70);

            if ($homeScored) $homeGoals++;
            if ($awayScored) $awayGoals++;

            $kicks[] = [
                'round' => $suddenDeathRound,
                'team' => 'home',
                'playerId' => $homeKicker['id'],
                'playerName' => $homeKicker['name'] ?? '',
                'scored' => $homeScored,
            ];
            $kicks[] = [
                'round' => $suddenDeathRound,
                'team' => 'away',
                'playerId' => $awayKicker['id'],
                'playerName' => $awayKicker['name'] ?? '',
                'scored' => $awayScored,
            ];

            $suddenDeathRound++;
        }

        return [
            'homeScorePenalties' => $homeGoals,
            'awayScorePenalties' => $awayGoals,
            'kicks' => $kicks,
            'winner' => $homeGoals > $awayGoals ? 'home' : 'away',
        ];
    }

    private function simulatePenaltyKick(int $technicalAbility): bool
    {
        // Base conversion rate: 75%. Elite players (90+) get up to 85%.
        $baseRate = 75.0;
        $bonus = max(0, ($technicalAbility - 70)) / 100 * 10; // up to +10% for 100 ability

        return (mt_rand(0, 10000) / 100) < ($baseRate + $bonus);
    }

    /**
     * Hydrate a lightweight Team model from an array.
     */
    private function hydrateTeam(array $data): Team
    {
        $team = new Team();
        $team->id = $data['id'];
        $team->name = $data['name'] ?? '';
        $team->country = $data['country'] ?? '';
        $team->exists = true; // prevent Eloquent from trying to insert

        return $team;
    }

    /**
     * Hydrate a collection of GamePlayer models from arrays.
     *
     * Creates lightweight GamePlayer + Player objects that satisfy
     * the MatchSimulator's attribute and relationship access patterns.
     *
     * @return Collection<GamePlayer>
     */
    private function hydratePlayers(array $playersData): Collection
    {
        return collect($playersData)->map(function (array $data) {
            // Create a minimal Player model for relationship access (age(), name, etc.)
            $refPlayer = new \App\Models\Player();
            $refPlayer->id = $data['id'] . '-ref';
            $refPlayer->name = $data['name'] ?? '';
            $refPlayer->date_of_birth = Carbon::parse($data['dateOfBirth'] ?? '1995-01-01');
            $refPlayer->foot = $data['foot'] ?? 'right';
            $refPlayer->nationality = $data['nationality'] ?? '';
            $refPlayer->height = $data['height'] ?? null;
            $refPlayer->technical_ability = $data['technicalAbility'] ?? 70;
            $refPlayer->physical_ability = $data['physicalAbility'] ?? 70;
            $refPlayer->exists = true;

            $player = new GamePlayer();
            $player->id = $data['id'];
            $player->team_id = $data['teamId'];
            $player->position = $data['position'];
            $player->number = $data['number'] ?? 0;
            $player->game_technical_ability = $data['technicalAbility'] ?? 70;
            $player->game_physical_ability = $data['physicalAbility'] ?? 70;
            $player->fitness = $data['fitness'] ?? 90;
            $player->morale = $data['morale'] ?? 70;
            $player->exists = true;

            // Set the Player relation without DB access
            $player->setRelation('player', $refPlayer);

            // Set a minimal Game relation for age() access to current_date
            $game = new Game();
            $game->current_date = Carbon::parse('2026-06-11');
            $player->setRelation('game', $game);

            return $player;
        });
    }

    /**
     * Serialize a MatchSimulationOutput to a client-friendly array.
     */
    private function serializeOutput(MatchSimulationOutput $output, array $payload): array
    {
        $result = $output->result;
        $events = $result->events->map(fn ($e) => $e->toArray())->values()->all();

        $response = [
            'homeScore' => $result->homeScore,
            'awayScore' => $result->awayScore,
            'homePossession' => $result->homePossession,
            'awayPossession' => $result->awayPossession,
            'events' => $events,
            'performances' => $output->performances,
        ];

        // Determine MVP
        $allPlayers = array_merge(
            $payload['homeTeam']['lineup'] ?? [],
            $payload['awayTeam']['lineup'] ?? [],
        );
        $mvp = $this->determineMVP($allPlayers, $events, $output->performances);
        if ($mvp) {
            $response['mvpPlayerId'] = $mvp;
        }

        return $response;
    }

    /**
     * Determine the MVP based on events and performance ratings.
     */
    private function determineMVP(array $players, array $events, array $performances): ?string
    {
        $scores = [];
        foreach ($players as $p) {
            $id = $p['id'];
            $perf = $performances[$id] ?? 1.0;
            $scores[$id] = MatchSimulator::performanceToRating($perf);
        }

        // Bonus for goals and assists
        foreach ($events as $event) {
            $playerId = $event['game_player_id'] ?? null;
            if (!$playerId || !isset($scores[$playerId])) continue;

            if ($event['event_type'] === 'goal') {
                $scores[$playerId] += 1.0;
            } elseif ($event['event_type'] === 'assist') {
                $scores[$playerId] += 0.5;
            } elseif ($event['event_type'] === 'red_card') {
                $scores[$playerId] -= 3.0;
            }
        }

        if (empty($scores)) return null;

        arsort($scores);

        return array_key_first($scores);
    }
}
