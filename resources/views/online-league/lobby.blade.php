<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 pb-12 mt-8">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    🌐 {{ $league->name }}
                </h1>
                <p class="text-text-muted text-sm mt-1">
                    {{ $league->competition->name }} &middot;
                    @if($league->status === 'waiting')
                        <span class="text-yellow-400">⏳ Esperando jugadores</span>
                    @elseif($league->status === 'active')
                        <span class="text-green-400">▶ Jornada {{ $league->current_matchday }}</span>
                    @else
                        <span class="text-text-muted">🏁 Finalizada</span>
                    @endif
                </p>
            </div>

            {{-- Código de invitación --}}
            @if($league->status === 'waiting')
                <div class="bg-surface-800 rounded-xl px-5 py-3 text-center">
                    <p class="text-text-muted text-xs mb-1">Código de invitación</p>
                    <p class="font-mono text-accent-blue text-2xl tracking-widest font-bold">{{ $league->invite_code }}</p>
                </div>
            @endif
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="bg-green-500/20 text-green-400 p-3 rounded-lg mb-6">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-500/20 text-red-400 p-3 rounded-lg mb-6">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Columna izquierda: clasificación --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Clasificación (solo si hay partidos jugados) --}}
                @if($league->status !== 'waiting' && $standings->isNotEmpty())
                <div class="bg-surface-800 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border-strong">
                        <h2 class="font-semibold text-text-primary">📊 Clasificación</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-text-muted text-xs uppercase border-b border-border-strong">
                                <th class="px-4 py-2 text-left w-6">#</th>
                                <th class="px-4 py-2 text-left">Equipo</th>
                                <th class="px-2 py-2 text-center">PJ</th>
                                <th class="px-2 py-2 text-center">G</th>
                                <th class="px-2 py-2 text-center">E</th>
                                <th class="px-2 py-2 text-center">P</th>
                                <th class="px-2 py-2 text-center">GF</th>
                                <th class="px-2 py-2 text-center">GC</th>
                                <th class="px-2 py-2 text-center">DG</th>
                                <th class="px-3 py-2 text-center font-bold">PTS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($standings as $row)
                                @php
                                    $isMe = $mySlot && $mySlot->team_id === $row->team_id;
                                    $slot = $league->slots->firstWhere('team_id', $row->team_id);
                                @endphp
                                <tr class="border-b border-border-strong/50 {{ $isMe ? 'bg-accent-blue/10' : 'hover:bg-surface-700' }} transition">
                                    <td class="px-4 py-2.5 text-text-muted">{{ $row->position }}</td>
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('online-league.squad', [$league->id, $row->team_id]) }}"
                                           class="text-text-primary hover:text-accent-blue transition font-medium">
                                            {{ $row->team->name }}
                                        </a>
                                        @if($slot && $slot->user_id)
                                            <span class="ml-1.5 text-[10px] text-accent-blue bg-accent-blue/10 px-1.5 py-0.5 rounded font-medium">
                                                {{ $slot->user->name }}
                                            </span>
                                        @else
                                            <span class="ml-1.5 text-[10px] text-text-muted bg-surface-700 px-1.5 py-0.5 rounded">CPU</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2.5 text-center text-text-muted">{{ $row->played }}</td>
                                    <td class="px-2 py-2.5 text-center text-green-400">{{ $row->won }}</td>
                                    <td class="px-2 py-2.5 text-center text-text-muted">{{ $row->drawn }}</td>
                                    <td class="px-2 py-2.5 text-center text-red-400">{{ $row->lost }}</td>
                                    <td class="px-2 py-2.5 text-center text-text-body">{{ $row->goals_for }}</td>
                                    <td class="px-2 py-2.5 text-center text-text-body">{{ $row->goals_against }}</td>
                                    <td class="px-2 py-2.5 text-center text-text-body">
                                        {{ $row->goals_for - $row->goals_against > 0 ? '+' : '' }}{{ $row->goals_for - $row->goals_against }}
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-bold text-text-primary">{{ $row->points }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                {{-- Jornada actual --}}
                @if($league->status === 'active' && $currentMatchday->isNotEmpty())
                <div class="bg-surface-800 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border-strong flex items-center justify-between">
                        <h2 class="font-semibold text-text-primary">⚽ Jornada {{ $league->current_matchday }}</h2>
                        @php $pendingCpu = $currentMatchday->where('type', 'cpu_vs_cpu')->where('status', 'pending')->count(); @endphp
                        @if($pendingCpu > 0)
                            <form method="POST" action="{{ route('online-league.process-matchday', $league->id) }}">
                                @csrf
                                <button type="submit"
                                    class="text-xs bg-accent-blue hover:bg-blue-600 text-white font-semibold px-3 py-1.5 rounded-lg transition">
                                    ▶ Simular CPU ({{ $pendingCpu }})
                                </button>
                            </form>
                        @endif
                    </div>
                    <div class="divide-y divide-border-strong/50">
                        @foreach($currentMatchday as $match)
                            <div class="px-5 py-3 flex items-center justify-between text-sm">
                                <div class="flex items-center gap-2 w-5/12 justify-end">
                                    @php $homeSlot = $league->slots->firstWhere('team_id', $match->home_team_id); @endphp
                                    <span class="text-text-primary font-medium">{{ $match->homeTeam->name }}</span>
                                    @if($homeSlot && $homeSlot->user_id)
                                        <span class="text-[10px] text-accent-blue">👤</span>
                                    @endif
                                </div>
                                <div class="w-2/12 text-center">
                                    @if($match->status === 'played')
                                        <span class="font-bold text-text-primary">{{ $match->home_score }} - {{ $match->away_score }}</span>
                                    @else
                                        <span class="text-text-muted text-xs">VS</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 w-5/12">
                                    @php $awaySlot = $league->slots->firstWhere('team_id', $match->away_team_id); @endphp
                                    @if($awaySlot && $awaySlot->user_id)
                                        <span class="text-[10px] text-accent-blue">👤</span>
                                    @endif
                                    <span class="text-text-primary font-medium">{{ $match->awayTeam->name }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

            {{-- Columna derecha: managers + acciones --}}
            <div class="space-y-6">

                {{-- Lista de managers --}}
                <div class="bg-surface-800 rounded-xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border-strong">
                        <h2 class="font-semibold text-text-primary">👥 Managers ({{ $slots->count() }})</h2>
                    </div>
                    <div class="divide-y divide-border-strong/50">
                        @foreach($slots as $slot)
                            <div class="px-4 py-3 flex items-center justify-between">
                                <div>
                                    <a href="{{ route('online-league.squad', [$league->id, $slot->team_id]) }}"
                                       class="text-text-primary hover:text-accent-blue transition text-sm font-medium">
                                        {{ $slot->team->name }}
                                    </a>
                                    @if($slot->user_id)
                                        <p class="text-text-muted text-xs">{{ $slot->user->name }}</p>
                                    @else
                                        <p class="text-text-muted text-xs">CPU</p>
                                    @endif
                                </div>
                                @if($slot->user_id && $league->owner_user_id === $slot->user_id)
                                    <span class="text-[10px] bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded font-medium">Owner</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Botón arrancar (solo owner en estado waiting) --}}
                @if($isOwner && $league->status === 'waiting')
                    <form method="POST" action="{{ route('online-league.start', $league->id) }}">
                        @csrf
                        <button type="submit"
                            class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-3 rounded-xl transition text-base">
                            🚀 ¡Arrancar Temporada!
                        </button>
                    </form>
                @endif

                {{-- Estado si no es owner y está esperando --}}
                @if(!$isOwner && $league->status === 'waiting')
                    <div class="bg-surface-800 rounded-xl px-5 py-4 text-center text-text-muted text-sm">
                        ⏳ Esperando que el creador arranque la liga...
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
