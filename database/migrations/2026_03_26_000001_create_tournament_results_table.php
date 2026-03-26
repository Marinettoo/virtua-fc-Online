<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('tournament_id');
            $table->string('tournament_type');
            $table->uuid('champion_team_id');
            $table->string('champion_team_name');
            $table->uuid('runner_up_team_id');
            $table->string('runner_up_team_name');
            $table->uuid('third_place_team_id')->nullable();
            $table->string('third_place_team_name')->nullable();
            $table->string('top_scorer_name')->nullable();
            $table->unsignedInteger('top_scorer_goals')->nullable();
            $table->uuid('user_team_id');
            $table->string('user_team_finish');
            $table->unsignedInteger('total_matches');
            $table->timestamp('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_results');
    }
};
