<x-app-layout>
    <div class="max-w-2xl mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold text-text-primary mb-2">⚽ {{ $room->name }}</h1>
        <p class="text-text-secondary mb-6">Jornada actual: <span class="text-accent-blue font-bold">{{ $currentMatchday?->matchday_number ?? '-' }}</span></p>

        @if(session('success'))
            <div class="bg-green-500/20 text-green-400 p-3 rounded mb-4">{{ session('success') }}</div>
        @endif

        <!-- Estado de jugadores -->
        <div class="bg-surface-800 rounded-xl p-6 mb-6">
            <h2 class="text-lg font-semibold text-text-primary mb-4">👥 Estado de la jornada</h2>
            <div class="space-y-3">
                @foreach($members as $member)
                    <div class="flex items-center justify-between bg-surface-700 rounded-lg px-4 py-3">
                        <span class="text-text-primary font-medium">{{ $member->user->name }}</span>
                        @if($member->is_ready)
                            <span class="text-green-400 text-sm">✅ Listo para jugar</span>
                        @else
                            <span class="text-yellow-400 text-sm">⏳ Esperando...</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Auto-avance countdown -->
        @if($currentMatchday?->auto_advance_at)
        <div class="bg-surface-800 rounded-xl p-4 mb-6 text-center">
            <p class="text-text-secondary text-sm">⏱️ Auto-avance en: <span class="text-accent-blue font-mono">{{ $currentMatchday->auto_advance_at->diffForHumans() }}</span></p>
        </div>
        @endif

        <!-- Botón Jugar Jornada -->
        @if(!$myMember->is_ready && $currentMatchday?->isPending())
        <form method="POST" action="{{ route('league-room.ready', $room->id) }}">
            @csrf
            <button type="submit"
                class="w-full bg-accent-blue hover:bg-blue-600 text-white font-bold py-3 rounded-xl transition text-lg">
                ▶️ Jugar Jornada {{ $currentMatchday->matchday_number }}
            </button>
        </form>
        @elseif($myMember->is_ready)
        <div class="w-full bg-green-600/30 text-green-400 font-bold py-3 rounded-xl text-center text-lg">
            ✅ Ya has pulsado jugar. Esperando a tus rivales...
        </div>
        @endif
    </div>
</x-app-layout>
