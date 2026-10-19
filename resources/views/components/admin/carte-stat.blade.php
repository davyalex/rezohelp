@props(['libelle', 'valeur', 'detail' => null, 'href' => null, 'ton' => 'neutre'])
@php
    $classes = [
        'block rounded-2xl bg-white p-4 shadow-sm ring-1',
        'ring-slate-200/70' => $ton === 'neutre',
        'ring-amber-300' => $ton === 'attention',
        'ring-red-300' => $ton === 'danger',
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([...$classes, 'hover:ring-brand']) }}>
@else
    <div {{ $attributes->class($classes) }}>
@endif
    <p class="text-sm text-slate-600">{{ $libelle }}</p>
    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $valeur }}</p>
    @if ($detail)
        <p class="mt-1 text-xs text-slate-500">{{ $detail }}</p>
    @endif
@if ($href)
    </a>
@else
    </div>
@endif
