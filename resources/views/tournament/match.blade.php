{{-- Tournament match shell page.
     The Alpine component will hydrate match data from IndexedDB.
     This is a minimal Blade shell — all interactivity is client-side. --}}
<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 py-6"
         x-data="{
             tournamentId: @js($tournamentId),
             matchId: @js($matchId),
             loading: true,
             error: null,
             match: null,
             homeTeam: null,
             awayTeam: null,
             homePlayers: [],
             awayPlayers: [],

             async init() {
                 try {
                     const { TournamentDB } = await import('/build/assets/tournament/db.js');
                     this.match = await TournamentDB.getMatch(this.matchId);
                     if (!this.match) {
                         this.error = '{{ __('tournament.match_not_found') }}';
                         this.loading = false;
                         return;
                     }
                     this.homeTeam = await TournamentDB.getTeam(this.match.homeTeamId);
                     this.awayTeam = await TournamentDB.getTeam(this.match.awayTeamId);
                     this.homePlayers = await TournamentDB.getPlayersForTeam(this.match.homeTeamId);
                     this.awayPlayers = await TournamentDB.getPlayersForTeam(this.match.awayTeamId);
                     this.loading = false;
                 } catch (err) {
                     this.error = err.message;
                     this.loading = false;
                 }
             }
         }">

        {{-- Loading --}}
        <template x-if="loading">
            <div class="flex items-center justify-center py-20">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-blue"></div>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-6 text-center">
                <p class="text-red-400" x-text="error"></p>
                <a :href="`/tournament/${tournamentId}`" class="inline-block mt-4 text-accent-blue hover:underline">
                    {{ __('tournament.back_to_hub') }}
                </a>
            </div>
        </template>

        {{-- Match content --}}
        <template x-if="!loading && !error && match">
            <div>
                {{-- Back link --}}
                <a :href="`/tournament/${tournamentId}`" class="text-text-secondary hover:text-text-primary text-sm mb-4 inline-block">
                    &larr; {{ __('tournament.back_to_hub') }}
                </a>

                {{-- Match header --}}
                <div class="bg-surface-800 rounded-lg border border-border-primary p-6 mb-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1 text-right">
                            <p class="text-lg font-bold text-text-primary" x-text="homeTeam?.name || 'Home'"></p>
                        </div>
                        <div class="text-center px-4">
                            <template x-if="match.played">
                                <div class="text-3xl font-mono font-bold text-text-primary"
                                     x-text="`${match.homeScore} - ${match.awayScore}`"></div>
                            </template>
                            <template x-if="!match.played">
                                <div class="text-text-secondary text-sm" x-text="match.scheduledDate"></div>
                            </template>
                            <template x-if="match.isExtraTime">
                                <div class="text-xs text-text-secondary mt-1">
                                    {{ __('tournament.et') }}: <span x-text="`${match.homeScoreEt} - ${match.awayScoreEt}`"></span>
                                </div>
                            </template>
                            <template x-if="match.homeScorePenalties !== null">
                                <div class="text-xs text-text-secondary mt-1">
                                    {{ __('tournament.pen') }}: <span x-text="`${match.homeScorePenalties} - ${match.awayScorePenalties}`"></span>
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 text-left">
                            <p class="text-lg font-bold text-text-primary" x-text="awayTeam?.name || 'Away'"></p>
                        </div>
                    </div>

                    <template x-if="match.played && match.homePossession">
                        <div class="mt-4 flex items-center gap-2 text-xs text-text-secondary">
                            <span x-text="`${match.homePossession}%`"></span>
                            <div class="flex-1 h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-blue rounded-full" :style="`width: ${match.homePossession}%`"></div>
                            </div>
                            <span x-text="`${match.awayPossession}%`"></span>
                        </div>
                    </template>
                </div>

                {{-- Placeholder for lineup selection + match simulation --}}
                <template x-if="!match.played">
                    <div class="bg-surface-800 rounded-lg border border-border-primary p-6 text-center">
                        <p class="text-text-secondary mb-4">{{ __('tournament.match_ready') }}</p>
                        <p class="text-text-secondary text-sm">{{ __('tournament.match_integration_pending') }}</p>
                    </div>
                </template>
            </div>
        </template>
    </div>
</x-app-layout>
