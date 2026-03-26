<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modo Carrera Multijugador Online - Fase 1
 *
 * Tablas nuevas:
 *  - online_leagues          → la sala/liga online
 *  - online_league_slots     → cada equipo dentro de la liga (humano o CPU)
 *  - online_league_matches   → partidos de la liga online (independientes de game_matches)
 *  - online_league_standings → clasificación compartida de la liga online
 *  - online_player_season_stats → stats de temporada de cada jugador en la liga online
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // 1. SALA / LIGA ONLINE
        // ─────────────────────────────────────────────
        Schema::create('online_leagues', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Usuario que creó la sala
            $table->foreignId('owner_user_id')->constrained('users')->onDelete('cascade');

            // Competición base (ej. 'ESP1' para LaLiga)
            $table->string('competition_id', 10);
            $table->foreign('competition_id')->references('id')->on('competitions');

            $table->string('name');          // Nombre de la liga (ej. "Liga de los amigos")
            $table->string('invite_code', 12)->unique(); // código de invitación

            $table->unsignedSmallInteger('current_matchday')->default(0);
            $table->string('season', 10)->default('2025');

            // Estado de la liga
            $table->enum('status', [
                'lobby',      // esperando jugadores (pretemporada)
                'active',     // temporada en curso
                'finished',   // temporada terminada
            ])->default('lobby');

            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        // ─────────────────────────────────────────────
        // 2. SLOTS DE EQUIPO DENTRO DE LA LIGA
        //    Un slot por equipo participante.
        //    controller_type = 'human' | 'cpu'
        //    user_id = null  → CPU lo controla
        //    user_id = X     → el jugador X lo controla
        // ─────────────────────────────────────────────
        Schema::create('online_league_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('online_league_id');
            $table->foreign('online_league_id')->references('id')->on('online_leagues')->onDelete('cascade');

            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams');

            // Quién controla este equipo
            $table->enum('controller_type', ['human', 'cpu'])->default('cpu');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // game_id del jugador humano (null si CPU)
            // Cada jugador humano tiene su propia partida de carrera
            // con su equipo; este id la vincula a la liga online
            $table->uuid('game_id')->nullable();
            $table->foreign('game_id')->references('id')->on('games')->nullOnDelete();

            // Momento en que un jugador tomó control (mid-season join)
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['online_league_id', 'team_id']);
            $table->unique(['online_league_id', 'user_id']); // un jugador = un equipo por liga
        });

        // ─────────────────────────────────────────────
        // 3. PARTIDOS DE LA LIGA ONLINE
        //    Compartidos entre todos los jugadores.
        //    Cada partido tiene estado propio.
        // ─────────────────────────────────────────────
        Schema::create('online_league_matches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('online_league_id');
            $table->foreign('online_league_id')->references('id')->on('online_leagues')->onDelete('cascade');

            $table->unsignedSmallInteger('round_number');

            $table->uuid('home_team_id');
            $table->foreign('home_team_id')->references('id')->on('teams');

            $table->uuid('away_team_id');
            $table->foreign('away_team_id')->references('id')->on('teams');

            // Tipo de partido según controladores
            // human_vs_human → ambos deben pulsar "Jugar"
            // human_vs_cpu   → el humano pulsa "Jugar" y se simula
            // cpu_vs_cpu     → se simula automáticamente
            $table->enum('match_type', ['human_vs_human', 'human_vs_cpu', 'cpu_vs_cpu'])->default('cpu_vs_cpu');

            // Resultado
            $table->unsignedTinyInteger('home_score')->nullable();
            $table->unsignedTinyInteger('away_score')->nullable();

            $table->enum('status', [
                'pending',   // sin jugar
                'ready',     // al menos un humano ha confirmado
                'played',    // resultado registrado
            ])->default('pending');

            // Referencia al game_match del jugador humano (home)
            // para extraer eventos del partido cuando se juegue
            $table->uuid('home_game_match_id')->nullable();
            $table->uuid('away_game_match_id')->nullable();

            $table->timestamp('played_at')->nullable();
            $table->timestamps();

            $table->index(['online_league_id', 'round_number']);
            $table->index(['online_league_id', 'status']);
        });

        // ─────────────────────────────────────────────
        // 4. CLASIFICACIÓN DE LA LIGA ONLINE
        //    Una fila por equipo, se actualiza tras cada partido.
        // ─────────────────────────────────────────────
        Schema::create('online_league_standings', function (Blueprint $table) {
            $table->id();

            $table->uuid('online_league_id');
            $table->foreign('online_league_id')->references('id')->on('online_leagues')->onDelete('cascade');

            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams');

            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedSmallInteger('prev_position')->nullable();
            $table->unsignedSmallInteger('played')->default(0);
            $table->unsignedSmallInteger('won')->default(0);
            $table->unsignedSmallInteger('drawn')->default(0);
            $table->unsignedSmallInteger('lost')->default(0);
            $table->unsignedSmallInteger('goals_for')->default(0);
            $table->unsignedSmallInteger('goals_against')->default(0);
            $table->unsignedSmallInteger('points')->default(0);

            $table->unique(['online_league_id', 'team_id']);
            $table->index(['online_league_id', 'position']);
        });

        // ─────────────────────────────────────────────
        // 5. STATS DE TEMPORADA DE JUGADORES (ONLINE)
        //    Permite ver la ficha de cada jugador desde
        //    la clasificación: goles, asistencias, etc.
        //    Sin medias ni atributos ocultos.
        // ─────────────────────────────────────────────
        Schema::create('online_player_season_stats', function (Blueprint $table) {
            $table->id();

            $table->uuid('online_league_id');
            $table->foreign('online_league_id')->references('id')->on('online_leagues')->onDelete('cascade');

            // Referencia al jugador en la partida del manager (game_players)
            $table->uuid('game_player_id');
            $table->foreign('game_player_id')->references('id')->on('game_players')->onDelete('cascade');

            $table->uuid('team_id');
            $table->foreign('team_id')->references('id')->on('teams');

            // Stats visibles (sin atributos internos)
            $table->unsignedSmallInteger('appearances')->default(0);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('assists')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->unsignedSmallInteger('minutes_played')->default(0);
            $table->unsignedSmallInteger('clean_sheets')->default(0); // para porteros

            $table->unique(['online_league_id', 'game_player_id']);
            $table->index(['online_league_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_player_season_stats');
        Schema::dropIfExists('online_league_standings');
        Schema::dropIfExists('online_league_matches');
        Schema::dropIfExists('online_league_slots');
        Schema::dropIfExists('online_leagues');
    }
};
