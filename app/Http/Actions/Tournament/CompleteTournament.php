<?php

namespace App\Http\Actions\Tournament;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persist final tournament results for leaderboards and achievements.
 *
 * Called by the client when a tournament is complete.
 * Stores a summary record — does not import full match data.
 */
class CompleteTournament
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'tournamentId' => 'required|string',
            'champion' => 'required|array',
            'champion.teamId' => 'required|string',
            'champion.teamName' => 'required|string',
            'runnerUp' => 'required|array',
            'runnerUp.teamId' => 'required|string',
            'runnerUp.teamName' => 'required|string',
            'thirdPlace' => 'nullable|array',
            'topScorer' => 'nullable|array',
            'userTeamId' => 'required|string',
            'userTeamFinish' => 'required|string',
            'totalMatches' => 'required|integer',
        ]);

        DB::table('tournament_results')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $request->user()->id,
            'tournament_id' => $request->input('tournamentId'),
            'tournament_type' => 'world_cup',
            'champion_team_id' => $request->input('champion.teamId'),
            'champion_team_name' => $request->input('champion.teamName'),
            'runner_up_team_id' => $request->input('runnerUp.teamId'),
            'runner_up_team_name' => $request->input('runnerUp.teamName'),
            'third_place_team_id' => $request->input('thirdPlace.teamId'),
            'third_place_team_name' => $request->input('thirdPlace.teamName'),
            'top_scorer_name' => $request->input('topScorer.name'),
            'top_scorer_goals' => $request->input('topScorer.goals'),
            'user_team_id' => $request->input('userTeamId'),
            'user_team_finish' => $request->input('userTeamFinish'),
            'total_matches' => $request->input('totalMatches'),
            'completed_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
