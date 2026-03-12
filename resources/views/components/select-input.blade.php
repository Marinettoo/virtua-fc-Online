@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg shadow-sm text-sm']) }}>
    {{ $slot }}
</select>
