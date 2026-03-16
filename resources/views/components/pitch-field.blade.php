{{-- Reusable pitch field with markings. Accepts a slot for player badges/overlays. --}}
@props(['class' => '', 'id' => null, 'fieldId' => null])

<div @if($id) id="{{ $id }}" @endif
     class="pitch {{ $class }} relative"
     {{ $attributes->except(['class', 'id', 'fieldId']) }}>

    {{-- Field area with sideline padding --}}
    <div @if($fieldId) id="{{ $fieldId }}" @endif class="absolute inset-x-[4%] inset-y-[3%]">

        {{-- Sideline border --}}
        <div class="absolute inset-0 border border-pitch-line pointer-events-none"></div>

        {{-- Pitch markings --}}
        <div class="pitch-center-line"></div>
        <div class="pitch-center-circle"></div>
        <div class="pitch-box-top"></div>
        <div class="pitch-box-bottom"></div>
        <div class="pitch-six-top"></div>
        <div class="pitch-six-bottom"></div>
        <div class="pitch-arc-top"></div>
        <div class="pitch-arc-bottom"></div>
        <div class="absolute left-1/2 top-1/2 w-2 h-2 rounded-full bg-white/20 -translate-x-1/2 -translate-y-1/2"></div>
        <div class="pitch-penalty-spot-top"></div>
        <div class="pitch-penalty-spot-bottom"></div>

        {{-- Slot for player badges, grid overlay, etc. --}}
        {{ $slot }}

    </div>
</div>
