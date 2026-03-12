@props(['disabled' => false, 'readonly' => false])

<input @disabled($disabled) @readonly($readonly) {{ $attributes->merge(['class' => 'border-slate-300 focus:border-slate-400 focus:ring-slate-400 rounded-lg shadow-sm text-sm']) }}>
