<?php

namespace App\Console\Commands;

use App\Modules\Season\Services\GameDeletionService;
use App\Models\Game;
use Illuminate\Console\Command;

class CleanupGames extends Command
{
    protected $signature = 'app:cleanup-games
                            {--dry-run : Preview what would be deleted without actually deleting}
                            {--days=2 : Number of days of inactivity after which a game is considered stale}
                            {--all : Include all inactive games, not just unplayed ones (matchday 0)}';

    protected $description = 'Delete stale games based on inactivity. By default only unplayed games (matchday 0); use --all to include any inactive game.';

    public function handle(GameDeletionService $service): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');

        $query = Game::where('updated_at', '<', now()->subDays($days));

        if (! $all) {
            $query->where('current_matchday', 0)
                ->where('season', '2025');
        }

        $staleGames = $query->get();

        if ($staleGames->isEmpty()) {
            $this->info('No stale games found.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '')."Found {$staleGames->count()} stale game(s).");

        foreach ($staleGames as $game) {
            $this->line("  - Game {$game->id} (user: {$game->user_id}, team: {$game->team_id}, matchday: {$game->current_matchday}, last active: {$game->updated_at})");

            if (! $dryRun) {
                $service->delete($game);
            }
        }

        $this->info(($dryRun ? '[DRY RUN] Would delete' : 'Deleted')." {$staleGames->count()} stale game(s).");

        return Command::SUCCESS;
    }
}
