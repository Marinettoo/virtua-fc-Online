<x-app-layout>
    <div class="max-w-lg mx-auto mt-10 px-4">
        <h1 class="text-2xl font-bold text-text-primary mb-6">🏆 Crear Liga Online</h1>

        @if(session('error'))
            <div class="bg-red-500/20 text-red-400 p-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('league-room.store') }}" class="bg-surface-800 rounded-xl p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-text-secondary text-sm mb-1">Nombre de la liga</label>
                <input type="text" name="name" placeholder="Liga de los colegas"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2 border border-surface-600 focus:outline-none focus:border-accent-blue"
                    value="{{ old('name') }}" required>
                @error('name')<p class="text-red-400 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-text-secondary text-sm mb-1">Auto-avance (horas sin actividad)</label>
                <select name="auto_advance_hours"
                    class="w-full bg-surface-700 text-text-primary rounded-lg px-4 py-2 border border-surface-600 focus:outline-none focus:border-accent-blue">
                    <option value="12">12 horas</option>
                    <option value="24" selected>24 horas (recomendado)</option>
                    <option value="48">48 horas</option>
                    <option value="72">72 horas</option>
                </select>
            </div>

            <button type="submit"
                class="w-full bg-accent-blue hover:bg-blue-600 text-white font-semibold py-2 rounded-lg transition">
                Crear Liga
            </button>
        </form>

        <div class="mt-4 text-center">
            <a href="{{ route('league-room.join') }}" class="text-accent-blue hover:underline text-sm">¿Tienes un código? Únete a una liga</a>
        </div>
    </div>
</x-app-layout>
