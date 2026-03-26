{{-- Tournament match page.
     Full match lifecycle: lineup selection → simulation → result display.
     All data loaded from IndexedDB — server only runs match simulation. --}}
<x-app-layout :hideFooter="true">
    <div class="max-w-4xl mx-auto px-4 py-4 pb-32"
         x-data="tournamentMatch({
             tournamentId: @js($tournamentId),
             matchId: @js($matchId),
             translations: {
                 matchNotFound: @js(__('tournament.match_not_found')),
                 win: @js(__('tournament.result_win')),
                 loss: @js(__('tournament.result_loss')),
                 draw: @js(__('tournament.result_draw')),
                 simulatingOthers: @js(__('tournament.simulating_others')),
             },
         })"
>

        {{-- Loading --}}
        <template x-if="loading">
            <div class="flex items-center justify-center py-20">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-blue"></div>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error && !loading">
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-6 text-center">
                <p class="text-red-400" x-text="error"></p>
                <a :href="`/tournament/${tournamentId}`" class="inline-block mt-4 text-accent-blue hover:underline">
                    {{ __('tournament.back_to_hub') }}
                </a>
            </div>
        </template>

        {{-- Main content --}}
        <template x-if="!loading && !error && match">
            <div>
                {{-- Back link --}}
                <a :href="`/tournament/${tournamentId}`"
                   class="text-text-secondary hover:text-text-primary text-sm mb-4 inline-flex items-center gap-1"
                   x-show="phase === 'pre_match' || phase === 'full_time'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    {{ __('tournament.back_to_hub') }}
                </a>

                {{-- Match context badge --}}
                <div class="text-center mb-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-accent-gold/10 text-accent-gold text-[10px] font-semibold uppercase tracking-wider"
                          x-text="match.groupLetter ? '{{ __('tournament.group') }} ' + match.groupLetter + ' — {{ __('game.matchday_n', ['number' => '']) }}' + match.roundNumber : '{{ __('tournament.knockout_stage') }}'">
                    </span>
                </div>

                {{-- Scoreboard --}}
                <div class="bg-surface-800 rounded-xl border border-border-primary p-4 md:p-6 mb-6">
                    <div class="flex items-center justify-center gap-3 md:gap-8">
                        {{-- Home team --}}
                        <div class="flex-1 text-right">
                            <p class="text-base md:text-xl font-heading font-bold uppercase tracking-wide truncate"
                               :class="isUserHome ? 'text-accent-blue' : 'text-text-primary'"
                               x-text="homeTeam?.name"></p>
                            <p class="text-[10px] text-text-muted uppercase tracking-wider mt-0.5"
                               x-text="homeTeam?.fifaCode"></p>
                        </div>

                        {{-- Score --}}
                        <div class="text-center px-3 md:px-6 shrink-0">
                            <template x-if="phase === 'pre_match'">
                                <div class="font-heading text-4xl md:text-5xl font-extrabold text-text-muted">vs</div>
                            </template>
                            <template x-if="phase !== 'pre_match'">
                                <div>
                                    <div class="font-heading text-4xl md:text-5xl font-extrabold text-text-primary tabular-nums">
                                        <span x-text="homeScore"></span>
                                        <span class="text-text-muted mx-1">:</span>
                                        <span x-text="awayScore"></span>
                                    </div>
                                    {{-- ET score --}}
                                    <template x-if="etHomeScore > 0 || etAwayScore > 0">
                                        <div class="text-xs text-text-secondary mt-1">
                                            {{ __('tournament.et') }}:
                                            <span x-text="`${etHomeScore} - ${etAwayScore}`"></span>
                                        </div>
                                    </template>
                                    {{-- Penalty score --}}
                                    <template x-if="penaltyWinner">
                                        <div class="text-xs text-penalty-text mt-1">
                                            {{ __('tournament.pen') }}:
                                            <span x-text="`${penaltyHomeScore} - ${penaltyAwayScore}`"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Away team --}}
                        <div class="flex-1 text-left">
                            <p class="text-base md:text-xl font-heading font-bold uppercase tracking-wide truncate"
                               :class="!isUserHome ? 'text-accent-blue' : 'text-text-primary'"
                               x-text="awayTeam?.name"></p>
                            <p class="text-[10px] text-text-muted uppercase tracking-wider mt-0.5"
                               x-text="awayTeam?.fifaCode"></p>
                        </div>
                    </div>

                    {{-- Phase indicator --}}
                    <div class="text-center mt-3">
                        <span class="inline-flex items-center gap-2 text-xs font-heading font-bold uppercase tracking-wider px-3 py-1 rounded-full"
                              :class="{
                                  'bg-surface-700 text-text-muted': phase === 'pre_match',
                                  'bg-accent-green/10 text-accent-green': phase === 'simulating',
                                  'bg-accent-green/10 text-accent-green': phase === 'playing',
                                  'bg-accent-orange/10 text-accent-orange': phase === 'extra_time',
                                  'bg-purple-500/10 text-penalty-text': phase === 'penalties',
                                  'bg-surface-700 text-text-primary': phase === 'full_time',
                              }">
                            <template x-if="phase === 'simulating'">
                                <span class="flex items-center gap-2">
                                    <svg class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    {{ __('tournament.simulating') }}
                                </span>
                            </template>
                            <template x-if="phase === 'playing'">
                                <span class="flex items-center gap-2">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-green opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-green"></span>
                                    </span>
                                    {{ __('game.live_label') }}
                                </span>
                            </template>
                            <template x-if="phase === 'pre_match'">
                                <span>{{ __('game.live_pre_match') }}</span>
                            </template>
                            <template x-if="phase === 'extra_time'">
                                <span>{{ __('game.live_extra_time') }}</span>
                            </template>
                            <template x-if="phase === 'penalties'">
                                <span>{{ __('game.live_penalties') }}</span>
                            </template>
                            <template x-if="phase === 'full_time'">
                                <span>{{ __('game.live_full_time') }}</span>
                            </template>
                        </span>
                    </div>

                    {{-- Possession bar --}}
                    <template x-if="phase === 'full_time' || phase === 'playing'">
                        <div class="mt-4 flex items-center gap-2 text-xs text-text-secondary">
                            <span x-text="`${homePossession}%`" class="tabular-nums"></span>
                            <div class="flex-1 h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                <div class="h-full bg-accent-blue rounded-full transition-all duration-500" :style="`width: ${homePossession}%`"></div>
                            </div>
                            <span x-text="`${awayPossession}%`" class="tabular-nums"></span>
                        </div>
                    </template>
                </div>

                {{-- ============ PRE-MATCH: Lineup Selection ============ --}}
                <template x-if="phase === 'pre_match'">
                    <div>
                        {{-- Formation + Mentality selectors --}}
                        <div class="flex flex-wrap gap-3 mb-4">
                            <div>
                                <label class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">{{ __('tournament.formation') }}</label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <template x-for="f in formations" :key="f">
                                        <button @click="changeFormation(f)" type="button"
                                                :class="formation === f ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-primary'"
                                                class="px-2.5 py-1 rounded text-xs font-mono font-semibold transition" x-text="f">
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">{{ __('tournament.mentality') }}</label>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <template x-for="m in mentalities" :key="m">
                                        <button @click="mentality = m" type="button"
                                                :class="mentality === m ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-primary'"
                                                class="px-2.5 py-1 rounded text-xs font-semibold capitalize transition" x-text="m">
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Starting XI --}}
                        <div class="mb-4">
                            <h3 class="text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">{{ __('tournament.starting_xi') }}</h3>
                            <div class="grid grid-cols-1 gap-1">
                                <template x-for="(player, idx) in userLineup" :key="player.id">
                                    <div class="flex items-center gap-2 bg-surface-800 rounded-lg border border-border-primary px-3 py-2">
                                        <span class="text-xs font-mono text-text-muted w-5 text-right" x-text="player.number || idx + 1"></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                              :class="getPositionColor(player.position)"
                                              x-text="getPositionAbbr(player.position)"></span>
                                        <span class="flex-1 text-sm text-text-primary truncate" x-text="player.name"></span>
                                        <span class="text-xs font-bold tabular-nums" :class="getOvrColor(player.overallScore)" x-text="player.overallScore"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Bench --}}
                        <div class="mb-6">
                            <h3 class="text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">{{ __('tournament.bench') }}</h3>
                            <div class="grid grid-cols-1 gap-1">
                                <template x-for="(player, idx) in userBench" :key="player.id">
                                    <div class="flex items-center gap-2 bg-surface-800/50 rounded-lg border border-border-primary/50 px-3 py-2 cursor-pointer hover:bg-surface-700 transition"
                                         @click="swapPlayer(idx)">
                                        <span class="text-xs font-mono text-text-muted w-5 text-right" x-text="player.number || ''"></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                              :class="getPositionColor(player.position)"
                                              x-text="getPositionAbbr(player.position)"></span>
                                        <span class="flex-1 text-sm text-text-secondary truncate" x-text="player.name"></span>
                                        <span class="text-xs font-bold tabular-nums" :class="getOvrColor(player.overallScore)" x-text="player.overallScore"></span>
                                        <span class="text-[9px] text-accent-blue">{{ __('tournament.swap') }}</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- ============ MATCH EVENTS ============ --}}
                <template x-if="phase === 'playing' || phase === 'full_time' || phase === 'extra_time' || phase === 'penalties'">
                    <div>
                        {{-- Skip button --}}
                        <div class="flex justify-end mb-2" x-show="phase === 'playing'">
                            <button @click="skipReveal()" class="text-xs text-text-muted hover:text-text-primary transition">
                                {{ __('tournament.skip_to_result') }} &raquo;
                            </button>
                        </div>

                        {{-- Events feed --}}
                        <div class="space-y-1 mb-4 max-h-96 overflow-y-auto" id="tournament-events-feed">
                            <template x-for="(event, idx) in [...revealedEvents].reverse()" :key="idx">
                                <div class="flex items-center gap-3 py-2 px-3 rounded-lg"
                                     :class="isGoalEvent(event) ? 'bg-goal-highlight border-l-2 border-l-accent-gold' : 'border-l-2 border-l-transparent'"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0">
                                    <span class="text-xs font-heading font-bold text-text-muted w-8 text-right shrink-0"
                                          x-text="event.minute + '\''"></span>
                                    <span class="text-sm w-5 text-center shrink-0" x-text="getEventIcon(event.event_type)"></span>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm text-text-primary" x-text="event.player_name || ''"></span>
                                        <template x-if="event.event_type === 'goal' && event.assist_name">
                                            <span class="text-xs text-text-secondary ml-1" x-text="'(' + event.assist_name + ')'"></span>
                                        </template>
                                    </div>
                                    <span class="text-[10px] text-text-muted shrink-0"
                                          :class="getEventSide(event) === 'home' ? '' : 'text-right'"
                                          x-text="getEventSide(event) === 'home' ? homeTeam?.fifaCode : awayTeam?.fifaCode"></span>
                                </div>
                            </template>

                            <template x-if="revealedEvents.length === 0 && phase !== 'simulating'">
                                <div class="text-center text-text-muted text-sm py-4">
                                    {{ __('tournament.no_events_yet') }}
                                </div>
                            </template>
                        </div>

                        {{-- Penalty kicks display --}}
                        <template x-if="revealedPenaltyKicks.length > 0">
                            <div class="mb-4">
                                <div class="bg-purple-500/10 rounded-lg p-3">
                                    <div class="text-center mb-2">
                                        <span class="text-sm font-heading font-bold uppercase tracking-wider text-penalty-text">{{ __('game.live_penalties') }}</span>
                                    </div>
                                    <div class="space-y-1">
                                        <template x-for="(kick, idx) in revealedPenaltyKicks" :key="idx">
                                            <div class="flex items-center gap-2 text-sm"
                                                 x-transition:enter="transition ease-out duration-300"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100">
                                                <span class="w-5 text-right text-xs font-bold text-penalty-text" x-text="kick.round"></span>
                                                <span class="text-xs text-text-muted w-8"
                                                      x-text="kick.team === 'home' ? homeTeam?.fifaCode : awayTeam?.fifaCode"></span>
                                                <span class="flex-1 text-text-body truncate" x-text="kick.playerName"></span>
                                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                                      :class="kick.scored ? 'bg-accent-green/10 text-accent-green' : 'bg-red-500/10 text-accent-red'"
                                                      x-text="kick.scored ? '{{ __('tournament.pen_scored') }}' : '{{ __('tournament.pen_missed') }}'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    {{-- Skip penalties button --}}
                                    <div class="text-center mt-2" x-show="phase === 'penalties' && revealedPenaltyKicks.length < penaltyKicks.length">
                                        <button @click="skipPenaltyReveal()" class="text-xs text-penalty-text hover:underline">
                                            {{ __('tournament.skip_to_result') }} &raquo;
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>

                        {{-- MVP --}}
                        <template x-if="phase === 'full_time' && mvpPlayer">
                            <div class="flex items-center justify-center gap-2 text-sm mb-4 py-2 px-3 rounded-lg bg-accent-gold/10">
                                <span class="text-accent-gold text-base">&#9733;</span>
                                <span class="text-text-secondary">{{ __('game.mvp_of_the_match') }}</span>
                                <span class="font-semibold text-text-primary" x-text="mvpPlayer.name"></span>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ============ FIXED BOTTOM BAR ============ --}}
                <div class="fixed bottom-0 inset-x-0 z-30 bg-surface-800/95 backdrop-blur-md border-t border-border-primary px-4 py-3">
                    {{-- Pre-match: Kick Off --}}
                    <template x-if="phase === 'pre_match'">
                        <button @click="kickOff()"
                                class="w-full py-3 rounded-lg font-heading font-bold uppercase tracking-wider text-sm bg-accent-green text-white hover:bg-accent-green/90 transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M3 3.732a1.5 1.5 0 0 1 2.305-1.265l6.706 4.267a1.5 1.5 0 0 1 0 2.531l-6.706 4.268A1.5 1.5 0 0 1 3 12.267V3.732Z"/></svg>
                            {{ __('tournament.kick_off') }}
                        </button>
                    </template>

                    {{-- Simulating --}}
                    <template x-if="phase === 'simulating'">
                        <div class="flex items-center justify-center gap-2 py-3 text-text-secondary">
                            <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span class="text-sm font-medium">{{ __('tournament.simulating') }}...</span>
                        </div>
                    </template>

                    {{-- Extra Time prompt --}}
                    <template x-if="phase === 'extra_time' && !simulating">
                        <button @click="playExtraTime()"
                                class="w-full py-3 rounded-lg font-heading font-bold uppercase tracking-wider text-sm bg-accent-orange text-white hover:bg-accent-orange/90 transition flex items-center justify-center gap-2">
                            {{ __('tournament.play_extra_time') }}
                        </button>
                    </template>

                    {{-- Penalties prompt --}}
                    <template x-if="phase === 'penalties' && revealedPenaltyKicks.length === 0 && !simulating">
                        <button @click="playPenalties()"
                                class="w-full py-3 rounded-lg font-heading font-bold uppercase tracking-wider text-sm bg-purple-600 text-white hover:bg-purple-600/90 transition flex items-center justify-center gap-2">
                            {{ __('tournament.play_penalties') }}
                        </button>
                    </template>

                    {{-- Full Time: Result + Continue --}}
                    <template x-if="phase === 'full_time'">
                        <div>
                            <div class="flex items-center justify-center gap-3 mb-3">
                                <span class="font-heading text-lg font-extrabold tabular-nums text-text-primary"
                                      x-text="totalHomeScore + ' - ' + totalAwayScore"></span>
                                <span class="text-sm font-heading font-bold uppercase tracking-wider"
                                      :class="resultColorClass"
                                      x-text="resultLabel"></span>
                            </div>
                            <button @click="continueToHub()"
                                    :disabled="batchSimulating"
                                    class="w-full py-3 rounded-lg font-heading font-bold uppercase tracking-wider text-sm transition flex items-center justify-center gap-2"
                                    :class="batchSimulating ? 'bg-surface-700 text-text-muted cursor-wait' : 'bg-accent-blue text-white hover:bg-accent-blue/90'">
                                <template x-if="batchSimulating">
                                    <span class="flex items-center gap-2">
                                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span x-text="batchProgress"></span>
                                    </span>
                                </template>
                                <template x-if="!batchSimulating">
                                    <span class="flex items-center gap-2">
                                        {{ __('tournament.continue') }}
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </span>
                                </template>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
