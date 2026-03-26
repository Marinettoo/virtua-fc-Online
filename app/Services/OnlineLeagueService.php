<?php

namespace App\Services;

use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\OnlineLeague;
use App\Models\OnlineLeagueMatch;
use App\Models\OnlineLeagueSlot;
use App\Models\OnlineLeagueStanding;
use App\Models\OnlinePlayerSeasonStats;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnlineLeagueService
{
    // ════════════════════════════════════════════════════════
    //  CREAR LIGA
    // ════════════════════════════════════════════════════════

    /**
     * Crea una nueva sala de liga online.
     * El owner elige su equipo; el resto queda como CPU.
     *
     * @param User   $owner         Usuario que crea la sala
     * @param string $competitionId ID de la competición base (ej. 'ESP1')
     * @param string $name          Nombre de la liga
     * @param string $ownerTeamId   UUID del equipo que elige el owner
     * @param string $ownerGameId   UUID de la partida de carrera del owner
     */
    public function createLeague(
        User $owner,
        string $competitionId,
        string $name,
        string $ownerTeamId,
        string $ownerGameId
    ): OnlineLeague {
        return DB::transaction(function () use ($owner, $competitionId, $name, $ownerTeamId, $ownerGameId) {

            // 1. Crear la sala
            $league = OnlineLeague::create([
                'id'             => Str::uuid(),
                'owner_user_id'  => $owner->id,
                'competition_id' => $competitionId,
                'name'           => $name,
                'invite_code'    => OnlineLeague::generateInviteCode(),
                'status'         => 'lobby',
                'season'         => '2025',
            ]);

            // 2. Obtener todos los equipos de esa competición
            $teamIds = DB::table('competition_teams')
                ->where('competition_id', $competitionId)
                ->where('season', '2025')
                ->pluck('team_id');

            // 3. Crear un slot por equipo (todos CPU inicialmente)
            foreach ($teamIds as $teamId) {
                $isOwner = $teamId === $ownerTeamId;

                OnlineLeagueSlot::create([
                    'id'               => Str::uuid(),
                    'online_league_id' => $league->id,
                    'team_id'          => $teamId,
                    'controller_type'  => $isOwner ? 'human' : 'cpu',
                    'user_id'          => $isOwner ? $owner->id : null,
                    'game_id'          => $isOwner ? $ownerGameId : null,
                    'joined_at'        => $isOwner ? now() : null,
                ]);
            }

            // 4. Inicializar clasificación vacía para todos los equipos
            foreach ($teamIds as $position => $teamId) {
                OnlineLeagueStanding::create([
                    'online_league_id' => $league->id,
                    'team_id'          => $teamId,
                    'position'         => $position + 1,
                ]);
            }

            return $league;
        });
    }

    // ════════════════════════════════════════════════════════
    //  UNIRSE A UNA LIGA EXISTENTE (CON CÓDIGO)
    // ════════════════════════════════════════════════════════

    /**
     * Un jugador entra a la liga mediante código de invitación
     * y toma control de un equipo que lleva la CPU.
     * Funciona tanto en lobby como a mitad de temporada.
     *
     * @param User   $user      Usuario que se une
     * @param string $code      Código de invitación de la liga
     * @param string $teamId    UUID del equipo CPU que quiere tomar
     * @param string $gameId    UUID de la partida de carrera del usuario
     */
    public function joinLeague(
        User $user,
        string $code,
        string $teamId,
        string $gameId
    ): OnlineLeagueSlot {
        $league = OnlineLeague::where('invite_code', $code)->firstOrFail();

        // El usuario no puede estar ya en esta liga
        abort_if(
            $league->slots()->where('user_id', $user->id)->exists(),
            422,
            'Ya estás en esta liga.'
        );

        // El equipo elegido debe existir y estar controlado por CPU
        $slot = $league->slots()
            ->where('team_id', $teamId)
            ->where('controller_type', 'cpu')
            ->firstOrFail();

        // El jugador toma control del slot
        $slot->claimByUser($user, app(\App\Models\Game::class)->findOrFail($gameId));

        // Si la liga estaba en lobby y ahora hay al menos 2 humanos,
        // el owner puede arrancarla cuando quiera (no se arranca automáticamente)
        return $slot->fresh();
    }

    // ════════════════════════════════════════════════════════
    //  ARRANCAR LA TEMPORADA (genera el calendario completo)
    // ════════════════════════════════════════════════════════

    /**
     * El owner arranca la liga: genera los partidos de todas las jornadas.
     * Solo puede hacerse desde el estado 'lobby'.
     */
    public function startLeague(OnlineLeague $league): void
    {
        abort_unless($league->isInLobby(), 422, 'La liga ya está iniciada.');

        DB::transaction(function () use ($league) {

            $slots = $league->slots()->with('team')->get();
            $teamIds = $slots->pluck('team_id')->toArray();

            // Generar calendario todos contra todos × 2 (ida + vuelta)
            $fixtures = $this->generateFixtures($teamIds);

            foreach ($fixtures as $roundNumber => $roundMatches) {
                foreach ($roundMatches as [$homeId, $awayId]) {

                    $matchType = $this->resolveMatchType($league, $homeId, $awayId);

                    OnlineLeagueMatch::create([
                        'id'               => Str::uuid(),
                        'online_league_id' => $league->id,
                        'round_number'     => $roundNumber,
                        'home_team_id'     => $homeId,
                        'away_team_id'     => $awayId,
                        'match_type'       => $matchType,
                        'status'           => 'pending',
                    ]);
                }
            }

            $league->update([
                'status'            => 'active',
                'current_matchday'  => 1,
                'started_at'        => now(),
            ]);
        });
    }

    // ════════════════════════════════════════════════════════
    //  AVANZAR JORNADA
    // ════════════════════════════════════════════════════════

    /**
     * Procesa la jornada actual:
     *  - Simula automáticamente todos los partidos CPU vs CPU
     *  - Simula los human_vs_cpu cuyo jugador humano haya confirmado
     *  - Avanza al siguiente matchday si todos los partidos están jugados
     */
    public function processMatchday(OnlineLeague $league): void
    {
        abort_unless($league->isActive(), 422, 'La liga no está activa.');

        DB::transaction(function () use ($league) {

            $pendingMatches = $league->matches()
                ->where('round_number', $league->current_matchday)
                ->where('status', '!=', 'played')
                ->get();

            foreach ($pendingMatches as $match) {
                if ($match->isCpuVsCpu()) {
                    // Simular automáticamente
                    $this->simulateCpuMatch($league, $match);
                }
                // Los human_vs_cpu y human_vs_human se resuelven
                // cuando el jugador pulsa "Jugar" desde su partida
            }

            // Comprobar si todos los partidos de la jornada están jugados
            $allPlayed = $league->matches()
                ->where('round_number', $league->current_matchday)
                ->where('status', '!=', 'played')
                ->doesntExist();

            if ($allPlayed) {
                $this->advanceMatchday($league);
            }
        });
    }

    /**
     * Registra el resultado de un partido con jugador humano.
     * Se llama cuando el jugador humano juega su partido en su carrera.
     *
     * @param OnlineLeagueMatch $onlineMatch  Partido de la liga online
     * @param int               $homeScore
     * @param int               $awayScore
     * @param string|null       $gameMatchId  ID del game_match del jugador (para copiar eventos)
     */
    public function registerHumanMatchResult(
        OnlineLeagueMatch $onlineMatch,
        int $homeScore,
        int $awayScore,
        ?string $gameMatchId = null
    ): void {
        DB::transaction(function () use ($onlineMatch, $homeScore, $awayScore, $gameMatchId) {

            $onlineMatch->registerResult($homeScore, $awayScore);

            // Actualizar clasificación
            $this->updateStandings($onlineMatch);

            // Actualizar stats de jugadores si viene el game_match_id
            if ($gameMatchId) {
                $this->syncPlayerStatsFromMatch($onlineMatch->league, $gameMatchId, $onlineMatch->home_team_id);
            }

            // Intentar avanzar jornada si ya están todos jugados
            $league = $onlineMatch->league;
            $allPlayed = $league->matches()
                ->where('round_number', $league->current_matchday)
                ->where('status', '!=', 'played')
                ->doesntExist();

            if ($allPlayed) {
                $this->advanceMatchday($league);
            }
        });
    }

    // ════════════════════════════════════════════════════════
    //  PRIVADOS
    // ════════════════════════════════════════════════════════

    /**
     * Genera el calendario todos contra todos × 2 (ida + vuelta).
     * Algoritmo round-robin estándar.
     *
     * @param  array $teamIds  Lista de UUIDs de equipos
     * @return array           [roundNumber => [[homeId, awayId], ...]]
     */
    private function generateFixtures(array $teamIds): array
    {
        $teams = $teamIds;
        $n = count($teams);

        // Si número impar, añadir equipo fantasma (bye)
        if ($n % 2 !== 0) {
            $teams[] = null;
            $n++;
        }

        $fixtures = [];
        $half = $n / 2;
        $rounds = $n - 1;

        // Ida
        for ($round = 0; $round < $rounds; $round++) {
            $roundMatches = [];
            for ($i = 0; $i < $half; $i++) {
                $home = $teams[$i];
                $away = $teams[$n - 1 - $i];
                // Ignorar partidos con equipo fantasma
                if ($home !== null && $away !== null) {
                    $roundMatches[] = [$home, $away];
                }
            }
            $fixtures[$round + 1] = $roundMatches;

            // Rotar (fijando el primer equipo)
            $last = array_pop($teams);
            array_splice($teams, 1, 0, [$last]);
        }

        // Vuelta (invertir local/visitante)
        $returnFixtures = [];
        foreach ($fixtures as $round => $matches) {
            $returnMatches = array_map(fn ($m) => [$m[1], $m[0]], $matches);
            $returnFixtures[$rounds + $round] = $returnMatches;
        }

        return $fixtures + $returnFixtures;
    }

    /**
     * Determina el tipo de partido según quién controla cada equipo.
     */
    private function resolveMatchType(OnlineLeague $league, string $homeId, string $awayId): string
    {
        $slots = $league->slots()->whereIn('team_id', [$homeId, $awayId])->get()->keyBy('team_id');

        $homeIsHuman = isset($slots[$homeId]) && $slots[$homeId]->isHuman();
        $awayIsHuman = isset($slots[$awayId]) && $slots[$awayId]->isHuman();

        if ($homeIsHuman && $awayIsHuman) return 'human_vs_human';
        if ($homeIsHuman || $awayIsHuman) return 'human_vs_cpu';
        return 'cpu_vs_cpu';
    }

    /**
     * Simula un partido CPU vs CPU con resultado aleatorio ponderado.
     * Usa la reputación del equipo si existe, si no usa distribución plana.
     */
    private function simulateCpuMatch(OnlineLeague $league, OnlineLeagueMatch $match): void
    {
        [$homeGoals, $awayGoals] = $this->randomScore(
            $this->getTeamStrength($match->home_team_id),
            $this->getTeamStrength($match->away_team_id)
        );

        $match->registerResult($homeGoals, $awayGoals);
        $this->updateStandings($match);
    }

    /**
     * Obtiene la fuerza relativa de un equipo (0.0 – 1.0).
     * Busca en team_reputations si existe, si no devuelve 0.5 (neutral).
     */
    private function getTeamStrength(string $teamId): float
    {
        $rep = DB::table('team_reputations')->where('team_id', $teamId)->first();
        if (! $rep) return 0.5;

        // Normaliza el valor de reputación a 0-1
        // La tabla guarda valores de 1 a 5 (estrellas)
        return min(max(($rep->overall ?? 3) / 5, 0.1), 0.9);
    }

    /**
     * Genera un resultado aleatorio ponderado por fuerza de los equipos.
     * @return array [homeGoals, awayGoals]
     */
    private function randomScore(float $homeStrength, float $awayStrength): array
    {
        // Ventaja de jugar en casa (+10%)
        $homeStrength = min($homeStrength + 0.1, 1.0);

        $homeExpected = $homeStrength * 2.5;   // media de goles esperados
        $awayExpected = $awayStrength * 2.0;

        // Distribución de Poisson aproximada con rand
        $homeGoals = $this->poissonRandom($homeExpected);
        $awayGoals = $this->poissonRandom($awayExpected);

        return [$homeGoals, $awayGoals];
    }

    /**
     * Genera un entero con distribución de Poisson (aproximación).
     */
    private function poissonRandom(float $lambda): int
    {
        $lambda = max(0.1, min($lambda, 8.0));
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= (float) mt_rand() / mt_getrandmax();
        } while ($p > $L);

        return max(0, $k - 1);
    }

    /**
     * Actualiza la clasificación de la liga tras un partido jugado.
     */
    private function updateStandings(OnlineLeagueMatch $match): void
    {
        $homeStanding = OnlineLeagueStanding::where('online_league_id', $match->online_league_id)
            ->where('team_id', $match->home_team_id)
            ->first();

        $awayStanding = OnlineLeagueStanding::where('online_league_id', $match->online_league_id)
            ->where('team_id', $match->away_team_id)
            ->first();

        if ($homeStanding) $homeStanding->applyResult($match->home_score, $match->away_score);
        if ($awayStanding) $awayStanding->applyResult($match->away_score, $match->home_score);

        // Recalcular posiciones
        $this->recalculatePositions($match->online_league_id);
    }

    /**
     * Recalcula las posiciones de la clasificación.
     * Criterios: puntos → diferencia goles → goles a favor.
     */
    private function recalculatePositions(string $leagueId): void
    {
        $standings = OnlineLeagueStanding::where('online_league_id', $leagueId)
            ->orderByDesc('points')
            ->orderByDesc(DB::raw('goals_for - goals_against'))
            ->orderByDesc('goals_for')
            ->get();

        foreach ($standings as $position => $standing) {
            $standing->prev_position = $standing->position;
            $standing->position      = $position + 1;
            $standing->save();
        }
    }

    /**
     * Avanza al siguiente matchday o marca la liga como terminada.
     */
    private function advanceMatchday(OnlineLeague $league): void
    {
        $totalRounds = $league->matches()->max('round_number');

        if ($league->current_matchday >= $totalRounds) {
            $league->update(['status' => 'finished']);
        } else {
            $league->increment('current_matchday');

            // Recalcular tipos de partido para la nueva jornada
            // (por si algún equipo CPU fue tomado por un humano)
            $this->refreshMatchTypesForCurrentMatchday($league->fresh());
        }
    }

    /**
     * Actualiza el match_type de los partidos de la jornada actual.
     * Necesario si un jugador tomó un equipo CPU entre jornadas.
     */
    private function refreshMatchTypesForCurrentMatchday(OnlineLeague $league): void
    {
        $matches = $league->matches()
            ->where('round_number', $league->current_matchday)
            ->where('status', 'pending')
            ->get();

        foreach ($matches as $match) {
            $newType = $this->resolveMatchType($league, $match->home_team_id, $match->away_team_id);
            if ($newType !== $match->match_type) {
                $match->update(['match_type' => $newType]);
            }
        }
    }

    /**
     * Copia las stats de los jugadores desde los eventos del game_match
     * al registro de stats de la liga online.
     *
     * @param OnlineLeague $league
     * @param string       $gameMatchId  ID del game_match de la partida individual
     * @param string       $teamId       Equipo cuyos jugadores se actualizan
     */
    private function syncPlayerStatsFromMatch(
        OnlineLeague $league,
        string $gameMatchId,
        string $teamId
    ): void {
        // Recuperar eventos del partido (goles, asistencias, tarjetas)
        $events = MatchEvent::where('game_match_id', $gameMatchId)->get();

        // Agrupar eventos por jugador
        $byPlayer = $events->groupBy('game_player_id');

        foreach ($byPlayer as $playerId => $playerEvents) {
            // Obtener o crear el registro de stats
            $stats = OnlinePlayerSeasonStats::firstOrCreate(
                [
                    'online_league_id' => $league->id,
                    'game_player_id'   => $playerId,
                    'team_id'          => $teamId,
                ],
                [
                    'appearances'    => 0,
                    'goals'          => 0,
                    'assists'        => 0,
                    'yellow_cards'   => 0,
                    'red_cards'      => 0,
                    'minutes_played' => 0,
                    'clean_sheets'   => 0,
                ]
            );

            $goals       = $playerEvents->where('type', 'goal')->count();
            $assists     = $playerEvents->where('type', 'assist')->count();
            $yellows     = $playerEvents->where('type', 'yellow_card')->count();
            $reds        = $playerEvents->where('type', 'red_card')->count();
            $minutes     = $playerEvents->where('type', 'minutes_played')->sum('value');
            $cleanSheet  = $playerEvents->where('type', 'clean_sheet')->isNotEmpty();

            $stats->addMatchStats([
                'goals'        => $goals,
                'assists'      => $assists,
                'yellow_cards' => $yellows,
                'red_cards'    => $reds,
                'minutes'      => $minutes ?: 90,
                'clean_sheet'  => $cleanSheet,
            ]);
        }
    }
}
