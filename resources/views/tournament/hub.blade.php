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
                <a href="/dashboard" class="inline-block mt-4 text-accent-blue hover:underline">
                    {{ __('app.back_to_dashboard') }}
                </a>
            </div>
        </template>

        {{-- Main content --}}
        <template x-if="!loading && !error && tournament">
            <div>
                {{-- Header --}}
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-text-primary" x-text="tournament.name"></h1>
                        <p class="text-text-secondary text-sm mt-1">
                            <span x-text="currentPhase === 'group_stage' ? '{{ __('tournament.group_stage') }}' : '{{ __('tournament.knockout_stage') }}'"></span>
                            <span x-show="isCompleted" class="ml-2 text-accent-green font-medium">{{ __('tournament.completed') }}</span>
                        </p>
                    </div>

                    {{-- Next match button --}}
                    <template x-if="nextUserMatch && !isCompleted">
                        <a :href="getMatchUrl(nextUserMatch.id)"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-accent-blue text-white rounded-lg hover:bg-accent-blue/80 transition text-sm font-medium">
                            {{ __('tournament.play_next_match') }}
                        </a>
                    </template>
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
                    <button @click="activeTab = 'stats'"
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
                                    <th class="text-left px-3 py-2">#</th>
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
                                            'bg-accent-green/5': idx < 2,
                                            'bg-accent-blue/5': idx === 2,
                                            'font-semibold': isUserTeam(standing.teamId)
                                        }"
                                        class="border-b border-border-primary last:border-0">
                                        <td class="px-3 py-2 text-text-secondary" x-text="idx + 1"></td>
                                        <td class="px-3 py-2">
                                            <span x-text="teamName(standing.teamId)"
                                                  :class="isUserTeam(standing.teamId) ? 'text-accent-blue' : 'text-text-primary'"></span>
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
                                        <span class="text-text-secondary text-xs" x-text="match.scheduledDate"></span>
                                    </template>
                                </div>

                                <div class="flex-1 text-left text-sm truncate"
                                     :class="isUserTeam(match.awayTeamId) ? 'text-accent-blue font-semibold' : 'text-text-primary'"
                                     x-text="teamName(match.awayTeamId)"></div>

                                <template x-if="!match.played && (isUserTeam(match.homeTeamId) || isUserTeam(match.awayTeamId))">
                                    <a :href="getMatchUrl(match.id)"
                                       class="text-xs bg-accent-blue/10 text-accent-blue px-2 py-1 rounded hover:bg-accent-blue/20 transition">
                                        {{ __('tournament.play') }}
                                    </a>
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
                            <h3 class="text-sm font-semibold text-text-secondary uppercase tracking-wide mb-3" x-text="round.name"></h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <template x-for="tie in round.ties" :key="tie.id">
                                    <div class="bg-surface-800 rounded-lg border border-border-primary p-3">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex-1 text-right text-sm truncate"
                                                 :class="{'text-accent-blue font-semibold': isUserTeam(tie.homeTeamId), 'text-accent-green font-semibold': tie.winnerId === tie.homeTeamId}"
                                                 x-text="teamName(tie.homeTeamId)"></div>
                                            <div class="text-xs text-text-secondary px-2"
                                                 x-text="tie.completed ? 'vs' : 'vs'"></div>
                                            <div class="flex-1 text-left text-sm truncate"
                                                 :class="{'text-accent-blue font-semibold': isUserTeam(tie.awayTeamId), 'text-accent-green font-semibold': tie.winnerId === tie.awayTeamId}"
                                                 x-text="teamName(tie.awayTeamId)"></div>
                                        </div>
                                        <template x-if="tie.completed && tie.resolution">
                                            <div class="text-center mt-1">
                                                <span class="text-xs text-text-secondary"
                                                      x-text="tie.resolution.type === 'penalties' ? '{{ __('tournament.on_penalties') }}' : tie.resolution.type === 'extra_time' ? '{{ __('tournament.after_extra_time') }}' : ''"></span>
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
                    <div class="bg-surface-800 rounded-lg border border-border-primary p-6 text-center text-text-secondary">
                        <p>{{ __('tournament.stats_coming_soon') }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>
