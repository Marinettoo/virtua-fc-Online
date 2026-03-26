<x-app-layout>
    <div class="max-w-lg mx-auto px-4 mt-10 pb-12">
        <h1 class="font-heading text-2xl font-bold uppercase tracking-wide text-text-primary mb-6">🔑 Unirse a una Liga</h1>

        @if($errors->any())
            <div class="bg-red-500/20 text-red-400 p-3 rounded-lg mb-6 text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('online-league.join') }}" class="space-y-5">
            @csrf

            {{-- Código --}}
            <div>
                <label class="block text-text-muted text-xs uppercase font-semibold mb-2">Código de invitación</label>
                <input type="text" name="invite_code" value="{{ old('invite_code') }}"
                    placeholder="Ej: AB3X7K2M"
                    maxlength="8"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2.5 border border-surface-600
                           focus:border-accent-blue focus:outline-none transition font-mono text-xl tracking-widest uppercase"
                    required>
            </div>

            {{-- Tu equipo --}}
            <div>
                <label class="block text-text-muted text-xs uppercase font-semibold mb-2">Tu equipo (desde tus partidas)</label>
                <select name="team_id" id="team_id_join"
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
                <input type="hidden" name="game_id" id="game_id_join" value="{{ old('game_id') }}">
            </div>

            <button type="submit"
                class="w-full bg-accent-blue hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition text-base">
                ✅ Unirme a la liga
            </button>
        </form>
    </div>

    <script>
        document.getElementById('team_id_join').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('game_id_join').value = selected.dataset.gameId || '';
        });
    </script>
</x-app-layout>
