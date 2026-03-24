<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transaction wrapping — CREATE INDEX CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS game_matches_mvp_player_id_index ON game_matches (mvp_player_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS game_matches_cup_tie_id_index ON game_matches (cup_tie_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS game_transfers_from_team_id_index ON game_transfers (from_team_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS financial_transactions_related_player_id_index ON financial_transactions (related_player_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS teams_parent_team_id_index ON teams (parent_team_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS activation_events_game_id_index ON activation_events (game_id)');
        } else {
            Schema::table('game_matches', function (Blueprint $table) {
                $table->index('mvp_player_id');
                $table->index('cup_tie_id');
            });

            Schema::table('game_transfers', function (Blueprint $table) {
                $table->index('from_team_id');
            });

            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->index('related_player_id');
            });

            Schema::table('teams', function (Blueprint $table) {
                $table->index('parent_team_id');
            });

            Schema::table('activation_events', function (Blueprint $table) {
                $table->index('game_id');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS game_matches_mvp_player_id_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS game_matches_cup_tie_id_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS game_transfers_from_team_id_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS financial_transactions_related_player_id_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS teams_parent_team_id_index');
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS activation_events_game_id_index');
        } else {
            Schema::table('game_matches', function (Blueprint $table) {
                $table->dropIndex(['mvp_player_id']);
                $table->dropIndex(['cup_tie_id']);
            });

            Schema::table('game_transfers', function (Blueprint $table) {
                $table->dropIndex(['from_team_id']);
            });

            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->dropIndex(['related_player_id']);
            });

            Schema::table('teams', function (Blueprint $table) {
                $table->dropIndex(['parent_team_id']);
            });

            Schema::table('activation_events', function (Blueprint $table) {
                $table->dropIndex(['game_id']);
            });
        }
    }
};
