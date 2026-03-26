<x-app-layout>
    <div class="max-w-6xl mx-auto px-4 py-6"
         x-data="tournamentHub({ tournamentId: @js($tournamentId) })">

        {{-- Loading state --}}
        <template x-if="loading">
            <div class="flex items-center justify-center py-20">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-accent-blue"></div>
            </div>
        </template>

        {{-- Error state --}}
        <template x-if="error && !loading">
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-6 text-center">
                <p class="text-red-400" x-text="error"></p>
                <a href="/tournament" class="inline-block mt-4 text-accent-blue hover:underline">
                    {{ __('tournament.back_to_start') }}
                </a>
            </div>
        </template>

        {{-- Main content --}}
        <template x-if="!loading && !error && tournament">
            <div>
                {{-- Header --}}
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <a href="/tournament" class="text-text-muted hover:text-text-secondary transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </a>
                            <h1 class="text-xl md:text-2xl font-heading font-bold uppercase tracking-wide text-text-primary" x-text="tournament.name"></h1>
                        </div>
                        <p class="text-text-secondary text-sm mt-1 ml-7">
                            <span x-text="currentPhase === 'group_stage' ? '{{ __('tournament.group_stage') }}' : '{{ __('tournament.knockout_stage') }}'"></span>
                            <span x-show="isCompleted" class="ml-2 text-accent-green font-medium">{{ __('tournament.completed') }}</span>
                        </p>
                    </div>

                    <div class="flex items-center gap-2 ml-7 md:ml-0">
                        {{-- Next match button --}}
                        <template x-if="nextUserMatch && !isCompleted">
                            <a :href="getMatchUrl(nextUserMatch.id)"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-accent-blue text-white rounded-lg hover:bg-accent-blue/80 transition text-sm font-medium">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M3 3.732a1.5 1.5 0 0 1 2.305-1.265l6.706 4.267a1.5 1.5 0 0 1 0 2.531l-6.706 4.268A1.5 1.5 0 0 1 3 12.267V3.732Z"/></svg>
                                {{ __('tournament.play_next_match') }}
                            </a>
                        </template>

                        {{-- Delete tournament --}}
                        <button @click="deleteTournament()" class="text-text-muted hover:text-accent-red transition p-2" title="{{ __('app.delete') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Tab navigation --}}
                <div class="flex gap-1 mb-6 border-b border-border-primary">
                    <button @click="activeTab = 'groups'"
                            :class="activeTab === 'groups' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-secondary hover:text-text-primary'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">
                        {{ __('tournament.groups') }}
                    </button>
                    <button @click="activeTab = 'knockout'"
                            :class="activeTab === 'knockout' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-secondary hover:text-text-primary'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">
                        {{ __('tournament.knockout') }}
                    </button>
                    <button @click="activeTab = 'stats'; loadStats()"
                            :class="activeTab === 'stats' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-secondary hover:text-text-primary'"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition">
                        {{ __('tournament.stats') }}
                    </button>
                </div>

                {{-- Groups tab --}}
                <div x-show="activeTab === 'groups'" x-cloak>
                    {{-- Group selector --}}
                    <div class="flex flex-wrap gap-1 mb-4">
                        <template x-for="letter in groupLetters" :key="letter">
                            <button @click="selectedGroup = letter"
                                    :class="selectedGroup === letter ? 'bg-accent-blue text-white' : 'bg-surface-700 text-text-secondary hover:text-text-primary'"
                                    class="w-8 h-8 rounded text-xs font-bold transition" x-text="letter">
                            </button>
                        </template>
                    </div>

                    {{-- Group table --}}
                    <div class="bg-surface-800 rounded-lg border border-border-primary overflow-x-auto mb-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-border-primary text-text-secondary">
                                    <th class="text-left px-3 py-2 w-8">#</th>
                                    <th class="text-left px-3 py-2">{{ __('tournament.team') }}</th>
                                    <th class="text-center px-2 py-2">{{ __('tournament.played_short') }}</th>
                                    <th class="text-center px-2 py-2">{{ __('tournament.won_short') }}</th>
                                    <th class="text-center px-2 py-2">{{ __('tournament.drawn_short') }}</th>
                                    <th class="text-center px-2 py-2">{{ __('tournament.lost_short') }}</th>
                                    <th class="text-center px-2 py-2 hidden md:table-cell">{{ __('tournament.gf_short') }}</th>
                                    <th class="text-center px-2 py-2 hidden md:table-cell">{{ __('tournament.ga_short') }}</th>
                                    <th class="text-center px-2 py-2">{{ __('tournament.gd_short') }}</th>
                                    <th class="text-center px-2 py-2 font-bold">{{ __('tournament.pts_short') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(standing, idx) in selectedGroupStandings" :key="standing.teamId">
                                    <tr :class="{
                                            'bg-accent-green/5 border-l-2 border-l-accent-green': idx < 2,
                                            'bg-accent-blue/5 border-l-2 border-l-accent-blue': idx === 2,
                                            'border-l-2 border-l-transparent': idx > 2,
                                            'font-semibold': isUserTeam(standing.teamId)
                                        }"
                                        class="border-b border-border-primary last:border-0">
                                        <td class="px-3 py-2 text-text-secondary" x-text="idx + 1"></td>
                                        <td class="px-3 py-2">
                                            <span x-text="teamName(standing.teamId)"
                                                  :class="isUserTeam(standing.teamId) ? 'text-accent-blue' : 'text-text-primary'"></span>
                                            <span class="text-text-muted text-xs ml-1" x-text="teamCode(standing.teamId)"></span>
                                        </td>
                                        <td class="text-center px-2 py-2" x-text="standing.played"></td>
                                        <td class="text-center px-2 py-2" x-text="standing.won"></td>
                                        <td class="text-center px-2 py-2" x-text="standing.drawn"></td>
                                        <td class="text-center px-2 py-2" x-text="standing.lost"></td>
                                        <td class="text-center px-2 py-2 hidden md:table-cell" x-text="standing.goalsFor"></td>
                                        <td class="text-center px-2 py-2 hidden md:table-cell" x-text="standing.goalsAgainst"></td>
                                        <td class="text-center px-2 py-2" x-text="standing.goalDifference"></td>
                                        <td class="text-center px-2 py-2 font-bold" x-text="standing.points"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Group matches --}}
                    <div class="space-y-2">
                        <template x-for="match in selectedGroupMatches" :key="match.id">
                            <div class="bg-surface-800 rounded-lg border border-border-primary p-3 flex items-center justify-between gap-2">
                                <div class="flex-1 text-right text-sm truncate"
                                     :class="isUserTeam(match.homeTeamId) ? 'text-accent-blue font-semibold' : 'text-text-primary'"
                                     x-text="teamName(match.homeTeamId)"></div>

                                <div class="flex-shrink-0 px-3 min-w-[60px] text-center">
                                    <template x-if="match.played">
                                        <span class="font-mono font-bold text-text-primary"
                                              x-text="`${match.homeScore} - ${match.awayScore}`"></span>
                                    </template>
                                    <template x-if="!match.played">
                                        <span class="text-text-muted text-xs">vs</span>
                                    </template>
                                </div>

                                <div class="flex-1 text-left text-sm truncate"
                                     :class="isUserTeam(match.awayTeamId) ? 'text-accent-blue font-semibold' : 'text-text-primary'"
                                     x-text="teamName(match.awayTeamId)"></div>

                                <template x-if="!match.played && (isUserTeam(match.homeTeamId) || isUserTeam(match.awayTeamId))">
                                    <a :href="getMatchUrl(match.id)"
                                       class="text-xs bg-accent-blue/10 text-accent-blue px-2.5 py-1 rounded hover:bg-accent-blue/20 transition font-medium">
                                        {{ __('tournament.play') }}
                                    </a>
                                </template>

                                <template x-if="!match.played && !isUserTeam(match.homeTeamId) && !isUserTeam(match.awayTeamId)">
                                    <span class="text-[10px] text-text-muted px-2">R<span x-text="match.roundNumber"></span></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Knockout tab --}}
                <div x-show="activeTab === 'knockout'" x-cloak>
                    <template x-if="allTies.length === 0">
                        <div class="text-center py-12 text-text-secondary">
                            <p>{{ __('tournament.knockout_not_started') }}</p>
                        </div>
                    </template>

                    <template x-for="round in knockoutRounds" :key="round.round">
                        <div class="mb-8">
                            <h3 class="text-sm font-heading font-bold text-text-secondary uppercase tracking-wider mb-3" x-text="round.name"></h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="tie in round.ties" :key="tie.id">
                                    <div class="bg-surface-800 rounded-lg border border-border-primary p-3"
                                         :class="(isUserTeam(tie.homeTeamId) || isUserTeam(tie.awayTeamId)) ? 'ring-1 ring-accent-blue/30' : ''">
                                        <div class="flex items-center gap-2">
                                            {{-- Home team --}}
                                            <div class="flex-1 text-right text-sm truncate"
                                                 :class="{
                                                     'text-accent-blue font-semibold': isUserTeam(tie.homeTeamId),
                                                     'text-accent-green font-semibold': tie.completed && tie.winnerId === tie.homeTeamId && !isUserTeam(tie.homeTeamId),
                                                     'text-text-primary': !tie.completed && !isUserTeam(tie.homeTeamId),
                                                     'text-text-muted': tie.completed && tie.winnerId !== tie.homeTeamId && !isUserTeam(tie.homeTeamId),
                                                 }"
                                                 x-text="teamName(tie.homeTeamId)"></div>

                                            {{-- Score / vs --}}
                                            <div class="flex-shrink-0 px-2 min-w-[50px] text-center">
                                                <template x-if="getTieScore(tie)">
                                                    <span class="font-mono text-xs font-bold text-text-primary" x-text="getTieScore(tie)"></span>
                                                </template>
                                                <template x-if="!getTieScore(tie)">
                                                    <span class="text-text-muted text-xs">vs</span>
                                                </template>
                                            </div>

                                            {{-- Away team --}}
                                            <div class="flex-1 text-left text-sm truncate"
                                                 :class="{
                                                     'text-accent-blue font-semibold': isUserTeam(tie.awayTeamId),
                                                     'text-accent-green font-semibold': tie.completed && tie.winnerId === tie.awayTeamId && !isUserTeam(tie.awayTeamId),
                                                     'text-text-primary': !tie.completed && !isUserTeam(tie.awayTeamId),
                                                     'text-text-muted': tie.completed && tie.winnerId !== tie.awayTeamId && !isUserTeam(tie.awayTeamId),
                                                 }"
                                                 x-text="teamName(tie.awayTeamId)"></div>
                                        </div>

                                        {{-- Resolution badge --}}
                                        <template x-if="tie.completed && tie.resolution && tie.resolution.type !== 'regular'">
                                            <div class="text-center mt-1">
                                                <span class="text-[10px] px-1.5 py-0.5 rounded"
                                                      :class="tie.resolution.type === 'penalties' ? 'bg-purple-500/10 text-penalty-text' : 'bg-accent-orange/10 text-accent-orange'"
                                                      x-text="tie.resolution.type === 'penalties' ? '{{ __('tournament.on_penalties') }}' : '{{ __('tournament.after_extra_time') }}'"></span>
                                            </div>
                                        </template>

                                        {{-- Play button for user's unplayed tie --}}
                                        <template x-if="!tie.completed && (isUserTeam(tie.homeTeamId) || isUserTeam(tie.awayTeamId))">
                                            <div class="text-center mt-2">
                                                <a :href="getMatchUrl(getTieMatch(tie)?.id)"
                                                   class="text-xs bg-accent-blue/10 text-accent-blue px-3 py-1 rounded hover:bg-accent-blue/20 transition font-medium"
                                                   x-show="getTieMatch(tie)">
                                                    {{ __('tournament.play') }}
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Stats tab --}}
                <div x-show="activeTab === 'stats'" x-cloak>
                    <template x-if="!statsLoaded">
                        <div class="flex items-center justify-center py-12">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-accent-blue"></div>
                        </div>
                    </template>

                    <template x-if="statsLoaded">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Top Scorers --}}
                            <div>
                                <h3 class="text-sm font-heading font-bold text-text-secondary uppercase tracking-wider mb-3">{{ __('tournament.top_scorers') }}</h3>
                                <div class="bg-surface-800 rounded-lg border border-border-primary overflow-hidden">
                                    <template x-if="topScorers.length === 0">
                                        <div class="p-4 text-center text-text-muted text-sm">{{ __('tournament.no_goals_yet') }}</div>
                                    </template>
                                    <template x-for="(s, idx) in topScorers" :key="s.playerId">
                                        <div class="flex items-center gap-2 px-3 py-2 border-b border-border-primary last:border-0"
                                             :class="isUserTeam(s.teamId) ? 'bg-accent-blue/5' : ''">
                                            <span class="text-xs text-text-muted w-5 text-right" x-text="idx + 1"></span>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-text-primary truncate block" x-text="s.playerName"></span>
                                                <span class="text-[10px] text-text-muted" x-text="s.teamCode"></span>
                                            </div>
                                            <span class="text-sm font-bold text-accent-gold tabular-nums" x-text="s.goals"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Top Assists --}}
                            <div>
                                <h3 class="text-sm font-heading font-bold text-text-secondary uppercase tracking-wider mb-3">{{ __('tournament.top_assists') }}</h3>
                                <div class="bg-surface-800 rounded-lg border border-border-primary overflow-hidden">
                                    <template x-if="topAssists.length === 0">
                                        <div class="p-4 text-center text-text-muted text-sm">{{ __('tournament.no_assists_yet') }}</div>
                                    </template>
                                    <template x-for="(s, idx) in topAssists" :key="s.playerId">
                                        <div class="flex items-center gap-2 px-3 py-2 border-b border-border-primary last:border-0"
                                             :class="isUserTeam(s.teamId) ? 'bg-accent-blue/5' : ''">
                                            <span class="text-xs text-text-muted w-5 text-right" x-text="idx + 1"></span>
                                            <div class="flex-1 min-w-0">
                                                <span class="text-sm text-text-primary truncate block" x-text="s.playerName"></span>
                                                <span class="text-[10px] text-text-muted" x-text="s.teamCode"></span>
                                            </div>
                                            <span class="text-sm font-bold text-accent-blue tabular-nums" x-text="s.assists"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
