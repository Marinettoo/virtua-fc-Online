@props(['color' => 'slate', 'size' => 'default'])

@php
$colors = [
    'green' => 'bg-green-100 text-green-800',
    'amber' => 'bg-amber-100 text-amber-800',
    'red' => 'bg-red-100 text-red-700',
    'sky' => 'bg-sky-100 text-sky-800',
    'slate' => 'bg-slate-100 text-slate-700',
    'emerald' => 'bg-emerald-100 text-emerald-800',
    'blue' => 'bg-blue-100 text-blue-800',
    'purple' => 'bg-purple-100 text-purple-700',
    'indigo' => 'bg-indigo-100 text-indigo-700',
    'orange' => 'bg-orange-100 text-orange-700',
    'teal' => 'bg-teal-100 text-teal-700',
    'violet' => 'bg-violet-100 text-violet-700',
    'rose' => 'bg-rose-100 text-rose-700',
    'green-solid' => 'bg-green-600 text-white',
    'red-solid' => 'bg-red-600 text-white',
];
$colorClasses = $colors[$color] ?? $colors['slate'];

$sizeClasses = match($size) {
    'xs' => 'px-1.5 py-0.5 text-[10px]',
    default => 'px-2.5 py-0.5 text-xs',
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center {$sizeClasses} {$colorClasses} font-medium rounded-full"]) }}>
    {{ $slot }}
</span>
