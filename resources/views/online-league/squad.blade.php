<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-12 mt-8">

        {{-- Breadcrumb --}}
        <div class="mb-6">
            <a href="{{ route('online-league.lobby', $league->id) }}"
               class="text-text-muted hover:text-accent-blue text-sm transition">
                ← {{ $league->name }}
            </a>
        </div>

        {{-- Header del equipo --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    {{ $slot->team->name }}
                </h1>
                <p class="text-text-muted text-sm mt-1">
                    @if($slot->user_id)
                        👤 {{ $slot->user->name }}
                    @else
                        🤖 CPU
                    @endif
                    @if($standing)
                        &middot; {{ $standing->points }} pts &middot; {{ $standing->position }}º
                    @endif
                </p>
            </div>
        </div>

        {{-- Estadísticas del equipo en la liga --}}
        @if($standing)
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-8">
            @foreach([
                ['label' => 'PJ', 'value' => $standing->played],
                ['label' => 'Victorias', 'value' => $standing->won, 'color' => 'text-green-400'],
                ['label' => 'Empates', 'value' => $standing->drawn],
                ['label' => 'Derrotas', 'value' => $standing->lost, 'color' => 'text-red-400'],
                ['label' => 'Puntos', 'value' => $standing->points, 'color' => 'text-accent-blue', 'bold' => true],
            ] as $stat)
                <div class="bg-surface-800 rounded-xl px-4 py-3 text-center">
                    <p class="text-text-muted text-xs mb-1">{{ $stat['label'] }}</p>
                    <p class="text-xl font-bold {{ $stat['color'] ?? 'text-text-primary' }} {{ isset($stat['bold']) ? 'font-extrabold' : '' }}">
                        {{ $stat['value'] }}
                    </p>
                </div>
            @endforeach
        </div>
        @endif

        {{-- Tabla de goleadores / stats de jugadores --}}
        @if($stats->isNotEmpty())
        <div class="bg-surface-800 rounded-xl overflow-hidden">
            <div class="px-5 py-4 border-b border-border-strong">
                <h2 class="font-semibold text-text-primary">⚽ Estadísticas de jugadores</h2>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-text-muted text-xs uppercase border-b border-border-strong">
                        <th class="px-4 py-2 text-left">Jugador</th>
                        <th class="px-3 py-2 text-left">Pos</th>
                        <th class="px-3 py-2 text-center">PJ</th>
                        <th class="px-3 py-2 text-center">⚽</th>
                        <th class="px-3 py-2 text-center">🅰️</th>
                        <th class="px-3 py-2 text-center">🟨</th>
                        <th class="px-3 py-2 text-center">🟥</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stats as $s)
                        <tr class="border-b border-border-strong/50 hover:bg-surface-700 transition">
                            <td class="px-4 py-2.5 text-text-primary font-medium">
                                {{ $s->gamePlayer->playerTemplate->name ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 text-text-muted text-xs">
                                {{ $s->gamePlayer->playerTemplate->position ?? '—' }}
                            </td>
                            <td class="px-3 py-2.5 text-center text-text-muted">{{ $s->appearances }}</td>
                            <td class="px-3 py-2.5 text-center font-semibold {{ $s->goals > 0 ? 'text-green-400' : 'text-text-muted' }}">{{ $s->goals }}</td>
                            <td class="px-3 py-2.5 text-center font-semibold {{ $s->assists > 0 ? 'text-blue-400' : 'text-text-muted' }}">{{ $s->assists }}</td>
                            <td class="px-3 py-2.5 text-center {{ $s->yellow_cards > 0 ? 'text-yellow-400' : 'text-text-muted' }}">{{ $s->yellow_cards }}</td>
                            <td class="px-3 py-2.5 text-center {{ $s->red_cards > 0 ? 'text-red-400' : 'text-text-muted' }}">{{ $s->red_cards }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div class="bg-surface-800 rounded-xl px-5 py-8 text-center text-text-muted text-sm">
                Aún no hay partidos jugados con este equipo.
            </div>
        @endif

    </div>
</x-app-layout>
