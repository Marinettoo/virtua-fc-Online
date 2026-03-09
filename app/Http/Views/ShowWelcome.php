<?php

namespace App\Http\Views;

use App\Models\Competition;
use App\Models\Game;

class ShowWelcome
{
    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // If welcome is already complete, go to onboarding or game
        if (!$game->needsWelcome()) {
            if ($game->needsOnboarding()) {
                return redirect()->route('game.onboarding', $gameId);
            }
            return redirect()->route('show-game', $gameId);
        }

        $competition = Competition::find($game->competition_id);

        return view('welcome-tutorial', [
            'game' => $game,
            'competition' => $competition,
        ]);
    }
}
