@props([
    'variante' => 'primaire',
    'href' => null,
])
@php
    $classes = [
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg px-4 text-base font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60',
        'bg-brand text-white hover:bg-brand-fonce' => $variante === 'primaire',
        'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50' => $variante === 'secondaire',
        'text-brand underline-offset-4 hover:underline' => $variante === 'lien',
        'bg-red-700 text-white hover:bg-red-800' => $variante === 'danger',
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'submit'])->class($classes) }}>{{ $slot }}</button>
@endif
