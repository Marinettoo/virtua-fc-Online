<x-app-layout>
    <div class="max-w-lg mx-auto px-4 mt-10 pb-12">
        <h1 class="font-heading text-2xl font-bold uppercase tracking-wide text-text-primary mb-6">🌐 Nueva Liga Online</h1>

        @if($errors->any())
            <div class="bg-red-500/20 text-red-400 p-3 rounded-lg mb-6 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('online-league.create') }}" class="space-y-5">
            @csrf

            {{-- Nombre --}}
            <div>
                <label class="block text-text-muted text-xs uppercase font-semibold mb-2">Nombre de la liga</label>
                <input type="text" name="name" value="{{ old('name') }}"
                    placeholder="Ej: La Liga de los Cracks"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2.5 border border-surface-600
                           focus:border-accent-blue focus:outline-none transition"
                    required maxlength="60">
            </div>

            {{-- Competición --}}
            <div>
                <label class="block text-text-muted text-xs uppercase font-semibold mb-2">Competición</label>
                <select name="competition_id"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2.5 border border-surface-600
                           focus:border-accent-blue focus:outline-none transition">
                    <option value="">-- Elige una competición --</option>
                    @foreach($competitions->groupBy('country') as $country => $comps)
                        <optgroup label="{{ $country }}">
                            @foreach($comps as $comp)
                                <option value="{{ $comp->id }}" {{ old('competition_id') == $comp->id ? 'selected' : '' }}>
                                    {{ $comp->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            {{-- Tu equipo (filtra partidas activas del usuario) --}}
            <div>
                <label class="block text-text-muted text-xs uppercase font-semibold mb-2">Tu equipo (desde tus partidas)</label>
                <select name="team_id" id="team_id"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2.5 border border-surface-600
                           focus:border-accent-blue focus:outline-none transition">
                    <option value="">-- Elige tu equipo --</option>
                    @foreach($myGames as $game)
                        <option value="{{ $game->team_id }}"
                                data-game-id="{{ $game->id }}"
                                {{ old('team_id') == $game->team_id ? 'selected' : '' }}>
                            {{ $game->team->name }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="game_id" id="game_id" value="{{ old('game_id') }}">
            </div>

            <button type="submit"
                class="w-full bg-accent-blue hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition text-base">
                🚀 Crear liga
            </button>
        </form>
    </div>

    {{-- Script para auto-rellenar game_id al elegir equipo --}}
    <script>
        document.getElementById('team_id').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('game_id').value = selected.dataset.gameId || '';
        });
    </script>
</x-app-layout>
