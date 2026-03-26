<x-app-layout>
    <div class="max-w-lg mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold text-text-primary mb-6">🔑 Unirse a una Liga</h1>

        @if(session('error'))
            <div class="bg-red-500/20 text-red-400 p-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('league-room.join.store') }}" class="bg-surface-800 rounded-xl p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-text-secondary text-sm mb-1">Código de invitación</label>
                <input type="text" name="code" placeholder="GRANADA24"
                    maxlength="8"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2 border border-surface-600 focus:outline-none focus:border-accent-blue uppercase tracking-widest text-center text-xl"
                    value="{{ old('code') }}" required>
                @error('code')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit"
                class="w-full bg-accent-blue hover:bg-blue-600 text-white font-semibold py-2 rounded-lg transition">
                Unirse
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="{{ route('league-room.create') }}" class="text-accent-blue hover:underline text-sm">¿Prefieres crear tu propia liga?</a>
        </div>
    </div>
</x-app-layout>
