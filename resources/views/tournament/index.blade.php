<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-6"
         x-data="tournamentIndex()"
         x-init="init()">

        {{-- Header --}}
        <div class="mb-8 text-center">
            <h1 class="font-heading text-3xl md:text-4xl font-extrabold uppercase tracking-wide text-text-primary">
                {{ __('game.wc2026_name') }}
            </h1>
            <p class="text-text-secondary mt-2 text-sm md:text-base">
                {{ __('tournament.index_subtitle') }}
            </p>
        </div>

        {{-- Resume existing tournament --}}
        <template x-if="existingTournaments.length > 0">
            <div class="mb-8">
                <h2 class="text-sm font-semibold text-text-secondary uppercase tracking-wider mb-3">{{ __('tournament.resume_tournament') }}</h2>
                <div class="space-y-2">
                    <template x-for="t in existingTournaments" :key="t.id">
                        <a :href="'/tournament/' + t.id"
                           class="flex items-center justify-between gap-3 bg-surface-800 border border-border-primary rounded-lg p-4 hover:bg-surface-700 transition">
                            <div>
                                <span class="text-text-primary font-medium" x-text="t.name"></span>
                                <span class="text-text-secondary text-xs ml-2"
                                      x-text="t.currentPhase === 'group_stage' ? '{{ __('tournament.group_stage') }}' : '{{ __('tournament.knockout_stage') }}'"></span>
                            </div>
                            <span class="text-accent-blue text-sm font-medium">{{ __('tournament.continue') }} &rarr;</span>
                        </a>
                    </template>
                </div>
            </div>
        </template>

        {{-- Error --}}
        <template x-if="error">
            <div class="mb-4 bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-center">
                <p class="text-red-400 text-sm" x-text="error"></p>
            </div>
        </template>

        {{-- Team picker --}}
        <div class="mb-6">
            <h2 class="text-sm font-semibold text-text-secondary uppercase tracking-wider mb-3">{{ __('tournament.pick_your_team') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2">
                @foreach($teams as $team)
                    <label class="flex flex-col items-center gap-2 rounded-lg border border-border-primary p-3 cursor-pointer transition-all
                                   hover:bg-accent-gold/5 hover:border-accent-gold/30
                                   has-checked:ring-2 has-checked:ring-accent-gold has-checked:border-accent-gold/30 has-checked:bg-accent-gold/5">
                        <x-team-crest :team="$team" class="w-12 h-12" />
                        <span class="text-xs font-medium text-text-body text-center truncate w-full">{{ $team->name }}</span>
                        <input type="radio" name="team_id" value="{{ $team->id }}" class="hidden"
                               x-model="selectedTeamId">
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Start button --}}
        <div class="text-center">
            <button @click="startTournament()"
                    :disabled="!selectedTeamId || creating"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-lg font-heading font-bold uppercase tracking-wider text-sm transition-all duration-200"
                    :class="selectedTeamId && !creating
                        ? 'bg-accent-gold text-surface-900 hover:bg-accent-gold/90 cursor-pointer'
                        : 'bg-surface-700 text-text-muted cursor-not-allowed'">
                <template x-if="creating">
                    <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="creating ? '{{ __('tournament.creating') }}' : '{{ __('tournament.start_tournament') }}'"></span>
            </button>
        </div>
    </div>
</x-app-layout>
