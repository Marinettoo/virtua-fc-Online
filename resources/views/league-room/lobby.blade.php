<x-app-layout>
    <div class="max-w-2xl mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold text-text-primary mb-2">🏟️ {{ $room->name }}</h1>
        <p class="text-text-secondary mb-6">Código de invitación: <span class="font-mono text-accent-blue text-lg tracking-widest">{{ $room->code }}</span></p>

        @if(session('success'))
            <div class="bg-green-500/20 text-green-400 p-3 rounded mb-4">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-500/20 text-red-400 p-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        <!-- Jugadores en la sala -->
        <div class="bg-surface-800 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-text-primary mb-4">👥 Jugadores ({{ $members->count() }})</h2>
            <div class="space-y-3">
                @foreach($members as $member)
                    <div class="flex items-center justify-between bg-surface-700 rounded-lg px-4 py-3">
                        <span class="text-text-primary font-medium">{{ $member->user->name }}</span>
                        @if($member->team_id)
                            <span class="text-green-400 text-sm">✅ Equipo elegido</span>
                        @else
                            <span class="text-yellow-400 text-sm">⏳ Sin equipo</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Elegir equipo -->
        @if(!$myMember->team_id)
        <div class="bg-surface-800 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-text-primary mb-4">⚽ Elige tu equipo</h2>
            <form method="POST" action="{{ route('league-room.choose-team', $room->id) }}">
                @csrf
                <select name="team_id"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2 border border-surface-600 mb-4">
                    <option value="">-- Selecciona un equipo --</option>
                    {{-- TODO: cargar equipos disponibles desde la base de datos --}}
                </select>
                <button type="submit"
                    class="w-full bg-accent-blue hover:bg-blue-600 text-white font-semibold py-2 rounded-lg transition">
                    Confirmar equipo
                </button>
            </form>
        </div>
        @endif

        <!-- Botón arrancar (solo el creador) -->
        @if(auth()->id() === $room->owner_id && $room->status === 'waiting')
        <form method="POST" action="{{ route('league-room.start', $room->id) }}">
            @csrf
            <button type="submit"
                class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-xl transition text-lg">
                🚀 ¡Arrancar Liga!
            </button>
        </form>
        @endif
    </div>
</x-app-layout>
